<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
namespace CommerceOptimizer\Store\Model\Cache\Frontend\Decorator;

use CommerceOptimizer\Store\Model\Runtime;

class Store extends \Magento\Framework\Cache\Frontend\Decorator\Bare
{

    private Runtime $runtime;

    public function __construct(\Magento\Framework\Cache\FrontendInterface $frontend, Runtime $runtime)
    {
        parent::__construct($frontend);
        $this->runtime = $runtime;
    }

    private function resolveRuntimeIdentifier($identifier)
    {
        return $this->runtime->getStoreCodeFromRequest() . '[::::]' . $identifier;

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
