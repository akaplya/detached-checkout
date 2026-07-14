<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace CommerceOptimizer\Checkout\Plugin\Quote\Model\Quote;

use CommerceOptimizer\Checkout\Model\Bundle\GroupManager;
use CommerceOptimizer\Checkout\Model\CompositeProductHydrator;
use CommerceOptimizer\Checkout\Model\Expiration\Config as ExpirationConfig;
use CommerceOptimizer\Checkout\Model\ProductRegistry;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Quote\Model\Quote\Item as Subject;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Plugin for Quote Item to handle Commerce Optimizer products.
 * Also populates the items_group column for both bundle and non-bundle CO items.
 */
class Item
{
    public function __construct(
        private readonly ProductRegistry $productRegistry,
        private readonly CompositeProductHydrator $productHydrator,
        private readonly JsonSerializer $jsonSerializer,
        private readonly LoggerInterface $logger,
        private readonly GroupManager $groupManager,
        private readonly ResourceConnection $resourceConnection,
        private readonly ExpirationConfig $expirationConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Save product view data and set the initial expiration timestamp before persisting a new CO item.
     */
    public function afterBeforeSave(Subject $subject): void
    {
        if (!$subject->isObjectNew()) {
            return;
        }

        $isCoProduct = (bool)$subject->getProductView();
        if (!$isCoProduct) {
            $product = $this->productRegistry->getProduct($subject->getSku());
            if ($product && $product->getProductView()) {
                $subject->setProductView($this->jsonSerializer->serialize($product->getProductView()));
                $isCoProduct = true;
            }
        }

        if ($isCoProduct && !$subject->getData('expires_at')) {
            $subject->setData('expires_at', $this->expirationConfig->calculateExpiresAt($this->resolveWebsiteId($subject)));
        }
    }

    /**
     * Resolve the website ID for the item's store, for scoped config lookups.
     *
     * @param Subject $item
     * @return int|null
     */
    private function resolveWebsiteId(Subject $item): ?int
    {
        $storeId = $item->getStoreId();
        if (!$storeId) {
            return null;
        }

        try {
            return (int)$this->storeManager->getStore($storeId)->getWebsiteId();
        } catch (\Exception $e) {
            $this->logger->debug('Could not resolve website ID for quote item, using default scope config', [
                'item_id' => $item->getId(),
                'store_id' => $storeId,
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * Populate items_group after the item is persisted (item_id is now available).
     *
     * For bundle components: write the temporary group key so afterResolve can
     * replace it with the final comma-separated item_id list.
     * For non-bundle CO items: write the item's own item_id.
     */
    public function afterAfterSave(Subject $subject, Subject $result): Subject
    {
        $itemId = (int)$subject->getId();
        if (!$itemId) {
            return $result;
        }

        // Only act on Commerce Optimizer products (those that have a product in registry
        // or that have a product_view stored).
        $sku = $subject->getSku();
        $inRegistry = $this->productRegistry->getProduct($sku) !== null;
        $hasProductView = (bool)$subject->getProductView();

        if (!$inRegistry && !$hasProductView) {
            return $result;
        }

        $groupKey = $this->groupManager->getGroupKey($sku);

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('quote_item');

        if ($groupKey !== null) {
            // Bundle component: write temporary group key; afterResolve will finalise.
            $connection->update($table, ['items_group' => $groupKey], ['item_id = ?' => $itemId]);
        } else {
            // Non-bundle CO item: set items_group to own item_id (only when null).
            $connection->update(
                $table,
                ['items_group' => (string)$itemId],
                ['item_id = ?' => $itemId, 'items_group IS NULL']
            );
        }

        return $result;
    }

    /**
     * Prevent product representation matching to ensure separate cart items.
     */
    public function afterRepresentProduct(Subject $subject, bool $result): bool
    {
        $product = $this->productRegistry->getProduct($subject->getSku());
        if ($product !== null) {
            return false;
        }
        return $result;
    }

    /**
     * Get product from stored view data or registry instead of a DB load.
     */
    public function aroundGetProduct(Subject $subject, \Closure $proceed): Product
    {
        if (!empty($subject->getData('product')) && ($subject->getData('product') instanceof Product)) {
            return $subject->getData('product');
        }

        $productView = $subject->getProductView();
        if ($productView) {
            try {
                $viewData = $this->jsonSerializer->unserialize($productView);
                return $this->hydrateProduct($viewData, $subject);
            } catch (\Exception $e) {
                $this->logger->error('Error hydrating product from view', [
                    'item_id' => $subject->getId(),
                    'sku' => $subject->getSku(),
                    'exception' => $e,
                ]);
            }
        }

        $product = $this->productRegistry->getProduct($subject->getSku());
        if ($product !== null) {
            return $product;
        }

        return $proceed();
    }

    private function hydrateProduct(array $viewData, Subject $item): Product
    {
        if ($this->productHydrator->isConfigurable($viewData)) {
            if ($item->getParentItemId()) {
                return $this->productHydrator->hydrateSimple($viewData);
            }
            $childItem = $this->findChildItem($item);
            if ($childItem && $childItem->getProductView()) {
                try {
                    $variantData = $this->jsonSerializer->unserialize($childItem->getProductView());
                    $selectedOptions = $viewData['selectedOptions'] ?? [];
                    [$parentProduct,] = $this->productHydrator->hydrateConfigurable(
                        $viewData,
                        $variantData,
                        $selectedOptions
                    );
                    return $parentProduct;
                } catch (\Exception $e) {
                    $this->logger->warning('Could not hydrate configurable with variant, falling back to simple', [
                        'item_id' => $item->getId(),
                        'sku' => $item->getSku(),
                        'exception' => $e,
                    ]);
                }
            }
        }

        return $this->productHydrator->hydrateSimple($viewData);
    }

    private function findChildItem(Subject $parentItem): ?Subject
    {
        foreach ($parentItem->getChildren() as $child) {
            return $child;
        }
        return null;
    }
}
