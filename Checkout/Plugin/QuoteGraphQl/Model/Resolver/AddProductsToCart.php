<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
namespace CommerceOptimizer\Checkout\Plugin\QuoteGraphQl\Model\Resolver;

use CommerceOptimizer\Checkout\Model\ProductRegistry;
use CommerceOptimizer\Checkout\Model\ProductHydrator;
use CommerceOptimizer\Checkout\Model\Http\ProductProvider;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\QuoteGraphQl\Model\Resolver\AddProductsToCart as Subject;

class AddProductsToCart
{
    private ProductProvider $productProvider;
    private ProductHydrator $productHydrator;
    private ProductRegistry $productRegistry;

    public function __construct(
        ProductProvider $productProvider,
        ProductHydrator $productHydrator,
        ProductRegistry $productRegistry
    ) {

        $this->productProvider = $productProvider;
        $this->productHydrator = $productHydrator;
        $this->productRegistry = $productRegistry;
    }
    public function beforeResolve(
        Subject $subject,
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $cartItemsData = $args['cartItems'];
        $skus = [];
        foreach ($cartItemsData as $cartItem) {
            $skus[] = $cartItem['sku'];
        }
        $views = $this->productProvider->get($skus);
        foreach ($views as $view) {
            $this->productRegistry->registerProduct($this->productHydrator->hydrate($view));
        }
        //TODO: validate input SKUs and service response
    }
}
