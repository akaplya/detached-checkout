<?php

namespace CommerceOptimizer\Checkout\Model;

use Magento\Catalog\Model\Product;

class ProductRegistry
{
    private array $products = [];

    /**
     * @param Product $product
     * @return void
     */
    public function registerProduct(Product $product): void
    {
        $this->products[$product->getSku()] = $product;
    }

    /**
     * @param string $sku
     * @return null|Product
     */
    public function getProduct(string $sku): ?Product
    {
        return $this->products[$sku];
    }
}
