<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace CommerceOptimizer\Checkout\Model\Bundle;

/**
 * Decodes ACO bundle option value IDs into component SKU/qty pairs.
 *
 * ACO encodes bundle option values as base64(bundle_item/{"sku":"...","qty":1.0}).
 */
class OptionDecoder
{
    private const BUNDLE_PREFIX = 'bundle_item/';

    /**
     * Returns true if the given option value ID is a bundle_item encoded string.
     */
    public function isBundle(string $optionValueId): bool
    {
        $decoded = base64_decode($optionValueId, true);
        return $decoded !== false && str_starts_with($decoded, self::BUNDLE_PREFIX);
    }

    /**
     * Decodes a single option value ID.
     *
     * @return array{sku: string, qty: float}|null
     */
    public function decode(string $optionValueId): ?array
    {
        $raw = base64_decode($optionValueId, true);
        if ($raw === false || !str_starts_with($raw, self::BUNDLE_PREFIX)) {
            return null;
        }

        $json = substr($raw, strlen(self::BUNDLE_PREFIX));
        $data = json_decode($json, true);

        if (!is_array($data) || empty($data['sku'])) {
            return null;
        }

        return [
            'sku' => (string)$data['sku'],
            'qty' => (float)($data['qty'] ?? 1.0),
        ];
    }

    /**
     * Decodes all bundle option value IDs, skipping non-bundle IDs.
     *
     * @param string[] $optionValueIds
     * @return array<string, array{sku: string, qty: float}>  keyed by original option value ID
     */
    public function decodeAll(array $optionValueIds): array
    {
        $result = [];
        foreach ($optionValueIds as $id) {
            $decoded = $this->decode($id);
            if ($decoded !== null) {
                $result[$id] = $decoded;
            }
        }
        return $result;
    }
}
