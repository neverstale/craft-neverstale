<?php

namespace zaengle\neverstale\models;
use craft\base\Model;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use zaengle\neverstale\enums\AnalysisStatus;

/**
 * API Transaction model
 *
 * @property-read string|null $neverstaleId
 * @property-read string|null $customId
 * @property-read string|null $channelId
 * @property-read AnalysisStatus $analysisStatus
 * @property-read null|\DateTime $dateAnalyzed
 * @property-read null|\DateTime $dateExpired
 * @property-read int $flagCount
 * @property-read string $transactionStatus
 */
class ApiTransaction extends Model
{
    public ?string $message = null;
    public ?string $event = null;
    public ?string $transactionStatus = null;
    public array $content = [];

    public function getNeverstaleId(): ?string
    {
        return Arr::get($this->content, 'id');
    }
    public function getCustomId(): ?string
    {
        return Arr::get($this->content, 'custom_id');
    }
    public function getAnalysisStatus(): AnalysisStatus
    {
        $status = Arr::get($this->content, 'analysis_status');
        return AnalysisStatus::from($status ??  $this->transactionStatus ?? AnalysisStatus::UNKNOWN->value);
    }
    public function getDateExpired(): ?\DateTime
    {
        return Arr::has($this->content, 'expired_at')
            ? new \DateTime(Arr::get($this->content, 'expired_at'))
            : null;
    }
    public function getDateAnalyzed(): ?\DateTime
    {
        return Arr::has($this->content, 'analyzed_at')
            ? new \DateTime(Arr::get($this->content, 'analyzed_at'))
            : null;
    }

    public function getFlags(): array
    {
        return Arr::get($this->content, 'flags') ?? [];
    }

    public function getFlagCount(): int
    {
        return count($this->getFlags());
    }

    public function getChannelId(): ?string
    {
        return Arr::get($this->content, 'channel_id');
    }

    public static function fromContentResponse(array $data, ?string $event = null): self
    {
        return new self([
            'event' => $event,
            'message' => $data['message'] ?? null,
            'transactionStatus' => $data['status'] ?? 'success',
            'content' => $data['data'],
        ]);
    }

    public static function fromGuzzleException(GuzzleException $e, ?string $event = null): self
    {
        return new self([
            'event' => $event,
            'message' => $e->getMessage(),
            'transactionStatus' => 'api-error',
            'content' => [],
        ]);
    }
    /**
     * Create a new ApiTransaction from a Neverstale webhook payload
     *
     * @param array $data
     * @return ApiTransaction
     */
    public static function fromWebhookPayload(array $data, ?string $message = 'Webhook received'): self
    {
        return new self([
            'event' => $data['event'] ?? null,
            'transactionStatus' => $data['status'] ?? 'success',
            'message' => $message,
            'content' => $data['data']['content'],
        ]);
    }
}
