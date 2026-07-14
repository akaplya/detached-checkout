<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace CommerceOptimizer\Checkout\Plugin\QuoteGraphQl\Model\Resolver;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\QuoteGraphQl\Model\Resolver\RemoveItemFromCart as Subject;
use Psr\Log\LoggerInterface;

/**
 * Enforces bundle integrity on item removal.
 *
 * When any bundle component is removed, all sibling components in the same
 * items_group are removed from the DB before the resolver handles the primary item.
 */
class RemoveItemFromCart
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {
    }

    public function beforeResolve(
        Subject $subject,
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): array {
        $cartItemId = (int)($args['input']['cart_item_id'] ?? 0);
        if (!$cartItemId) {
            return [$field, $context, $info, $value, $args];
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('quote_item');

        $itemsGroup = $connection->fetchOne(
            $connection->select()
                ->from($table, ['items_group'])
                ->where('item_id = ?', $cartItemId)
        );

        if (!$itemsGroup || strpos($itemsGroup, ',') === false) {
            // Non-bundle or single-item group — nothing extra to do.
            return [$field, $context, $info, $value, $args];
        }

        $allIds = array_map('intval', explode(',', $itemsGroup));
        $siblingIds = array_filter($allIds, static fn(int $id): bool => $id !== $cartItemId);

        if (!empty($siblingIds)) {
            $deleted = $connection->delete($table, ['item_id IN (?)' => array_values($siblingIds)]);
            $this->logger->debug('Removed bundle siblings', [
                'primary_item_id' => $cartItemId,
                'siblings_removed' => $deleted,
                'sibling_ids' => array_values($siblingIds),
            ]);
        }

        return [$field, $context, $info, $value, $args];
    }
}
