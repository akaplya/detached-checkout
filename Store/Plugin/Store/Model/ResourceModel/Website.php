<?php

namespace CommerceOptimizer\Store\Plugin\Store\Model\ResourceModel;

use CommerceOptimizer\Store\Model\Runtime;
use Magento\Framework\App\ObjectManager;

class Website extends \Magento\Store\Model\ResourceModel\Website
{

    /**
     * Read all information about websites.
     *
     * Convert information to next format:
     * [website_code => [website_data (website_id, code, name, etc...)]]
     *
     * @return array
     * @since 100.1.3
     */
    public function readAllWebsites()
    {
        /** @var Runtime $runtime */
        $runtime = ObjectManager::getInstance()->get(Runtime::class);
        $websites = [];
        $tableName = $this->getMainTable();
        $select = $this->getConnection()
            ->select()
            ->from($tableName)->where('website_id = ?', $runtime->getRuntimeWebsiteId());

        foreach ($this->getConnection()->fetchAll($select) as $websiteData) {
            $websites[$websiteData['code']] = $websiteData;
        }

        return $websites;
    }
}
