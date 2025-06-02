<?php

namespace CommerceOptimizer\Checkout\Model;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\ProductFactory;

class ProductHydrator
{
    private ProductFactory $productFactory;

    public function __construct(
        ProductFactory $productFactory
    ){
        $this->productFactory = $productFactory;
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
        $product->setStatus(ProductStatus::STATUS_ENABLED);
        return $product;
    }
}
