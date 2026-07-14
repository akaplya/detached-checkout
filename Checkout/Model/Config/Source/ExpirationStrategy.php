<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace CommerceOptimizer\Checkout\Model\Config\Source;

use CommerceOptimizer\Checkout\Model\Expiration\Config;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Admin option source for the expired cart item handling strategy.
 */
class ExpirationStrategy implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::STRATEGY_DELETE, 'label' => __('Delete Expired Items')],
            ['value' => Config::STRATEGY_RELOAD, 'label' => __('Reload Expired Items from Commerce Optimizer')],
            ['value' => Config::STRATEGY_WARNING, 'label' => __('Warn and Block Checkout')],
        ];
    }
}
