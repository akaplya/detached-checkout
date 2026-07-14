<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace CommerceOptimizer\Checkout\Plugin\Quote\Model\ResourceModel\Quote\Item;

use CommerceOptimizer\Checkout\Model\CompositeProductHydrator;
use CommerceOptimizer\Checkout\Model\ProductRegistry;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Psr\Log\LoggerInterface;

/**
 * Processor for assigning products to quote items loaded from database
 */
class ProductAssignProcessor
{
    /**
     * @var ProductRegistry
     */
    private ProductRegistry $productRegistry;

    /**
     * @var CompositeProductHydrator
     */
    private CompositeProductHydrator $productHydrator;

    /**
     * @var JsonSerializer
     */
    private JsonSerializer $serializer;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param ProductRegistry $productRegistry
     * @param CompositeProductHydrator $productHydrator
     * @param JsonSerializer $serializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        ProductRegistry $productRegistry,
        CompositeProductHydrator $productHydrator,
        JsonSerializer $serializer,
        LoggerInterface $logger
    ) {
        $this->productRegistry = $productRegistry;
        $this->productHydrator = $productHydrator;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    /**
     * Assign product to quote item
     *
     * @param QuoteItem $item
     * @return bool Whether recollection is needed
     */
    public function assignProduct(QuoteItem $item): bool
    {
        $needRecollect = false;

        try {
            $product = $this->resolveProduct($item);

            if ($product !== null && !$item->isDeleted()) {
                $item->setProduct($product);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error assigning product to quote item', [
                'item_id' => $item->getId(),
                'sku' => $item->getSku(),
                'exception' => $e,
            ]);
            // Mark item for deletion if product cannot be resolved
            $item->isDeleted(true);
            $needRecollect = true;
        }

        return $needRecollect;
    }

    /**
     * Resolve product for the quote item
     *
     * @param QuoteItem $item
     * @return \Magento\Catalog\Model\Product|null
     */
    private function resolveProduct(QuoteItem $item)
    {
        $productView = $item->getProductView();

        if ($productView) {
            $viewData = $this->serializer->unserialize($productView);
            return $this->hydrateFromView($viewData, $item);
        }

        // Fallback to registry
        return $this->productRegistry->getProduct($item->getSku());
    }

    /**
     * Hydrate product from stored view data
     *
     * @param array $viewData
     * @param QuoteItem $item
     * @return \Magento\Catalog\Model\Product
     */
    private function hydrateFromView(array $viewData, QuoteItem $item)
    {
        // Check if this is a configurable product
        if ($this->productHydrator->isConfigurable($viewData)) {
            return $this->hydrateConfigurable($viewData, $item);
        }

        // Simple product - use composite hydrator
        return $this->productHydrator->hydrateSimple($viewData);
    }

    /**
     * Hydrate configurable product from view data
     *
     * @param array $parentViewData
     * @param QuoteItem $item
     * @return \Magento\Catalog\Model\Product
     */
    private function hydrateConfigurable(array $parentViewData, QuoteItem $item)
    {
        // For configurable products, we need both parent and child data
        // The child data should be stored in the item's options or related child items
        
        // Check if we have variant data stored
        $variantData = null;
        $selectedOptions = [];

        // Try to get variant data from custom options
        $buyRequest = $item->getBuyRequest();
        if ($buyRequest) {
            $selectedOptions = $buyRequest->getSelectedOptions() ?? [];
        }

        // If we have a child item, get its product view
        foreach ($item->getChildren() as $childItem) {
            $childView = $childItem->getProductView();
            if ($childView) {
                $variantData = $this->serializer->unserialize($childView);
                break;
            }
        }

        // If no variant data found, hydrate as simple product
        if ($variantData === null) {
            $this->logger->warning('No variant data found for configurable item, treating as simple', [
                'sku' => $item->getSku()
            ]);
            $parentViewData['__typename'] = 'SimpleProductView';
            unset($parentViewData['options']);
            return $this->productHydrator->hydrateSimple($parentViewData);
        }

        // Hydrate configurable product with variant
        [$parentProduct, $childProduct] = $this->productHydrator->hydrateConfigurable(
            $parentViewData,
            $variantData,
            $selectedOptions
        );

        // Register both products
        $this->productRegistry->registerProduct($parentProduct);
        $this->productRegistry->registerProduct($childProduct);

        return $parentProduct;
    }
}
