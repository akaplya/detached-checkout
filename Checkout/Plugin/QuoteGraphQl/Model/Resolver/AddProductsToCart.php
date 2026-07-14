<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace CommerceOptimizer\Checkout\Plugin\QuoteGraphQl\Model\Resolver;

use CommerceOptimizer\Checkout\Model\Bundle\GroupManager;
use CommerceOptimizer\Checkout\Model\Bundle\OptionDecoder;
use CommerceOptimizer\Checkout\Model\CompositeProductHydrator;
use CommerceOptimizer\Checkout\Model\Http\ProductProvider;
use CommerceOptimizer\Checkout\Model\ProductRegistry;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\QuoteGraphQl\Model\Resolver\AddProductsToCart as Subject;
use Psr\Log\LoggerInterface;

/**
 * Pre-loads products from Commerce Optimizer before adding to cart.
 *
 * Bundle products (ComplexProductView with bundle_item encoded options) are expanded
 * into individual simple cart items so that each selected option becomes its own quote item.
 *
 * After every mutation:
 *  - bundle groups in quote_item.items_group are finalised (temp key → comma-separated item_ids)
 *  - one row per logical selection is written to quote_item_group_info
 */
class AddProductsToCart
{
    /**
     * Configurable groups collected in beforeResolve, consumed in afterResolve.
     * Each entry: [parent_sku, selected_options, lookup_sku, product_view]
     *
     * @var array<int, array{parent_sku: string, selected_options: string[], lookup_sku: string, product_view: array}>
     */
    private array $pendingConfigurableGroups = [];

    public function __construct(
        private readonly ProductProvider $productProvider,
        private readonly CompositeProductHydrator $productHydrator,
        private readonly ProductRegistry $productRegistry,
        private readonly LoggerInterface $logger,
        private readonly GroupManager $groupManager,
        private readonly ResourceConnection $resourceConnection,
        private readonly OptionDecoder $optionDecoder
    ) {
    }

    // =========================================================================
    // GraphQL resolver hooks
    // =========================================================================

    /**
     * Pre-load products and expand bundle items before the resolver runs.
     */
    public function beforeResolve(
        Subject $subject,
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): array {
        $cartItemsData = $args['cartItems'] ?? [];
        $configurableItems  = [];
        $nonConfigurableItems = [];

        foreach ($cartItemsData as $index => $cartItem) {
            if (isset($cartItem['parent_sku']) && $cartItem['parent_sku'] !== '') {
                $configurableItems[$index] = $cartItem;
            } else {
                $nonConfigurableItems[$index] = $cartItem;
            }
        }

        // Fetch all non-configurable products from ACO in one request.
        $nonConfigSkus    = array_unique(array_column(array_values($nonConfigurableItems), 'sku'));
        $fetchedProducts  = !empty($nonConfigSkus) ? $this->productProvider->get($nonConfigSkus) : [];

        // Process non-configurable items: simple or bundle.
        $expandedCartItems = [];
        foreach ($nonConfigurableItems as $cartItem) {
            $sku         = $cartItem['sku'];
            $productData = $fetchedProducts[$sku] ?? null;

            if ($productData && $this->isBundleProduct($productData)) {
                $selectedOptions = $cartItem['selected_options'] ?? [];
                $quantity        = (float)($cartItem['quantity'] ?? 1);
                foreach ($this->expandBundleProduct($productData, $selectedOptions, $quantity) as $component) {
                    $expandedCartItems[] = $component;
                }
            } else {
                if ($productData) {
                    $this->productRegistry->registerProduct(
                        $this->productHydrator->hydrateSimple($productData)
                    );
                }
                $expandedCartItems[] = $cartItem;
            }
        }

        // Process configurable items.
        if (!empty($configurableItems)) {
            $this->processConfigurableProducts($configurableItems);
        }

        $args['cartItems'] = array_values(array_merge(
            $expandedCartItems,
            array_values($configurableItems)
        ));

        return [$field, $context, $info, $value, $args];
    }

