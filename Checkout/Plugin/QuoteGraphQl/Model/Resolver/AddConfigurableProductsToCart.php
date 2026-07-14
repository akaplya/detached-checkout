<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace CommerceOptimizer\Checkout\Plugin\QuoteGraphQl\Model\Resolver;

use CommerceOptimizer\Checkout\Model\CompositeProductHydrator;
use CommerceOptimizer\Checkout\Model\Http\ProductProvider;
use CommerceOptimizer\Checkout\Model\ProductRegistry;
use Magento\ConfigurableProductGraphQl\Model\Resolver\AddConfigurableProductsToCart as Subject;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Psr\Log\LoggerInterface;

/**
 * Plugin to pre-load configurable products from Commerce Optimizer before adding to cart
 */
class AddConfigurableProductsToCart
{
    /**
     * @var ProductProvider
     */
    private ProductProvider $productProvider;

    /**
     * @var CompositeProductHydrator
     */
    private CompositeProductHydrator $productHydrator;

    /**
     * @var ProductRegistry
     */
    private ProductRegistry $productRegistry;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param ProductProvider $productProvider
     * @param CompositeProductHydrator $productHydrator
     * @param ProductRegistry $productRegistry
     * @param LoggerInterface $logger
     */
    public function __construct(
        ProductProvider $productProvider,
        CompositeProductHydrator $productHydrator,
        ProductRegistry $productRegistry,
        LoggerInterface $logger
    ) {
        $this->productProvider = $productProvider;
        $this->productHydrator = $productHydrator;
        $this->productRegistry = $productRegistry;
        $this->logger = $logger;
    }

    /**
     * Pre-load configurable products from Commerce Optimizer
     *
     * @param Subject $subject
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws GraphQlInputException
     */
    public function beforeResolve(
        Subject $subject,
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): array {
        $cartItems = $args['input']['cart_items'] ?? [];
        
        if (empty($cartItems)) {
            return [$field, $context, $info, $value, $args];
        }

        // Collect parent SKUs
        $parentSkus = [];
        $childSkus = [];
        
        foreach ($cartItems as $item) {
            $parentSku = $item['parent_sku'] ?? null;
            $childSku = $item['data']['sku'] ?? null;
            
            if ($parentSku) {
                $parentSkus[$parentSku] = $parentSku;
            }
            if ($childSku) {
                $childSkus[$childSku] = $childSku;
            }
        }

        // Fetch all parent products
        $parentProducts = [];
        if (!empty($parentSkus)) {
            $parentProducts = $this->productProvider->get(array_values($parentSkus));
        }

        // Fetch all child products (variants)
        $childProducts = [];
        if (!empty($childSkus)) {
            $childProducts = $this->productProvider->get(array_values($childSkus));
        }

        // Process each cart item
        foreach ($cartItems as $item) {
            $parentSku = $item['parent_sku'] ?? null;
            $childSku = $item['data']['sku'] ?? null;
            $selectedOptions = $item['selected_options'] ?? [];

            if (!$parentSku) {
                continue;
            }

            // Get parent data
            if (!isset($parentProducts[$parentSku])) {
                throw new GraphQlInputException(
                    __('Could not find configurable product with SKU "%1" in Commerce Optimizer.', $parentSku)
                );
            }
            $parentData = $parentProducts[$parentSku];

            // Get variant data
            $variantData = null;
            
            // First try to get variant using selected options
            if (!empty($selectedOptions)) {
                $variantData = $this->productProvider->getVariant($parentSku, $selectedOptions);
            }

            // Fallback to child SKU lookup
            if ($variantData === null && $childSku && isset($childProducts[$childSku])) {
                $variantData = $childProducts[$childSku];
            }

            if ($variantData === null) {
                throw new GraphQlInputException(
                    __('Could not find variant for configurable product "%1".', $parentSku)
                );
            }

            // Hydrate and register products using composite hydrator
            [$parentProduct, $childProduct] = $this->productHydrator->hydrateConfigurable(
                $parentData,
                $variantData,
                $selectedOptions
            );

            $this->productRegistry->registerProduct($parentProduct);
            $this->productRegistry->registerProduct($childProduct);

            $this->logger->debug('Registered configurable product from Commerce Optimizer', [
                'parent_sku' => $parentSku,
                'child_sku' => $childProduct->getSku(),
                'selected_options' => $selectedOptions
            ]);
        }

        return [$field, $context, $info, $value, $args];
    }
}
