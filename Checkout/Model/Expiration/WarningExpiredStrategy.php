<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace CommerceOptimizer\Checkout\Model\Expiration;

use Magento\Quote\Model\Quote\Item as QuoteItem;

/**
 * "warning" strategy: keep the item in the cart but flag it as errored so the
 * expiration surfaces via CartItemInterface.errors on every cart query, and
 * checkout can be blocked while the flag is present.
 */
class WarningExpiredStrategy
{
    /**
     * Error code surfaced as CartItemErrorType.ITEM_EXPIRED via the GraphQL enum data mapper.
     */
    public const ERROR_CODE_ITEM_EXPIRED = 100;

    /**
     * Flag the item with an expiration error. The item is left untouched otherwise.
     *
     * @param QuoteItem $item
     * @return bool Whether recollection is needed
     */
    public function apply(QuoteItem $item): bool
    {
        $item->addErrorInfo(
            'commerceoptimizer_checkout',
            self::ERROR_CODE_ITEM_EXPIRED,
            (string)__('This item has expired and must be refreshed or removed before checkout.')
        );

        return false;
    }
}