    /**
     * Finalise bundle groups and write one quote_item_group_info row per logical selection.
     */
    public function afterResolve(
        Subject $subject,
        $result,
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $connection = $this->resourceConnection->getConnection();
        $itemTable  = $this->resourceConnection->getTableName('quote_item');
        $infoTable  = $this->resourceConnection->getTableName('quote_item_group_info');

        // --- bundle groups -------------------------------------------------
        foreach ($this->groupManager->getGroupKeys() as $groupKey) {
            $itemIds = $connection->fetchCol(
                $connection->select()
                    ->from($itemTable, ['item_id'])
                    ->where('items_group = ?', $groupKey)
            );

            if (empty($itemIds)) {
                $this->logger->warning('Bundle group has no saved items in DB', ['group_key' => $groupKey]);
                continue;
            }

            sort($itemIds, SORT_NUMERIC);
            $groupValue = implode(',', $itemIds);

            // Replace the temporary key with the final comma-separated list.
            $connection->update($itemTable, ['items_group' => $groupValue], ['items_group = ?' => $groupKey]);

            // Resolve quote_id from the first item.
            $quoteId = (int)$connection->fetchOne(
                $connection->select()->from($itemTable, ['quote_id'])->where('item_id = ?', $itemIds[0])
            );

            if ($quoteId) {
                $meta = $this->groupManager->getGroupMeta($groupKey);
                $connection->insert($infoTable, [
                    'quote_id'        => $quoteId,
                    'items_group'     => $groupValue,
                    'parent_sku'      => $meta['parent_sku']       ?? '',
                    'product_type'    => $meta['product_type']      ?? 'bundle',
                    'selected_options'=> json_encode($meta['selected_options'] ?? []),
                    'product_view'    => json_encode($meta['product_view']     ?? null),
                ]);

                $this->logger->debug('Wrote bundle group info', [
                    'quote_id'     => $quoteId,
                    'items_group'  => $groupValue,
                    'parent_sku'   => $meta['parent_sku'] ?? '',
                ]);
            }
        }

        // --- configurable groups -------------------------------------------
        foreach ($this->pendingConfigurableGroups as $info) {
            // The configurable parent item has sku = parent_sku, parent_item_id IS NULL.
            $row = $connection->fetchRow(
                $connection->select()
                    ->from($itemTable, ['item_id', 'quote_id'])
                    ->where('sku = ?', $info['lookup_sku'])
                    ->where('parent_item_id IS NULL')
                    ->order('item_id DESC')
                    ->limit(1)
            );

            if (!$row) {
                $this->logger->warning('Could not find configurable parent item for group info', $info);
                continue;
            }

            $connection->insert($infoTable, [
                'quote_id'         => (int)$row['quote_id'],
                'items_group'      => (string)$row['item_id'],
                'parent_sku'       => $info['parent_sku'],
                'product_type'     => 'configurable',
                'selected_options' => json_encode($info['selected_options'] ?? []),
                'product_view'     => json_encode($info['product_view']     ?? null),
            ]);

            $this->logger->debug('Wrote configurable group info', [
                'quote_id'   => $row['quote_id'],
                'item_id'    => $row['item_id'],
                'parent_sku' => $info['parent_sku'],
            ]);
        }

        // Reset for safety (shouldn't matter in PHP request lifecycle, but explicit is better).
        $this->pendingConfigurableGroups = [];

        return $result;
    }

    // =========================================================================
    // Bundle detection & expansion
    // =========================================================================

