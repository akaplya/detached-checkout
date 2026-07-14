<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace CommerceOptimizer\Checkout\Plugin\QuoteGraphQl\Model\Cart;

use CommerceOptimizer\Checkout\Model\Expiration\WarningExpiredStrategy;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\QuoteGraphQl\Model\Cart\PlaceOrder as Subject;

/**
 * Blocks order placement while the cart contains items flagged as expired
 * under the "warning" expiration strategy.
 */
class PlaceOrder
{
    /**
     * @param Subject $subject
     * @param Quote $cart
     * @param string $maskedCartId
     * @param int $userId
     * @return array
     * @throws LocalizedException
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeExecute(Subject $subject, Quote $cart, string $maskedCartId, int $userId): array
    {
        foreach ($cart->getAllVisibleItems() as $item) {
            if (!$item->getData('has_error')) {
                continue;
            }

            foreach ($item->getErrorInfos() as $error) {
                if (($error['code'] ?? null) === WarningExpiredStrategy::ERROR_CODE_ITEM_EXPIRED) {
                    throw new LocalizedException(
                        __('Your cart contains expired items. Please refresh or remove them before placing the order.')
                    );
                }
            }
        }

        return [$cart, $maskedCartId, $userId];
    }
}
