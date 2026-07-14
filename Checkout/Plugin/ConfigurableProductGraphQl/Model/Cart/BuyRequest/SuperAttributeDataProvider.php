<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace CommerceOptimizer\Checkout\Plugin\ConfigurableProductGraphQl\Model\Cart\BuyRequest;

use CommerceOptimizer\Checkout\Model\ProductRegistry;
use Magento\ConfigurableProductGraphQl\Model\Cart\BuyRequest\SuperAttributeDataProvider as Subject;

/**
 * Plugin to skip database-based product validation for Commerce Optimizer products
 */
class SuperAttributeDataProvider
{
    /**
     * @var ProductRegistry
     */
    private ProductRegistry $productRegistry;

    /**
     * @param ProductRegistry $productRegistry
     */
    public function __construct(ProductRegistry $productRegistry)
    {
        $this->productRegistry = $productRegistry;
    }

    /**
     * Skip execution for Commerce Optimizer products
     *
     * If the product is registered in ProductRegistry, it means it's from Commerce Optimizer
     * and we should skip the default SuperAttributeDataProvider which requires products in database.
     *
     * @param Subject $subject
     * @param callable $proceed
     * @param array $cartItemData
     * @return array
     */
    public function aroundExecute(Subject $subject, callable $proceed, array $cartItemData): array
    {
        $parentSku = $cartItemData['parent_sku'] ?? null;
        
        if ($parentSku === null) {
            return $proceed($cartItemData);
        }

        // Check if parent product is from Commerce Optimizer
        $parentProduct = $this->productRegistry->getProduct($parentSku);
        
        if ($parentProduct !== null) {
            // Product is from Commerce Optimizer, return empty array
            // The super_attribute data is already built by our custom SuperAttributeDataProvider
            return [];
        }

        // Product is from database, use default behavior
        return $proceed($cartItemData);
    }
}