    private function isBundleProduct(array $productData): bool
    {
        if (($productData['__typename'] ?? '') !== 'ComplexProductView') {
            return false;
        }
        foreach ($productData['options'] ?? [] as $option) {
            foreach ($option['values'] ?? [] as $value) {
                if ($this->optionDecoder->isBundle($value['id'] ?? '')) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Expands one bundle cart item into multiple simple cart items (one per selected option).
     *
     * For each selected option the ACO product view (SimpleProductView) is pulled from the
     * ProductViewOptionValueProduct fragment already embedded in the parent bundle response —
     * the same pattern used for simple and configurable products. Prices and attributes
     * therefore come directly from ACO rather than from a synthetic fallback.
     *
     * @return array[]  Simple cart item arrays suitable for the cartItems arg
     */
    private function expandBundleProduct(array $productData, array $selectedOptionIds, float $quantity): array
    {
        $decodedOptions = $this->optionDecoder->decodeAll($selectedOptionIds);

        if (empty($decodedOptions)) {
            $this->logger->warning('Bundle product selected_options contain no decodable bundle_item IDs', [
                'sku'              => $productData['sku'] ?? '',
                'selected_options' => $selectedOptionIds,
            ]);
            return [];
        }

        // option value ID → {title, quantity, isDefault, product (SimpleProductView)}
        $optionValueProductMap = $this->buildOptionValueProductMap($productData);

        $componentSkus = array_column(array_values($decodedOptions), 'sku');
        $bundleSku     = $productData['sku']  ?? '';
        $bundleName    = $productData['name'] ?? 'Bundle';

        // Register the group with full metadata so afterResolve can write quote_item_group_info.
        $groupKey = $this->groupManager->createGroup(
            $componentSkus,
            $bundleSku,
            'bundle',
            $selectedOptionIds,
            $productData
        );

        $cartItems = [];

        foreach ($decodedOptions as $optionId => $decoded) {
            $componentSku = $decoded['sku'];
            $componentQty = $decoded['qty'] * $quantity;
            $optionEntry  = $optionValueProductMap[$optionId] ?? null;

            if ($optionEntry !== null) {
                // Use the real SimpleProductView returned by ACO for this option value.
                $productView         = $optionEntry['product'];
                $productView['name'] = $bundleName . ' - ' . ($optionEntry['title'] ?? $componentSku);
            } else {
                // Fallback for option values that are not ProductViewOptionValueProduct.
                $productView = [
                    '__typename'       => 'SimpleProductView',
                    'sku'              => $componentSku,
                    'name'             => $bundleName . ' - ' . $componentSku,
                    'shortDescription' => '',
                    'attributes'       => [],
                    'price'            => [
                        'regular' => ['amount' => ['value' => 0.0, 'currency' => 'USD']],
                        'final'   => ['amount' => ['value' => 0.0, 'currency' => 'USD']],
                    ],
                ];
            }

            // Stamp bundle metadata onto the view so it survives quote reload.
            $productView['bundleParentSku'] = $bundleSku;
            $productView['bundleOptionId']  = $optionId;
            $productView['bundleGroupKey']  = $groupKey;

            $this->productRegistry->registerProduct(
                $this->productHydrator->hydrateSimple($productView)
            );

            $this->logger->debug('Registered bundle component from ACO product view', [
                'component_sku' => $componentSku,
                'bundle_sku'    => $bundleSku,
                'group_key'     => $groupKey,
                'price'         => $productView['price']['final']['amount']['value'] ?? 'n/a',
            ]);

            $cartItems[] = ['sku' => $componentSku, 'quantity' => $componentQty];
        }

        return $cartItems;
    }

    /**
     * Builds a map from option value ID → ACO ProductViewOptionValueProduct entry.
     *
     * @return array<string, array{title: string, quantity: float, isDefault: bool, product: array}>
     */
    private function buildOptionValueProductMap(array $productData): array
    {
        $map = [];
        foreach ($productData['options'] ?? [] as $option) {
            foreach ($option['values'] ?? [] as $value) {
                if (($value['__typename'] ?? '') === 'ProductViewOptionValueProduct'
                    && isset($value['product'])
                ) {
                    $map[$value['id']] = [
                        'title'     => $value['title']     ?? '',
                        'quantity'  => (float)($value['quantity']  ?? 1.0),
                        'isDefault' => (bool)($value['isDefault']  ?? false),
                        'product'   => $value['product'],
                    ];
                }
            }
        }
        return $map;
    }

    // =========================================================================
    // Configurable product processing
    // =========================================================================

    private function processConfigurableProducts(array $configurableItems): void
    {
        $parentSkus = [];
        foreach ($configurableItems as $item) {
            $parentSkus[$item['parent_sku']] = $item['parent_sku'];
        }
        $parentProducts = $this->productProvider->get(array_values($parentSkus));

        foreach ($configurableItems as $item) {
            $parentSku       = $item['parent_sku'];
            $childSku        = $item['sku'];
            $selectedOptions = $item['selected_options'] ?? [];

            if (!isset($parentProducts[$parentSku])) {
                throw new GraphQlInputException(
                    __('Could not find configurable product with SKU "%1" in Commerce Optimizer.', $parentSku)
                );
            }

            $parentData      = $parentProducts[$parentSku];
            $isComplexProduct = $this->productHydrator->isConfigurable($parentData);

            if ($isComplexProduct && !empty($selectedOptions)) {
                $this->processComplexProductWithOptions($parentData, $selectedOptions, $childSku);
            } else {
                $variantData = null;
                if (!empty($selectedOptions)) {
                    $variantData = $this->productProvider->getVariant($parentSku, $selectedOptions);
                }
                if ($variantData === null && $childSku !== $parentSku) {
                    $childProducts = $this->productProvider->get([$childSku]);
                    $variantData   = $childProducts[$childSku] ?? null;
                }
                if ($variantData === null) {
                    $this->processComplexProductWithOptions($parentData, $selectedOptions, $childSku);
                } else {
                    [$parentProduct, $childProduct] = $this->productHydrator->hydrateConfigurable(
                        $parentData,
                        $variantData,
                        $selectedOptions
                    );
                    $this->productRegistry->registerProduct($parentProduct);
                    $this->productRegistry->registerProduct($childProduct);
                }
            }

            // Collect info for quote_item_group_info — resolved in afterResolve.
            $this->pendingConfigurableGroups[] = [
                'parent_sku'       => $parentSku,
                'selected_options' => $selectedOptions,
                'lookup_sku'       => $parentSku,
                'product_view'     => $parentData,
            ];
        }
    }

    private function processComplexProductWithOptions(array $parentData, array $selectedOptions, string $sku): void
    {
        $product = $this->productHydrator->hydrateSimple(
            $this->createSimpleFromComplex($parentData, $selectedOptions, $sku)
        );
        $this->productRegistry->registerProduct($product);
    }

    private function createSimpleFromComplex(array $complexData, array $selectedOptions, string $sku): array
    {
        $optionLabels = $this->getSelectedOptionLabels($complexData, $selectedOptions);
        $optionSuffix = !empty($optionLabels) ? ' - ' . implode(', ', $optionLabels) : '';

        $price = $finalPrice = 0;
        if (isset($complexData['priceRange']['minimum'])) {
            $price      = $complexData['priceRange']['minimum']['regular']['amount']['value'] ?? 0;
            $finalPrice = $complexData['priceRange']['minimum']['final']['amount']['value']   ?? $price;
        }

        return [
            '__typename'       => 'SimpleProductView',
            'sku'              => $sku,
            'name'             => ($complexData['name'] ?? 'Product') . $optionSuffix,
            'shortDescription' => $complexData['shortDescription'] ?? '',
            'attributes'       => $complexData['attributes'] ?? [],
            'price'            => [
                'regular' => ['amount' => ['value' => $price,      'currency' => 'USD']],
                'final'   => ['amount' => ['value' => $finalPrice, 'currency' => 'USD']],
            ],
            'selectedOptions'  => $selectedOptions,
            'parentSku'        => $complexData['sku'] ?? '',
        ];
    }

    private function getSelectedOptionLabels(array $complexData, array $selectedOptions): array
    {
        $labels = [];
        foreach ($complexData['options'] ?? [] as $option) {
            foreach ($option['values'] ?? [] as $value) {
                if (in_array($value['id'], $selectedOptions, true)) {
                    $labels[] = $value['title'] ?? $value['id'];
                }
            }
        }
        return $labels;
    }
}
