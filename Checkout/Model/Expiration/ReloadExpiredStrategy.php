<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace CommerceOptimizer\Checkout\Model\Expiration;

use CommerceOptimizer\Checkout\Model\CompositeProductHydrator;
use CommerceOptimizer\Checkout\Model\Http\ProductProvider;
use CommerceOptimizer\Checkout\Model\ProductRegistry;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Psr\Log\LoggerInterface;

/**
 * "reload-expired" strategy: re-request the product from Commerce Optimizer,
 * refresh the item's stored view/price and push its expiration back out.
 */
class ReloadExpiredStrategy
{
    /**
     * @var ProductProvider
     */
    private ProductProvider $productProvider;

    /**
     * @var CompositeProductHydrator
     */
    private CompositeProductHydrator $productHydrator;

    /**
     * @var ProductRegistry
     */
    private ProductRegistry $productRegistry;

    /**
     * @var JsonSerializer
     */
    private JsonSerializer $jsonSerializer;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param ProductProvider $productProvider
     * @param CompositeProductHydrator $productHydrator
     * @param ProductRegistry $productRegistry
     * @param JsonSerializer $jsonSerializer
     * @param Config $config
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(
        ProductProvider $productProvider,
        CompositeProductHydrator $productHydrator,
        ProductRegistry $productRegistry,
        JsonSerializer $jsonSerializer,
        Config $config,
        ResourceConnection $resourceConnection,
        LoggerInterface $logger
    ) {
        $this->productProvider = $productProvider;
        $this->productHydrator = $productHydrator;
        $this->productRegistry = $productRegistry;
        $this->jsonSerializer = $jsonSerializer;
        $this->config = $config;
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }

    /**
     * Re-fetch the product from Commerce Optimizer and reset the item's expiration.
     *
     * Falls back to deleting the item if it can no longer be found in Commerce Optimizer.
     *
     * @param QuoteItem $item
     * @param int|null $websiteId
     * @return bool Whether recollection is needed
     */
    public function apply(QuoteItem $item, ?int $websiteId): bool
    {
        $sku = $item->getSku();
        $data = $this->productProvider->get([$sku])[$sku] ?? null;

        if ($data === null) {
            $this->logger->warning('Could not reload expired item from Commerce Optimizer, deleting it instead', [
                'sku' => $sku,
            ]);
            $item->isDeleted(true);

            return true;
        }

        $product = $this->productHydrator->hydrateSimple($data);
        $this->productRegistry->registerProduct($product);
        $item->setProduct($product);

        $newExpiresAt = $this->config->calculateExpiresAt($websiteId);
        $serializedView = $this->jsonSerializer->serialize($data);
        $item->setData('expires_at', $newExpiresAt);
        $item->setData('product_view', $serializedView);

        $itemId = (int)$item->getId();
        if ($itemId) {
            $connection = $this->resourceConnection->getConnection();
            $connection->update(
                $this->resourceConnection->getTableName('quote_item'),
                [
                    'expires_at' => $newExpiresAt,
                    'product_view' => $serializedView,
                ],
                ['item_id = ?' => $itemId]
            );
        }

        return true;
    }
}
