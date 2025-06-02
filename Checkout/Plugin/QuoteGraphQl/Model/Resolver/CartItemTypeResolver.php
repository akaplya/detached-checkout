<?php

namespace CommerceOptimizer\Checkout\Plugin\QuoteGraphQl\Model\Resolver;

use Magento\Framework\Exception\LocalizedException;

class CartItemTypeResolver extends \Magento\QuoteGraphQl\Model\Resolver\CartItemTypeResolver
{
    public function resolveType(array $data) : string
    {
        if (!isset($data['product'])) {
            throw new LocalizedException(__('Missing key "product" in cart data'));
        }
        return 'SimpleCartItem';
    }
}
