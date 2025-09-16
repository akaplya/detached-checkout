<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
namespace CommerceOptimizer\QueryConnection\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class AcoConfig
{
    public const BASE_URI = 'base_uri';
    public const CHANNEL_ID = 'ac_channel_id';
    public const ENVIRONMENT_ID = 'ac_environment_id';
    public const PRICE_BOOK_ID = 'ac_price_book_id';
    public const SCOPE_LOCALE = 'ac_scope_locale';
    public const XML_PATH_BASE_URI = 'comopt/settings/aco/connection/base_uri';
    public const XML_PATH_AC_CHANNEL_ID = 'comopt/settings/aco/connection/ac_channel_id';
    public const XML_PATH_AC_ENVIRONMENT_ID = 'comopt/settings/aco/connection/ac_environment_id';
    public const XML_PATH_AC_PRICE_BOOK_ID = 'comopt/settings/aco/connection/ac_price_book_id';
    public const XML_PATH_AC_SCOPE_LOCALE = 'comopt/settings/aco/connection/ac_scope_locale';

    public static array $configMap = [
        self::BASE_URI => self::XML_PATH_BASE_URI,
        self::CHANNEL_ID => self::XML_PATH_AC_CHANNEL_ID,
        self::ENVIRONMENT_ID => self::XML_PATH_AC_ENVIRONMENT_ID,
        self::PRICE_BOOK_ID => self::XML_PATH_AC_PRICE_BOOK_ID,
        self::SCOPE_LOCALE => AcoConfig::XML_PATH_AC_SCOPE_LOCALE
    ];

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Get base URI
     *
     * @param string $scope
     * @param int $scopeId
     * @return string
     */
    public function getBaseUri(string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, int $scopeId = 0): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_BASE_URI, $scope, $scopeId);
    }

    /**
     * Get AC Channel ID
     *
     * @param string $scope
     * @param int $scopeId
     * @return string
     */
    public function getAcChannelId(string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, int $scopeId = 0): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_AC_CHANNEL_ID, $scope, $scopeId);
    }

    /**
     * Get AC Environment ID
     *
     * @param string $scope
     * @param int $scopeId
     * @return string
     */
    public function getAcEnvironmentId(string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, int $scopeId = 0): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_AC_ENVIRONMENT_ID, $scope, $scopeId);
    }

    /**
     * Get AC Price Book ID
     *
     * @param string $scope
     * @param int $scopeId
     * @return string
     */
    public function getAcPriceBookId(string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, int $scopeId = 0): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_AC_PRICE_BOOK_ID, $scope, $scopeId);
    }

    /**
     * Get AC Scope Locale
     *
     * @param string $scope
     * @param int $scopeId
     * @return string
     */
    public function getAcScopeLocale(string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, int $scopeId = 0): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_AC_SCOPE_LOCALE, $scope, $scopeId);
    }

    /**
     * Get all ACO connection settings as array
     *
     * @param string $scope
     * @param int $scopeId
     * @return array
     */
    public function getAllSettings(string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, int $scopeId = 0): array
    {
        return [
            self::BASE_URI => $this->getBaseUri($scope, $scopeId),
            self::CHANNEL_ID => $this->getAcChannelId($scope, $scopeId),
            self::ENVIRONMENT_ID => $this->getAcEnvironmentId($scope, $scopeId),
            self::PRICE_BOOK_ID => $this->getAcPriceBookId($scope, $scopeId),
            self::SCOPE_LOCALE => $this->getAcScopeLocale($scope, $scopeId)
        ];
    }
}
