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
        $tenantId = 'KZrr4s3gAAbumMGicqrvVo';
        $httpClient = new \GuzzleHttp\Client([
            'base_uri' => 'https://na1-sandbox.api.commerce.adobe.com'
        ]);

        $body = '{"query":"query getProduct($skus: [String]){products(skus: $skus) { __typename shortDescription name sku attributes {roles name value} ... on SimpleProductView {price {regular {amount {value}} final{amount{value}}}}}}","variables":{"skus":'
            . json_encode(array_values($skus))
            . '},"operationName":"getProduct"}';
        $options = [

            'headers' => [
                'Content-Type' => 'application/json',
                'ac-channel-id' => 'c0780d24-00b0-4236-bc31-ba586d3e7f0b',
                'ac-environment-id' => $tenantId,
                'ac-price-book-id' => 'west_coast_inc',
                'ac-scope-locale' => 'en-US'
            ],
            'body' => $body
        ];

        $response = $httpClient->request(
            'POST',
            $tenantId . '/graphql',
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
