<?php

namespace CommerceOptimizer\Store\Model\Cache\Frontend\Decorator;

use CommerceOptimizer\Store\Model\Runtime;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\DB\Adapter\Pdo\Mysql;

class Store extends \Magento\Framework\Cache\Frontend\Decorator\Bare
{

    private Runtime $runtime;
    private array $systemIdentifiers = [];


    public function __construct(FrontendInterface $frontend, Runtime $runtime, array $systemIdentifiers)
    {
        parent::__construct($frontend);
        $this->runtime = $runtime;
        $this->systemIdentifiers = $systemIdentifiers;
    }

    private function resolveRuntimeIdentifier($identifier)
    {
        if (!isset($this->systemIdentifiers[$identifier])
            && (strtoupper(substr($identifier, 0, 16)) != Mysql::DDL_CACHE_PREFIX)
        ) {
            /** @var \Psr\Log\LoggerInterface $logger */
//            $logger = ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class);
//            $logger->critical($identifier);
            $identifier = $this->runtime->getStoreCodeFromRequest() . '[::::]' . $identifier;
        }
        return $identifier;
    }

    public function load($identifier)
    {
        return parent::load($this->resolveRuntimeIdentifier($identifier));
    }

    /**
     * @inheritDoc
     */
    public function save($data, $identifier, array $tags = [], $lifeTime = null)
    {
        return parent::save($data, $this->resolveRuntimeIdentifier($identifier), $tags, $lifeTime);
    }

    /**
     * @inheritdoc
     */
    public function remove($identifier)
    {
        return parent::remove($this->resolveRuntimeIdentifier($identifier));
    }
}
