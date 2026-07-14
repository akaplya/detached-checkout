<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace CommerceOptimizer\Checkout\Plugin\QuoteGraphQl\Model\Resolver;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Framework\Exception\LocalizedException;

/**
 * Resolver for cart item types including configurable products from Commerce Optimizer
 */
class CartItemTypeResolver extends \Magento\QuoteGraphQl\Model\Resolver\CartItemTypeResolver
{
    /**
     * Resolve the cart item type based on the product type
     *
     * @param array $data
     * @return string
     * @throws LocalizedException
     */
    public function resolveType(array $data): string
    {
        if (!isset($data['product'])) {
            throw new LocalizedException(__('Missing key "product" in cart data'));
        }

        $product = $data['product'];
        
        // Handle both Product object and array
        $typeId = null;
        if ($product instanceof Product) {
            $typeId = $product->getTypeId();
        } elseif (is_array($product) && isset($product['type_id'])) {
            $typeId = $product['type_id'];
        }
        
        // Check if it's a configurable product
        if ($typeId === ConfigurableType::TYPE_CODE) {
            return 'ConfigurableCartItem';
        }

        // Check if this is a child item of a configurable product
        if (isset($data['model']) && $data['model']->getParentItemId()) {
            // This is a child item, don't expose it directly
            return 'SimpleCartItem';
        }

        return 'SimpleCartItem';
    }
}
