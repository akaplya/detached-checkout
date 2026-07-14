<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
namespace CommerceOptimizer\Checkout\Model\Http;

use CommerceOptimizer\QueryConnection\Model\AcoConfig;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class ProductProvider
{
    private const SIMPLE_PRODUCT_QUERY = <<<'GRAPHQL'
query getProduct($skus: [String]) {
    products(skus: $skus) {
        __typename
        shortDescription
        name
        sku
        attributes { roles name value }
        ... on SimpleProductView {
            price {
                regular { amount { value currency } }
                final { amount { value currency } }
            }
        }
        ... on ComplexProductView {
            priceRange {
                minimum {
                    regular { amount { value currency } }
                    final { amount { value currency } }
                }
                maximum {
                    regular { amount { value currency } }
                    final { amount { value currency } }
                }
            }
            options {
                id
                title
                required
                values {
                    id
                    title
                    __typename
                    ... on ProductViewOptionValueConfiguration {
                        id
                        title
                    }
                    ... on ProductViewOptionValueProduct {
                        quantity
                        isDefault
                        product {
                            __typename
                            sku
                            name
                            shortDescription
                            attributes { roles name value }
                            ... on SimpleProductView {
                                price {
                                    regular { amount { value currency } }
                                    final { amount { value currency } }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
GRAPHQL;

    private const CONFIGURABLE_VARIANT_QUERY = <<<'GRAPHQL'
query getProductVariant($sku: String!, $optionIds: [String!]!) {
    productVariant(sku: $sku, optionIds: $optionIds) {
        __typename
        ... on SimpleProductView {
            sku
            name
            shortDescription
            attributes { roles name value }
            price {
                regular { amount { value currency } }
                final { amount { value currency } }
            }
        }
    }
}
GRAPHQL;

    /**
     * @var AcoConfig
     */
    private $acoConfig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Client|null
     */
    private $httpClient;

    /**
     * @param AcoConfig $acoConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        AcoConfig $acoConfig,
        LoggerInterface $logger
    ) {
        $this->acoConfig = $acoConfig;
        $this->logger = $logger;
    }

    /**
     * Get products by SKUs
     *
     * @param array $skus
     * @return array
     */
    public function get(array $skus): array
    {
        $query = self::SIMPLE_PRODUCT_QUERY;
        $variables = ['skus' => array_values($skus)];

        $data = $this->executeQuery($query, $variables, 'getProduct');
        
        $output = [];
        if (isset($data['data']['products'])) {
            foreach ($data['data']['products'] as $product) {
                $output[$product['sku']] = $product;
            }
        }
        return $output;
    }

    /**
     * Get configurable product variant by parent SKU and selected option IDs
     *
     * @param string $parentSku
     * @param array $optionIds
     * @return array|null
     */
    public function getVariant(string $parentSku, array $optionIds): ?array
    {
        $query = self::CONFIGURABLE_VARIANT_QUERY;
        $variables = [
            'sku' => $parentSku,
            'optionIds' => array_values($optionIds)
        ];

        $data = $this->executeQuery($query, $variables, 'getProductVariant');
        
        if (isset($data['data']['productVariant'])) {
            return $data['data']['productVariant'];
        }
        
        return null;
    }

    /**
     * Get configurable product with all its options and variants info
     *
     * @param string $sku
     * @return array|null
     */
    public function getConfigurable(string $sku): ?array
    {
        $products = $this->get([$sku]);
        return $products[$sku] ?? null;
    }

    /**
     * Execute GraphQL query against Commerce Optimizer
     *
     * @param string $query
     * @param array $variables
     * @param string $operationName
     * @return array
     */
    private function executeQuery(string $query, array $variables, string $operationName): array
    {
        $settings = $this->acoConfig->getAllSettings();
        $client = $this->getHttpClient($settings['base_uri']);

        $body = json_encode([
            'query' => $query,
            'variables' => $variables,
            'operationName' => $operationName
        ]);

        $options = [
            'headers' => [
                'Content-Type' => 'application/json',
                'ac-channel-id' => $settings['ac_channel_id'],
                'ac-environment-id' => $settings['ac_environment_id'],
                'ac-price-book-id' => $settings['ac_price_book_id'],
                'ac-scope-locale' => $settings['ac_scope_locale']
            ],
            'body' => $body
        ];

        try {
            $response = $client->request(
                'POST',
                $settings['ac_environment_id'] . '/graphql',
                $options
            );
            return json_decode((string)$response->getBody(), true);
        } catch (\Exception $e) {
            $this->logger->error('Commerce Optimizer API error', [
                'query' => $operationName,
                'variables' => $variables,
                'exception' => $e,
            ]);
            return [];
        }
    }

    /**
     * Get HTTP client instance
     *
     * @param string $baseUri
     * @return Client
     */
    private function getHttpClient(string $baseUri): Client
    {
        if ($this->httpClient === null) {
            $this->httpClient = new Client([
                'base_uri' => $baseUri
            ]);
        }
        return $this->httpClient;
    }
}
