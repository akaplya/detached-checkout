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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Tax\Helper\Data as TaxHelper;

/**
 * Hydrator for simple products from Commerce Optimizer
 */
class SimpleProductHydrator implements ProductHydratorInterface
{
    /**
     * @var ProductFactory
     */
    private ProductFactory $productFactory;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $config;

    /**
     * @param ProductFactory $productFactory
     * @param ScopeConfigInterface $config
     */
    public function __construct(
        ProductFactory $productFactory,
        ScopeConfigInterface $config
    ) {
        $this->productFactory = $productFactory;
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    public function supports(array $data): bool
    {
        $typename = $data['__typename'] ?? 'SimpleProductView';
        
        // Supports SimpleProductView or products without options
        return $typename === 'SimpleProductView' && !isset($data['options']);
    }

    /**
     * @inheritdoc
     */
    public function hydrate(array $data, array $context = []): array
    {
        $product = $this->productFactory->create();
        
        // Store the original view data
        $product->setProductView($data);
        
        // Set basic product data
        $product->setName($data['name'] ?? 'Unknown Product');
        $product->setSku($data['sku'] ?? '');
        $product->setId($this->generateProductId($data['sku'] ?? ''));
        $product->setTypeId('simple');
        
        // Set prices
        $this->setPrices($product, $data);
        
        // Set tax class
        $defaultTaxClassId = $this->config->getValue(TaxHelper::CONFIG_DEFAULT_PRODUCT_TAX_CLASS);
        $product->setTaxClassId($defaultTaxClassId);
        
        // Set status
        $product->setStatus(ProductStatus::STATUS_ENABLED);
        
        // Set attributes
        if (isset($data['attributes'])) {
            foreach ($data['attributes'] as $attribute) {
                $product->setData($attribute['name'], $attribute['value']);
            }
        }
        
        return [$product];
    }

    /**
     * Set prices on product
     *
     * @param Product $product
     * @param array $data
     * @return void
     */
    private function setPrices(Product $product, array $data): void
    {
        if (isset($data['price'])) {
            $regularPrice = $data['price']['regular']['amount']['value'] ?? 0;
            $finalPrice = $data['price']['final']['amount']['value'] ?? $regularPrice;
            
            $product->setPrice((float)$regularPrice);
            $product->setSpecialPrice((float)$finalPrice);
        } else {
            $product->setPrice(0);
            $product->setSpecialPrice(0);
        }
    }

    /**
     * Generate a unique product ID based on SKU
     *
     * @param string $sku
     * @return int
     */
    private function generateProductId(string $sku): int
    {
        if (empty($sku)) {
            return time();
        }
        return abs(crc32($sku));
    }
}
