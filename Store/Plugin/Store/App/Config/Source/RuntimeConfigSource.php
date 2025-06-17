<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
namespace CommerceOptimizer\Store\Plugin\Store\App\Config\Source;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\TableNotFoundException;
use CommerceOptimizer\Store\Model\Runtime;
use Magento\Store\Model\Store;

class RuntimeConfigSource extends \Magento\Store\App\Config\Source\RuntimeConfigSource
{
    /**
     * @var DeploymentConfig
     */
    private $deploymentConfig;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var AdapterInterface
     */
    private $connection;
    private Runtime $runtime;

    /**
     * @param DeploymentConfig $deploymentConfig
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        DeploymentConfig $deploymentConfig,
        ResourceConnection $resourceConnection,
        Runtime $runtime
    ) {
        parent::__construct($deploymentConfig, $resourceConnection);
        $this->deploymentConfig = $deploymentConfig;
        $this->resourceConnection = $resourceConnection;
        $this->runtime = $runtime;
    }

    /**
     * Return whole scopes config data from db.
     *
     * Ignore $path argument due to config source must return all config data
     *
     * @param string $path
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function get($path = '')
    {
        $data = [];
        try {
            if ($this->canUseDatabase()) {
                $data = [
                    'websites' => $this->getEntities(
                        'store_website',
                        'code',
                        'website_id IN (?)',
                        [$this->runtime->getRuntimeWebsiteId()]
                    ),
                    'groups' => $this->getEntities(
                        'store_group',
                        'group_id',
                        'group_id IN (?)',
                        [$this->runtime->getRuntimeGroupId()]
                    ),
                    'stores' => $this->getEntities(
                        'store',
                        'code',
                        'store_id IN (?)',
                        [Store::DEFAULT_STORE_ID, $this->runtime->getRuntimeStoreId()]
                    ),
                ];
            }
        } catch (TableNotFoundException $exception) {
            // database is empty or not setup
            $data = [];
        }

        return $data;
    }

    /**
     * Retrieve default connection
     *
     * @return AdapterInterface
     */
    private function getConnection()
    {
        if (null === $this->connection) {
            $this->connection = $this->resourceConnection->getConnection();
        }
        return $this->connection;
    }

    /**
     * Get entities from specified table in format [entityKeyField => [entity data], ...]
     *
     * @param string $table
     * @param string $keyField
     * @return array
     */
    private function getEntities(string $table, string $keyField, string $whereClosure, array $values)
    {
        $data = [];
        $tableName = $this->resourceConnection->getTableName($table);
        // Check if db table exists before fetch data

        $entities = $this->getConnection()->fetchAll(
            $this->getConnection()->select()->from($tableName)->where($whereClosure, $values)
        );

        foreach ($entities as $entity) {
            $data[$entity[$keyField]] = $entity;
        }

        return $data;
    }

    /**
     * Check whether db connection is available and can be used
     *
     * @return bool
     */
    private function canUseDatabase()
    {
        return $this->deploymentConfig->isDbAvailable();
    }
}
