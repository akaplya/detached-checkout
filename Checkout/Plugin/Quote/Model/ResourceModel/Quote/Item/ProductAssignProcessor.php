<?php

namespace CommerceOptimizer\Checkout\Plugin\Quote\Model\ResourceModel\Quote\Item;

use CommerceOptimizer\Checkout\Model\ProductHydrator;
use CommerceOptimizer\Checkout\Model\ProductRegistry;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Psr\Log\LoggerInterface;
class ProductAssignProcessor
{
    private ProductRegistry $productRegistry;
    private ProductHydrator $productHydrator;
    private JsonSerializer $serializer;
    private LoggerInterface $logger;

    public function __construct(
        ProductRegistry $productRegistry,
        ProductHydrator $productHydrator,
        JsonSerializer $serializer,
        LoggerInterface $logger

    ) {
        $this->productRegistry = $productRegistry;
        $this->productHydrator = $productHydrator;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }
    public function assignProduct(QuoteItem $item)
    {
        $needRecollect = false;
        if ($item->getProductView()) {
            $product = $this->productHydrator->hydrate(
                $this->serializer->unserialize($item->getProductView())
            );
        } else {
            $product = $this->productRegistry->getProduct($item->getSku());
        }
        if (1 == 0) {
            $item->isDeleted(true);
            $needRecollect = true;
        }
        if (!$item->isDeleted()) {
            $item->setProduct($product);
        }
        return $needRecollect;
    }
}
