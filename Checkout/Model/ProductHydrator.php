<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
namespace CommerceOptimizer\Checkout\Model;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Tax\Helper\Data as TaxHelper;

class ProductHydrator
{
    private ProductFactory $productFactory;
    private ScopeConfigInterface $config;

    public function __construct(
        ProductFactory $productFactory,
        ScopeConfigInterface $config
    ){
        $this->productFactory = $productFactory;
        $this->config = $config;
    }

    /**
     * @param $data
     * @return Product
     */
    public function hydrate($data): Product
    {
        $product = $this->productFactory->create();
        $product->setPrice((float)$data['price']['regular']['amount']['value']);
        $product->setSpecialPrice((float)$data['price']['final']['amount']['value']);
        $product->setProductView($data);
        $product->setName($data['name']);
        $product->setTypeId("simple");
        $product->setSku($data['sku']);
        $product->setId(time());
        $defaultTaxClassId = $this->config->getValue(TaxHelper::CONFIG_DEFAULT_PRODUCT_TAX_CLASS);
        $product->setTaxClassId($defaultTaxClassId);
        $product->setStatus(ProductStatus::STATUS_ENABLED);
        if (isset($data['attributes'])) {
            foreach ($data['attributes'] as $attribute) {
                $product->setData($attribute['name'], $attribute['value']);
            }
        }
        return $product;
    }
}
