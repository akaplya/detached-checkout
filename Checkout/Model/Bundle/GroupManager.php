<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace CommerceOptimizer\Checkout\Model\Bundle;

/**
 * Request-scoped registry that tracks grouped cart item sets before their DB IDs are known.
 *
 * Covers both bundle products (multiple component items) and configurable products
 * (single item, but carrying parent-SKU + selected-option metadata for the extension table).
 *
 * Workflow:
 *  1. AddProductsToCart plugin calls createGroup() before items are saved.
 *  2. Quote/Item plugin calls getGroupKey() in afterAfterSave to write the temporary key to DB.
 *  3. AddProductsToCart plugin calls getGroupKeys() in afterResolve, replaces temporary keys
 *     with the final comma-separated item_id list, and writes quote_item_group_info records.
 */
class GroupManager
{
    /**
     * @var array<string, array{skus: string[], parent_sku: string, product_type: string, selected_options: string[], product_view: array}>
     *   groupKey => metadata
     */
    private array $groups = [];

    /** @var array<string, string>  sku => groupKey */
    private array $skuToGroupKey = [];

    /**
     * Registers a new group and returns a temporary key used as a placeholder in the DB.
     *
     * @param string[] $componentSkus       SKUs of every quote item in this group.
     * @param string   $parentSku           Bundle or configurable parent SKU.
     * @param string   $productType         'bundle' or 'configurable'.
     * @param string[] $selectedOptions     Option value IDs as sent by the client.
     * @param array    $productView         Full parent product view from ACO.
     */
    public function createGroup(
        array $componentSkus,
        string $parentSku = '',
        string $productType = 'bundle',
        array $selectedOptions = [],
        array $productView = []
    ): string {
        $groupKey = 'bgrp_' . bin2hex(random_bytes(8));

        $this->groups[$groupKey] = [
            'skus'             => $componentSkus,
            'parent_sku'       => $parentSku,
            'product_type'     => $productType,
            'selected_options' => $selectedOptions,
            'product_view'     => $productView,
        ];

        foreach ($componentSkus as $sku) {
            $this->skuToGroupKey[$sku] = $groupKey;
        }

        return $groupKey;
    }

    /**
     * Returns the temporary group key for the given component SKU, or null if not tracked.
     */
    public function getGroupKey(string $sku): ?string
    {
        return $this->skuToGroupKey[$sku] ?? null;
    }

    /**
     * Returns all group keys created during this request.
     *
     * @return string[]
     */
    public function getGroupKeys(): array
    {
        return array_keys($this->groups);
    }

    /**
     * Returns stored metadata for one group, or an empty array if not found.
     *
     * @return array{skus: string[], parent_sku: string, product_type: string, selected_options: string[], product_view: array}
     */
    public function getGroupMeta(string $groupKey): array
    {
        return $this->groups[$groupKey] ?? [];
    }

    public function isBundle(string $sku): bool
    {
        return isset($this->skuToGroupKey[$sku]);
    }
}
