<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
namespace CommerceOptimizer\Store\Model;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\StoreCookieManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

class Runtime
{
    private static array $runtimeStore;
    private StoreCookieManagerInterface $storeCookieManager;
    private Http $request;
    private ResourceConnection $resourceConnection;

    /**
     * @param StoreCookieManagerInterface $storeCookieManager
     * @param ResourceConnection $resourceConnection
     * @param Http $request
     */
    public function __construct(
        StoreCookieManagerInterface $storeCookieManager,
        ResourceConnection $resourceConnection,
        Http $request
    ) {
        $this->storeCookieManager = $storeCookieManager;
        $this->request = $request;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    private function getRuntimeStore(): array
    {
        if (!isset(self::$runtimeStore)) {
            self::$runtimeStore = $this->getStoreByCode($this->getStoreCodeFromRequest());
            if (empty(self::$runtimeStore)) {
                throw new NoSuchEntityException(__('Store not found'));
            }
        }
        return self::$runtimeStore;
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getRuntimeStoreId(): string
    {
        return $this->getRuntimeStore()['store_id'];
    }


    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getRuntimeWebsiteId(): string
    {
        return $this->getRuntimeStore()['website_id'];
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getRuntimeGroupId(): string
    {
        return $this->getRuntimeStore()['group_id'];
    }

    /**
     * @param string $code
     * @return array
     */
    private function getStoreByCode(string $code): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()->from(
            [$this->resourceConnection->getTableName('store')],
            ['code', 'store_id', 'website_id', 'group_id']
        )
            ->where('code IN (?)', [$code, 'admin'])
            ->where('is_active = 1');
        $storeViews = $connection->fetchAssoc($select);
        return $storeViews[$code] ?? $storeViews['admin'];
    }

    /**
     * @return string
     */
    private function getStoreCodeFromRequest(): string
    {
        $storeCode = 'admin';
        if ($this->request->getHeader('Store')) {
            $storeCode = $this->request->getHeader('Store');
        } elseif ($this->request->getParam(StoreManagerInterface::PARAM_NAME,
            $this->storeCookieManager->getStoreCodeFromCookie()
        )) {
            $storeCode = $this->request->getHeader('Store');
        }
        return $storeCode;
    }
}
