<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace CommerceOptimizer\Checkout\Model\Cart\BuyRequest;

use CommerceOptimizer\Checkout\Model\Http\ProductProvider;
use CommerceOptimizer\Checkout\Model\ProductRegistry;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\Stdlib\ArrayManagerFactory;
use Magento\Quote\Model\Quote;
use Magento\QuoteGraphQl\Model\Cart\BuyRequest\BuyRequestDataProviderInterface;

/**
 * DataProvider for building super attribute options for Commerce Optimizer products
 *
 * This replaces the default SuperAttributeDataProvider to allow configurable products
 * that don't exist in Adobe Commerce database but are available in Commerce Optimizer.
 */
class SuperAttributeDataProvider implements BuyRequestDataProviderInterface
{
    /**
     * @var ArrayManagerFactory
     */
    private ArrayManagerFactory $arrayManagerFactory;

    /**
     * @var ProductProvider
     */
    private ProductProvider $productProvider;

    /**
     * @var ProductRegistry
     */
    private ProductRegistry $productRegistry;

    /**
     * @param ArrayManagerFactory $arrayManagerFactory
     * @param ProductProvider $productProvider
     * @param ProductRegistry $productRegistry
     */
    public function __construct(
        ArrayManagerFactory $arrayManagerFactory,
        ProductProvider $productProvider,
        ProductRegistry $productRegistry
    ) {
        $this->arrayManagerFactory = $arrayManagerFactory;
        $this->productProvider = $productProvider;
        $this->productRegistry = $productRegistry;
    }

    /**
     * @inheritdoc
     */
    public function execute(array $cartItemData): array
    {
        $arrayManager = $this->arrayManagerFactory->create();
        $parentSku = $arrayManager->get('parent_sku', $cartItemData);
        
        if ($parentSku === null) {
            return [];
        }

        $sku = $arrayManager->get('data/sku', $cartItemData);
        $selectedOptions = $arrayManager->get('selected_options', $cartItemData) ?? [];
        $cart = $arrayManager->get('model', $cartItemData);
        
        if (!$cart instanceof Quote) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        // Check if parent product is already registered (fetched from Commerce Optimizer)
        $parentProduct = $this->productRegistry->getProduct($parentSku);
        
        if ($parentProduct === null) {
            throw new GraphQlInputException(
                __('Could not find parent product with SKU "%1" in Commerce Optimizer.', $parentSku)
            );
        }

        // Get configurable options from the registered parent product
        $configurableOptions = $parentProduct->getConfigurableOptions() ?? [];
        
        if (empty($configurableOptions) && !empty($selectedOptions)) {
            // Options were passed but we don't have configurable options info
            // This means selected_options contains the option IDs directly
            return $this->buildSuperAttributeFromSelectedOptions($selectedOptions, $parentSku);
        }

        // Build super_attribute from configurable options and selected options
        $superAttributesData = $this->buildSuperAttribute($configurableOptions, $selectedOptions);

        return ['super_attribute' => $superAttributesData];
    }

    /**
     * Build super_attribute data from configurable options and selected option IDs
     *
     * @param array $configurableOptions
     * @param array $selectedOptions
     * @return array
     */
    private function buildSuperAttribute(array $configurableOptions, array $selectedOptions): array
    {
        $superAttributesData = [];
        
        foreach ($configurableOptions as $option) {
            foreach ($option['values'] as $value) {
                if (in_array($value['value_id'], $selectedOptions, true)) {
                    $superAttributesData[$option['attribute_id']] = $value['value_id'];
                    break;
                }
            }
        }
        
        return $superAttributesData;
    }

    /**
     * Build super_attribute when we only have selected option IDs
     *
     * In this case, we generate attribute IDs based on the option position
     *
     * @param array $selectedOptions
     * @param string $parentSku
     * @return array
     */
    private function buildSuperAttributeFromSelectedOptions(array $selectedOptions, string $parentSku): array
    {
        $superAttributesData = [];
        
        // Generate attribute IDs based on the option value IDs
        // This is a simplified approach - in production you might want to fetch
        // the full product data from Commerce Optimizer to get actual attribute IDs
        $attributeId = 1;
        foreach ($selectedOptions as $optionValueId) {
            $superAttributesData[$attributeId] = $optionValueId;
            $attributeId++;
        }
        
        return ['super_attribute' => $superAttributesData];
    }
}
