<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
namespace CommerceOptimizer\Checkout\Model\Http;

class ProductProvider
{
    public function get(array $skus): array
    {
        $httpClient = new \GuzzleHttp\Client([
            'base_uri' => 'https://catalog-service.adobe.io'
        ]);

        $body = '{"query":"query getProduct($skus: [String]){products(skus: $skus) { __typename shortDescription name sku attributes {roles name value} ... on SimpleProductView {price {regular {amount {value}} final{amount{value}}}}}}","variables":{"skus":'
            . json_encode(array_values($skus))
            . '},"operationName":"getProduct"}';
        $options = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Magento-Environment-Id' => 'f38a0de0-764b-41fa-bd2c-5bc2f3c7b39a',
                'Magento-Store-Code' => 'main_website_store',
                'Magento-Store-View-Code' => 'default',
                'Magento-Website-Code' => 'base',
                'x-api-key' => '4dfa19c9fe6f4cccade55cc5b3da94f7'
            ],
            'body' => $body
        ];

        $response = $httpClient->request(
            'POST',
            '/graphql',
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
