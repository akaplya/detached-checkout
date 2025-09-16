<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
namespace CommerceOptimizer\QueryConnection\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class AcoConfig
{
    private const XML_PATH_BASE_URI = 'comopt/settings/aco/connection/base_uri';
    private const XML_PATH_AC_CHANNEL_ID = 'comopt/settings/aco/connection/ac_channel_id';
    private const XML_PATH_AC_ENVIRONMENT_ID = 'comopt/settings/aco/connection/ac_environment_id';
    private const XML_PATH_AC_PRICE_BOOK_ID = 'comopt/settings/aco/connection/ac_price_book_id';
    private const XML_PATH_AC_SCOPE_LOCALE = 'comopt/settings/aco/connection/ac_scope_locale';

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
            'base_uri' => $this->getBaseUri($scope, $scopeId),
            'ac_channel_id' => $this->getAcChannelId($scope, $scopeId),
            'ac_environment_id' => $this->getAcEnvironmentId($scope, $scopeId),
            'ac_price_book_id' => $this->getAcPriceBookId($scope, $scopeId),
            'ac_scope_locale' => $this->getAcScopeLocale($scope, $scopeId)
        ];
    }
}

