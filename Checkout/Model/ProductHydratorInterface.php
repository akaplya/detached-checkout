<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace CommerceOptimizer\Checkout\Model;

use Magento\Catalog\Model\Product;

/**
 * Interface for product hydrators from Commerce Optimizer data
 */
interface ProductHydratorInterface
{
    /**
     * Check if this hydrator supports the given product data
     *
     * @param array $data Product data from Commerce Optimizer
     * @return bool
     */
    public function supports(array $data): bool;

    /**
     * Hydrate product(s) from Commerce Optimizer data
     *
     * @param array $data Product data from Commerce Optimizer
     * @param array $context Additional context (e.g., variant data, selected options)
     * @return Product[] Array of hydrated products
     */
    public function hydrate(array $data, array $context = []): array;
}
