<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace CommerceOptimizer\QuoteGraphQl\Model\Resolver\CartItem;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class AcoProductName implements ResolverInterface
{
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): ?string {
        $productViewJson = $value['model']->getData('product_view');
        if (!$productViewJson) {
            return null;
        }
        $productView = json_decode($productViewJson, true);
        return $productView['name'] ?? null;
    }
}
