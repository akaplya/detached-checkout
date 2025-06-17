<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
namespace CommerceOptimizer\Checkout\Plugin\Quote\Model\Quote;

use CommerceOptimizer\Checkout\Model\ProductHydrator;
use CommerceOptimizer\Checkout\Model\ProductRegistry;
use Magento\Quote\Model\Quote\Item as Subject;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Catalog\Model\Product;

class Item
{
    private ProductRegistry $productRegistry;
    private ProductHydrator $productHydrator;
    private JsonSerializer $jsonSerializer;

    public function __construct(
        ProductRegistry $productRegistry,
        ProductHydrator $productHydrator,
        JsonSerializer $jsonSerializer
    ) {
        $this->productRegistry = $productRegistry;
        $this->jsonSerializer = $jsonSerializer;
        $this->productHydrator = $productHydrator;
    }

    public function afterBeforeSave(Subject $subject)
    {
        if ($subject->isObjectNew()) {
            $product = $this->productRegistry->getProduct($subject->getSku());
            $subject->setProductView($this->jsonSerializer->serialize($product->getProductView()));
        }
    }
    public function afterRepresentProduct(Subject $subject, $result)
    {
        return false;
    }

    public function aroundGetProduct(Subject $subject, \Closure $proceed)
    {
        if (!empty($subject->getData('product')) && ($subject->getData('product') instanceof Product)) {
            $product = $subject->getData('product');
        } else {
            $product = $this->productHydrator->hydrate(
                $this->jsonSerializer->unserialize($subject->getProductView())
            );
        }
        return $product;

    }

}
