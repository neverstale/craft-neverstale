<?php

namespace neverstale\craft\support;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

/**
 * Neverstale API Client
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
class ApiClient
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';
    public string $apiKey;
    public string $baseUri = 'https://app.neverstale.io/api/v1/';

    private Client $client;

    public function __construct(array $config = [])
    {
        $this->apiKey = $config['apiKey'];
        $this->baseUri = $config['baseUri'] ?? $this->baseUri;

        $this->client = new Client([
            'base_uri' => $this->baseUri,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }
    public function ping(): bool
    {
        // @todo implement when endpoint is available
        return true;
    }
    /**
     * @param array<string,mixed> $data
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function ingest(array $data, ?array $callbackConfig = []): ResponseInterface
    {
        return $this->client->post('ingest', [
            'json' => array_merge($data, $callbackConfig),
        ]);
    }

    /**
     * @param array<int,string> $customIds
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function batchDelete(array $customIds): ResponseInterface
    {
        return $this->client->delete('content/batch', [
            'json' => [
                'custom_ids' => $customIds,
            ],
        ]);
    }

    public function getByCustomId(string $customId): ResponseInterface
    {
        return $this->client->get("content/$customId");
    }

    public function ignoreFlag(string $flagId): ResponseInterface
    {
        return $this->client->post("flags/$flagId/ignore");
    }

    public function rescheduleFlag(string $flagId, \DateTime $expiredAt): ResponseInterface
    {
        return $this->client->post("flags/$flagId/reschedule",[
            'json' => [
                'expired_at' => $expiredAt->format('Y-m-d H:i:s'),
            ],
        ]);
    }
}
