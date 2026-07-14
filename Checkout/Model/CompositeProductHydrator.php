<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace CommerceOptimizer\Checkout\Model;

use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;

/**
 * Composite product hydrator that delegates to appropriate hydrator based on product type
 *
 * This class implements the Composite pattern to hide the knowledge of specific
 * hydrators from clients. Clients use this single hydrator and the appropriate
 * specialized hydrator is selected automatically based on the product data.
 */
class CompositeProductHydrator implements ProductHydratorInterface
{
    /**
     * @var ProductHydratorInterface[]
     */
    private array $hydrators;

    /**
     * @param ProductHydratorInterface[] $hydrators
     */
    public function __construct(
        array $hydrators = []
    ) {
        $this->hydrators = $hydrators;
    }

    /**
     * @inheritdoc
     */
    public function supports(array $data): bool
    {
        foreach ($this->hydrators as $hydrator) {
            if ($hydrator->supports($data)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function hydrate(array $data, array $context = []): array
    {
        $hydrator = $this->getHydrator($data);
        
        if ($hydrator === null) {
            throw new LocalizedException(
                __('No hydrator found for product type: %1', $data['__typename'] ?? 'unknown')
            );
        }

        return $hydrator->hydrate($data, $context);
    }

    /**
     * Get the appropriate hydrator for the given product data
     *
     * @param array $data
     * @return ProductHydratorInterface|null
     */
    private function getHydrator(array $data): ?ProductHydratorInterface
    {
        foreach ($this->hydrators as $hydrator) {
            if ($hydrator->supports($data)) {
                return $hydrator;
            }
        }
        return null;
    }

    /**
     * Hydrate a simple product from Commerce Optimizer data
     *
     * Convenience method for simple product hydration
     *
     * @param array $data Product data from Commerce Optimizer
     * @return Product The hydrated product
     */
    public function hydrateSimple(array $data): Product
    {
        $products = $this->hydrate($data);
        return $products[0];
    }

    /**
     * Hydrate a configurable product with its variant
     *
     * Convenience method for configurable product hydration
     *
     * @param array $parentData Parent configurable product data
     * @param array $variantData Variant (simple product) data
     * @param array $selectedOptions Selected option IDs
     * @return array [parent Product, child Product]
     */
    public function hydrateConfigurable(array $parentData, array $variantData, array $selectedOptions = []): array
    {
        $context = [
            ConfigurableProductHydrator::CONTEXT_VARIANT_DATA => $variantData,
            ConfigurableProductHydrator::CONTEXT_SELECTED_OPTIONS => $selectedOptions
        ];

        return $this->hydrate($parentData, $context);
    }

    /**
     * Check if the given data represents a configurable product
     *
     * @param array $data
     * @return bool
     */
    public function isConfigurable(array $data): bool
    {
        $typename = $data['__typename'] ?? 'SimpleProductView';
        return $typename === 'ComplexProductView' || isset($data['options']);
    }
}
