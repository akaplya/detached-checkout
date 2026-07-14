<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace CommerceOptimizer\Checkout\Plugin\Quote\Model\Cart;

use CommerceOptimizer\Checkout\Model\CompositeProductHydrator;
use CommerceOptimizer\Checkout\Model\Http\ProductProvider;
use CommerceOptimizer\Checkout\Model\ProductRegistry;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Quote\Model\Cart\ProductReaderInterface;

/**
 * Product reader for Commerce Optimizer products
 */
class ProductReader implements ProductReaderInterface
{
    /**
     * @var ProductRegistry
     */
    private ProductRegistry $productRegistry;

    /**
     * @var ProductProvider
     */
    private ProductProvider $productProvider;

    /**
     * @var CompositeProductHydrator
     */
    private CompositeProductHydrator $productHydrator;

    /**
     * @param ProductRegistry $productRegistry
     * @param ProductProvider $productProvider
     * @param CompositeProductHydrator $productHydrator
     */
    public function __construct(
        ProductRegistry $productRegistry,
        ProductProvider $productProvider,
        CompositeProductHydrator $productHydrator
    ) {
        $this->productRegistry = $productRegistry;
        $this->productProvider = $productProvider;
        $this->productHydrator = $productHydrator;
    }

    /**
     * Load products by SKUs from Commerce Optimizer
     *
     * @param array $skus
     * @param int $storeId
     * @return array
     */
    public function loadProducts(array $skus, int $storeId): array
    {
        $products = [];
        $skusToFetch = [];

        // Check registry first
        foreach ($skus as $sku) {
            $product = $this->productRegistry->getProduct($sku);
            if ($product !== null) {
                $products[$sku] = $product;
            } else {
                $skusToFetch[] = $sku;
            }
        }

        // Fetch missing products from Commerce Optimizer
        if (!empty($skusToFetch)) {
            $views = $this->productProvider->get($skusToFetch);
            foreach ($views as $sku => $view) {
                $product = $this->productHydrator->hydrateSimple($view);
                $this->productRegistry->registerProduct($product);
                $products[$sku] = $product;
            }
        }

        return $products;
    }

    /**
     * Get product by SKU
     *
     * @param string $sku
     * @return ProductInterface|null
     */
    public function getProductBySku(string $sku): ?ProductInterface
    {
        // First check registry
        $product = $this->productRegistry->getProduct($sku);
        
        if ($product !== null) {
            return $product;
        }

        // Try to fetch from Commerce Optimizer
        $views = $this->productProvider->get([$sku]);
        if (isset($views[$sku])) {
            $product = $this->productHydrator->hydrateSimple($views[$sku]);
            $this->productRegistry->registerProduct($product);
            return $product;
        }

        return null;
    }
}
