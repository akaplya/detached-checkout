<?php

namespace CommerceOptimizer\Checkout\Plugin\Quote\Model\ResourceModel\Quote\Item;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Framework\App\ObjectManager;

class Collection extends \Magento\Quote\Model\ResourceModel\Quote\Item\Collection
{
    /**
     * @var ProductAssignProcessor
     */
    private $productAssignProcessor;

    protected function _construct()
    {
        parent::_construct();
        $this->productAssignProcessor = ObjectManager::getInstance()->get(ProductAssignProcessor::class);
    }

    protected function _assignProducts(): self
    {

        $needRecollect = false;
        foreach ($this as $item) {
            /** @var ProductInterface $product */
            if ($this->productAssignProcessor->assignProduct($item)) {
                $needRecollect = true;
            }
        };
        if ($needRecollect && $this->_quote) {
            $this->_quote->setTotalsCollectedFlag(false);
        }
        \Magento\Framework\Profiler::stop('QUOTE:' . __METHOD__);

        return $this;
    }

    /**
     * After load processing.
     *
     * @return $this
     */
    protected function _afterLoad(): self
    {
        \Magento\Framework\Model\ResourceModel\Db\VersionControl\Collection::_afterLoad();
        $productIds = [];
        foreach ($this as $item) {
            // Assign parent items
            if ($item->getParentItemId()) {
                $item->setParentItem($this->getItemById($item->getParentItemId()));
            }
            if ($this->_quote) {
                $item->setQuote($this->_quote);
            }
            // Collect quote products ids
            $productIds[] = (int)$item->getProductId();
        }
        $this->_productIds = array_merge($this->_productIds, $productIds);
        /**
         * Assign options and products
         */
        $this->_assignProducts();
        $this->resetItemsDataChanged();

        return $this;
    }
}
