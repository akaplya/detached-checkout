<?php

namespace CommerceOptimizer\Store\Plugin\Store\Model\ResourceModel;

use CommerceOptimizer\Store\Model\Runtime;
use Magento\Framework\App\ObjectManager;

class Store extends \Magento\Store\Model\ResourceModel\Store
{
    /**
     * Read information about all stores
     *
     * @return array
     * @since 100.1.3
     */
    public function readAllStores()
    {
        /** @var Runtime $runtime */
        $runtime = ObjectManager::getInstance()->get(Runtime::class);
        $select = $this->getConnection()
            ->select()
            ->from($this->getTable($this->getMainTable()))
            ->where('store_id IN (?)', [$runtime->getRuntimeStoreId(), \Magento\Store\Model\Store::DEFAULT_STORE_ID]);
        return $this->getConnection()->fetchAll($select);
    }
}
