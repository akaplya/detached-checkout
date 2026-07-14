<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace CommerceOptimizer\QuoteGraphQl\Model\Resolver\CartItem;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class CartItemGroup implements ResolverInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): ?array {
        $item = $value['model'];
        $itemsGroup = $item->getData('items_group');
        if (!$itemsGroup) {
            return null;
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('quote_item_group_info');

        $row = $connection->fetchRow(
            $connection->select()
                ->from($table)
                ->where('items_group = ?', $itemsGroup)
                ->where('quote_id = ?', (int)$item->getQuoteId())
                ->limit(1)
        );

        if (!$row || empty($row['product_view'])) {
            return null;
        }

        $productView     = json_decode($row['product_view'], true);
        $selectedIds     = json_decode($row['selected_options'] ?? '[]', true) ?: [];
        $selectedIdSet   = array_flip($selectedIds);

        return [
            'sku'              => $productView['sku']  ?? $row['parent_sku'],
            'name'             => $productView['name'] ?? '',
            'image'            => $this->extractImage($productView['images'] ?? []),
            'selected_options' => $this->resolveSelectedOptions($productView['options'] ?? [], $selectedIdSet),
        ];
    }

    /**
     * Builds the selected_options list by matching stored value IDs against the ACO product view options.
     * Only option groups that have at least one selected value are included.
     */
    private function resolveSelectedOptions(array $options, array $selectedIdSet): array
    {
        $result = [];
        foreach ($options as $option) {
            $selectedValues = [];
            foreach ($option['values'] ?? [] as $value) {
                if (isset($selectedIdSet[$value['id'] ?? ''])) {
                    $selectedValues[] = ['label' => $value['title'] ?? $value['id']];
                }
            }
            if (!empty($selectedValues)) {
                $result[] = [
                    'label'  => $option['title'] ?? $option['id'] ?? '',
                    'values' => $selectedValues,
                ];
            }
        }
        return $result;
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
