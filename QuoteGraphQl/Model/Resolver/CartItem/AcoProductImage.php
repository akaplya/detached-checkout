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

class AcoProductImage implements ResolverInterface
{
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): ?array {
        $productViewJson = $value['model']->getData('product_view');
        if (!$productViewJson) {
            return null;
        }
        $productView = json_decode($productViewJson, true);
        return $this->extractImage($productView['images'] ?? []);
    }

    private function extractImage(array $images): ?array
    {
        if (empty($images)) {
            return null;
        }
        foreach ($images as $image) {
            if (in_array('thumbnail', $image['roles'] ?? [], true)) {
                return ['url' => $image['url'], 'label' => $image['label'] ?? null];
            }
        }
        return ['url' => $images[0]['url'], 'label' => $images[0]['label'] ?? null];
    }
}
