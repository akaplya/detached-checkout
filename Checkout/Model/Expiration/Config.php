<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace CommerceOptimizer\Checkout\Model\Expiration;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\ScopeInterface;

/**
 * Reads per-website cart item expiration settings and computes expiration timestamps.
 */
class Config
{
    public const STRATEGY_DELETE = 'delete-expired';
    public const STRATEGY_RELOAD = 'reload-expired';
    public const STRATEGY_WARNING = 'warning';

    private const XML_PATH_LENGTH = 'checkout/cart_expiration/length';
    private const XML_PATH_STRATEGY = 'checkout/cart_expiration/strategy';

    private const DEFAULT_LENGTH_MINUTES = 30;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param DateTime $dateTime
     */
    public function __construct(ScopeConfigInterface $scopeConfig, DateTime $dateTime)
    {
        $this->scopeConfig = $scopeConfig;
        $this->dateTime = $dateTime;
    }

    /**
     * Get the configured expiration length, in minutes, for the given website.
     *
     * @param int|null $websiteId
     * @return int
     */
    public function getLengthMinutes(?int $websiteId = null): int
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_LENGTH, ScopeInterface::SCOPE_WEBSITE, $websiteId);

        return $value !== null && $value !== '' ? (int)$value : self::DEFAULT_LENGTH_MINUTES;
    }

    /**
     * Get the configured expired-item strategy for the given website.
     *
     * @param int|null $websiteId
     * @return string
     */
    public function getStrategy(?int $websiteId = null): string
    {
        $value = (string)$this->scopeConfig->getValue(self::XML_PATH_STRATEGY, ScopeInterface::SCOPE_WEBSITE, $websiteId);

        return in_array($value, [self::STRATEGY_DELETE, self::STRATEGY_RELOAD, self::STRATEGY_WARNING], true)
            ? $value
            : self::STRATEGY_DELETE;
    }

    /**
     * Compute the expiration timestamp (GMT) for a new or refreshed item on the given website.
     *
     * @param int|null $websiteId
     * @return string
     */
    public function calculateExpiresAt(?int $websiteId = null): string
    {
        return $this->dateTime->gmtDate(null, time() + $this->getLengthMinutes($websiteId) * 60);
    }
}
