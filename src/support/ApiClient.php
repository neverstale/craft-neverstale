<?php

namespace zaengle\neverstale\support;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

class ApiClient
{

    public string $apiKey;
    public string $apiSecret;

    public string $baseUri = 'https://app.neverstale.io/api/v1';

    private Client $client;

    public function __construct(array $config = [])
    {
        $this->apiKey = $config['apiKey'];
        $this->apiSecret = $config['apiSecret'];
        $this->baseUri = $config['baseUri'] ?? $this->baseUri;
        $this->client = new Client([
            'base_uri' => $this->baseUri,
            'headers' => [
//                @todo implement authentication for requests
            ]
        ]);
    }

    /**
     * @param string|int $customId
     * @param array<string,mixed> $data
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function upsert(string|int $customId, array $data): Response
    {
        return $this->client->post('/upsert', [
            'json' => [
                'customId' => $customId,
                'data' => $data,
            ],
        ]);
    }
}
