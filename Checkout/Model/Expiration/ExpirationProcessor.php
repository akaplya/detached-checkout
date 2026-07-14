<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace CommerceOptimizer\Checkout\Model\Expiration;

use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Detects expired Commerce Optimizer quote items and dispatches them to the
 * strategy configured for the item's website.
 */
class ExpirationProcessor
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var DeleteExpiredStrategy
     */
    private DeleteExpiredStrategy $deleteExpiredStrategy;

    /**
     * @var ReloadExpiredStrategy
     */
    private ReloadExpiredStrategy $reloadExpiredStrategy;

    /**
     * @var WarningExpiredStrategy
     */
    private WarningExpiredStrategy $warningExpiredStrategy;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param DeleteExpiredStrategy $deleteExpiredStrategy
     * @param ReloadExpiredStrategy $reloadExpiredStrategy
     * @param WarningExpiredStrategy $warningExpiredStrategy
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        StoreManagerInterface $storeManager,
        DeleteExpiredStrategy $deleteExpiredStrategy,
        ReloadExpiredStrategy $reloadExpiredStrategy,
        WarningExpiredStrategy $warningExpiredStrategy,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->deleteExpiredStrategy = $deleteExpiredStrategy;
        $this->reloadExpiredStrategy = $reloadExpiredStrategy;
        $this->warningExpiredStrategy = $warningExpiredStrategy;
        $this->logger = $logger;
    }

    /**
     * Process a single quote item, applying the configured strategy if it has expired.
     *
     * @param QuoteItem $item
     * @return bool Whether recollection is needed
     */
    public function process(QuoteItem $item): bool
    {
        if ($item->isDeleted()) {
            return false;
        }

        $expiresAt = $item->getData('expires_at');
        if (!$expiresAt || strtotime($expiresAt) > time()) {
            return false;
        }

        $websiteId = $this->resolveWebsiteId($item);
        $strategy = $this->config->getStrategy($websiteId);

        try {
            return match ($strategy) {
                Config::STRATEGY_RELOAD => $this->reloadExpiredStrategy->apply($item, $websiteId),
                Config::STRATEGY_WARNING => $this->warningExpiredStrategy->apply($item),
                default => $this->deleteExpiredStrategy->apply($item),
            };
        } catch (\Exception $e) {
            $this->logger->error('Error handling expired quote item, deleting it instead', [
                'item_id' => $item->getId(),
                'sku' => $item->getSku(),
                'strategy' => $strategy,
                'exception' => $e,
            ]);

            return $this->deleteExpiredStrategy->apply($item);
        }
    }

    /**
     * Resolve the website ID for the item's store, for scoped config lookups.
     *
     * @param QuoteItem $item
     * @return int|null
     */
    private function resolveWebsiteId(QuoteItem $item): ?int
    {
        $storeId = $item->getStoreId();
        if (!$storeId) {
            return null;
        }

        try {
            return (int)$this->storeManager->getStore($storeId)->getWebsiteId();
        } catch (\Exception $e) {
            $this->logger->debug('Could not resolve website ID for quote item, using default scope config', [
                'item_id' => $item->getId(),
                'store_id' => $storeId,
                'exception' => $e,
            ]);

            return null;
        }
    }
}
