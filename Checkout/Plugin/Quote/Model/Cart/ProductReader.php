<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
namespace CommerceOptimizer\Checkout\Plugin\Quote\Model\Cart;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Quote\Model\Cart\ProductReaderInterface;
use CommerceOptimizer\Checkout\Model\ProductRegistry;
class ProductReader implements ProductReaderInterface
{
    private ProductRegistry $productRegistry;

    public function __construct(
        ProductRegistry $productRegistry
    ) {
      $this->productRegistry = $productRegistry;
    }

    public function loadProducts(array $skus, int $storeId)
    {
        // TODO: Implement loadProducts() method.
        return [];
    }

    public function getProductBySku(string $sku): ?ProductInterface
    {
        // TODO: Implement getProductBySku() method.
        return $this->productRegistry->getProduct($sku);
    }
}
