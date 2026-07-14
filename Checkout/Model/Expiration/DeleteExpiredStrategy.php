<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace CommerceOptimizer\Checkout\Model\Expiration;

use Magento\Quote\Model\Quote\Item as QuoteItem;

/**
 * "delete-expired" strategy: mark the expired item for removal from the quote.
 */
class DeleteExpiredStrategy
{
    /**
     * Mark the item as deleted so it is dropped on the next quote save.
     *
     * @param QuoteItem $item
     * @return bool Whether recollection is needed
     */
    public function apply(QuoteItem $item): bool
    {
        $item->isDeleted(true);

        return true;
    }
}
