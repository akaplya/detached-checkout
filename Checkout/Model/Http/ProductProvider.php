<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
namespace CommerceOptimizer\Checkout\Model\Http;

use CommerceOptimizer\QueryConnection\Model\AcoConfig;

class ProductProvider
{
    /**
     * @var AcoConfig
     */
    private $acoConfig;

    /**
     * @param AcoConfig $acoConfig
     */
    public function __construct(AcoConfig $acoConfig)
    {
        $this->acoConfig = $acoConfig;
    }

    public function get(array $skus): array
    {
        $settings = $this->acoConfig->getAllSettings();
        
        $httpClient = new \GuzzleHttp\Client([
            'base_uri' => $settings['base_uri']
        ]);

        $body = '{"query":"query getProduct($skus: [String]){products(skus: $skus) { __typename shortDescription name sku attributes {roles name value} ... on SimpleProductView {price {regular {amount {value}} final{amount{value}}}}}}","variables":{"skus":'
            . json_encode(array_values($skus))
            . '},"operationName":"getProduct"}';
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

        $response = $httpClient->request(
            'POST',
            $settings['ac_environment_id'] . '/graphql',
            $options
        );
        $output = [];
        $data = json_decode((string)$response->getBody(), true);
        foreach ($data['data']['products'] as $product) {
            $output[$product['sku']] = $product;
        }
        return $output;
    }
}
