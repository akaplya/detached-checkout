<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace CommerceOptimizer\Checkout\Model;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\ProductFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Tax\Helper\Data as TaxHelper;

/**
 * Hydrator for configurable products from Commerce Optimizer
 */
class ConfigurableProductHydrator implements ProductHydratorInterface
{
    /**
     * Context key for variant data
     */
    public const CONTEXT_VARIANT_DATA = 'variant_data';

    /**
     * Context key for selected options
     */
    public const CONTEXT_SELECTED_OPTIONS = 'selected_options';

    /**
     * @var ProductFactory
     */
    private ProductFactory $productFactory;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $config;

    /**
     * @var SimpleProductHydrator
     */
    private SimpleProductHydrator $simpleProductHydrator;

    /**
     * @param ProductFactory $productFactory
     * @param ScopeConfigInterface $config
     * @param SimpleProductHydrator $simpleProductHydrator
     */
    public function __construct(
        ProductFactory $productFactory,
        ScopeConfigInterface $config,
        SimpleProductHydrator $simpleProductHydrator
    ) {
        $this->productFactory = $productFactory;
        $this->config = $config;
        $this->simpleProductHydrator = $simpleProductHydrator;
    }

    /**
     * @inheritdoc
     */
    public function supports(array $data): bool
    {
        $typename = $data['__typename'] ?? 'SimpleProductView';
        
        return $typename === 'ComplexProductView' || isset($data['options']);
    }

    /**
     * @inheritdoc
     */
    public function hydrate(array $data, array $context = []): array
    {
        $variantData = $context[self::CONTEXT_VARIANT_DATA] ?? null;
        $selectedOptions = $context[self::CONTEXT_SELECTED_OPTIONS] ?? [];

        // Create parent configurable product
        $parentProduct = $this->createConfigurableProduct($data, $selectedOptions);

        // If no variant data provided, return only parent
        if ($variantData === null) {
            return [$parentProduct];
        }

        // Create child simple product from variant
        $childProducts = $this->simpleProductHydrator->hydrate($variantData);
        $childProduct = $childProducts[0];

        // Link child to parent
        $this->linkProducts($parentProduct, $childProduct, $selectedOptions);

        return [$parentProduct, $childProduct];
    }

    /**
     * Create configurable product object
     *
     * @param array $data
     * @param array $selectedOptions
     * @return Product
     */
    private function createConfigurableProduct(array $data, array $selectedOptions): Product
    {
        $product = $this->productFactory->create();
        
        // Set basic product data
        $product->setName($data['name']);
        $product->setSku($data['sku']);
        $product->setTypeId(ConfigurableType::TYPE_CODE);
        $product->setId($this->generateProductId($data['sku']));
        
        // Set price from price range if available
        if (isset($data['priceRange']['minimum'])) {
            $minPrice = $data['priceRange']['minimum'];
            $product->setPrice((float)($minPrice['regular']['amount']['value'] ?? 0));
            $product->setSpecialPrice((float)($minPrice['final']['amount']['value'] ?? 0));
        }
        
        // Set tax class
        $defaultTaxClassId = $this->config->getValue(TaxHelper::CONFIG_DEFAULT_PRODUCT_TAX_CLASS);
        $product->setTaxClassId($defaultTaxClassId);
        
        // Set status
        $product->setStatus(ProductStatus::STATUS_ENABLED);
        
        // Store full product view data
        $product->setProductView($data);
        
        // Set attributes
        if (isset($data['attributes'])) {
            foreach ($data['attributes'] as $attribute) {
                $product->setData($attribute['name'], $attribute['value']);
            }
        }
        
        // Store configurable options for later use
        if (isset($data['options'])) {
            $product->setConfigurableOptions($this->processOptions($data['options']));
        }
        
        // Store selected options
        $product->setSelectedOptions($selectedOptions);
        
        return $product;
    }

    /**
     * Process configurable options from CO format
     *
     * @param array $options
     * @return array
     */
    private function processOptions(array $options): array
    {
        $processed = [];
        foreach ($options as $option) {
            $values = [];
            foreach ($option['values'] ?? [] as $value) {
                $values[] = [
                    'value_id' => $value['id'],
                    'label' => $value['title']
                ];
            }
            $processed[] = [
                'attribute_id' => $option['id'],
                'attribute_code' => $this->generateAttributeCode($option['title']),
                'label' => $option['title'],
                'values' => $values,
                'required' => $option['required'] ?? false
            ];
        }
        return $processed;
    }

    /**
     * Link parent and child products
     *
     * @param Product $parentProduct
     * @param Product $childProduct
     * @param array $selectedOptions
     * @return void
     */
    private function linkProducts(Product $parentProduct, Product $childProduct, array $selectedOptions): void
    {
        // Build super_attribute data from selected options
        $superAttribute = [];
        $configurableOptions = $parentProduct->getConfigurableOptions() ?? [];
        
        foreach ($configurableOptions as $option) {
            foreach ($option['values'] as $value) {
                if (in_array($value['value_id'], $selectedOptions)) {
                    $superAttribute[$option['attribute_id']] = $value['value_id'];
                    break;
                }
            }
        }

        // Set custom options on parent product
        $parentProduct->addCustomOption('attributes', json_encode($superAttribute));
        $parentProduct->addCustomOption('product_qty_' . $childProduct->getId(), 1, $childProduct);
        $parentProduct->addCustomOption('simple_product', $childProduct->getId(), $childProduct);
        
        // Set parent reference on child
        $childProduct->setParentProductId($parentProduct->getId());
        $childProduct->addCustomOption('parent_product_id', $parentProduct->getId());
    }

    /**
     * Generate a unique product ID based on SKU
     *
     * @param string $sku
     * @return int
     */
    private function generateProductId(string $sku): int
    {
        return abs(crc32($sku));
    }

    /**
     * Generate attribute code from label
     *
     * @param string $label
     * @return string
     */
    private function generateAttributeCode(string $label): string
    {
        return strtolower(str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9\s]/', '', $label)));
    }
}
