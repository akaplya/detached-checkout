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
use Magento\QuoteGraphQl\Model\Resolver\UpdateCartItems as Subject;
use Psr\Log\LoggerInterface;

/**
 * Enforces bundle integrity on cart item updates.
 *
 * A bundle component's quantity is fixed by the bundle selection and must not be changed
 * independently of the group. Requests to update the quantity of a bundle component are
 * rejected (the item's persisted quantity is left untouched) and reported as an error;
 * shoppers who want a different bundle must remove it via removeItemFromCart and re-add it.
 */
class UpdateCartItems
{
    /**
     * @var array
     */
    private array $blockedItemErrors = [];

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
        $this->blockedItemErrors = [];

        $cartItems = $args['input']['cart_items'] ?? [];
        if (empty($cartItems)) {
            return [$field, $context, $info, $value, $args];
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('quote_item');

        foreach ($cartItems as $key => $cartItem) {
            $cartItemId = $this->resolveItemId($cartItem);
            if (!$cartItemId) {
                continue;
            }

            $row = $connection->fetchRow(
                $connection->select()
                    ->from($table, ['sku', 'qty', 'items_group'])
                    ->where('item_id = ?', $cartItemId)
            );

            $itemsGroup = $row['items_group'] ?? null;
            if (!$itemsGroup || strpos($itemsGroup, ',') === false) {
                // Non-bundle or single-item group — quantity changes are unrestricted.
                continue;
            }

            // Bundle component: quantity is fixed by the bundle selection. Neutralise the
            // requested change (keep the persisted quantity) and surface an error instead
            // of applying it.
            $cartItems[$key]['quantity'] = (float)$row['qty'];

            $this->blockedItemErrors[] = [
                'message' => __(
                    'The quantity of "%1" cannot be changed because it is part of a bundle. '
                    . 'Remove the item to delete the entire bundle.',
                    $row['sku']
                ),
                'code' => 'INVALID_PARAMETER_VALUE',
            ];

            $this->logger->debug('Blocked quantity change on bundle component item', [
                'item_id' => $cartItemId,
                'sku' => $row['sku'],
                'items_group' => $itemsGroup,
            ]);
        }

        $args['input']['cart_items'] = $cartItems;

        return [$field, $context, $info, $value, $args];
    }

    /**
     * @param Subject $subject
     * @param array $result
     * @return array
     */
    public function afterResolve(Subject $subject, array $result): array
    {
        if (!empty($this->blockedItemErrors)) {
            $result['errors'] = array_merge($result['errors'] ?? [], $this->blockedItemErrors);
        }

        return $result;
    }

    private function resolveItemId(array $cartItem): int
    {
        if (!empty($cartItem['cart_item_id'])) {
            return (int)$cartItem['cart_item_id'];
        }
        if (!empty($cartItem['cart_item_uid'])) {
            return (int)base64_decode((string)$cartItem['cart_item_uid']);
        }
        return 0;
    }
}
