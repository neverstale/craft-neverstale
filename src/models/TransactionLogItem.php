<?php

namespace neverstale\craft\models;

use craft\base\Model;
use neverstale\api\enums\AnalysisStatus;
use neverstale\api\exceptions\ApiException;
use neverstale\api\models\Content;
use neverstale\api\models\TransactionResult;

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
 * @property-read array $flags
 * @property-read string $transactionStatus
 */
class TransactionLogItem extends Model
{
    public ?string $message = null;
    public ?string $event = null;
    public ?string $transactionStatus = null;
    public ?Content $content;

    public function getNeverstaleId(): ?string
    {
        return $this->content?->id;
    }
    public function getCustomId(): ?string
    {
        return $this->content?->custom_id;
    }
    public function getAnalysisStatus(): AnalysisStatus
    {
        return $this->content?->analysis_status;
    }
    public function getDateExpired(): ?\DateTime
    {
        return $this->content?->expired_at ?? null;
    }
    public function getDateAnalyzed(): ?\DateTime
    {
        return $this->content?->analyzed_at ?? null;
    }

    public function getFlags(): array
    {
        return $this->content->flags ?? [];
    }

    public function getFlagCount(): int
    {
        return count($this->getFlags());
    }

    public function getChannelId(): ?string
    {
        return $this->content?->channel_id;
    }
    public static function fromContentResponse(TransactionResult $transaction, ?string $event = null): self
    {
        return new self([
            'event' => $event,
            'message' => $transaction->message ?? null,
            'transactionStatus' => $transaction->status ?? 'success',
            'content' => $transaction->data,
        ]);
    }
    public static function fromException(ApiException $e, ?string $event = null): self
    {
        return new self([
            'event' => $event,
            'message' => $e->getMessage(),
            'transactionStatus' => 'api-error',
        ]);
    }
    /**
     * Create a new TransactionLogItem from a Neverstale webhook payload
     *
     * @throws \Exception
     */
    public static function fromWebhookPayload(array $payload, ?string $message = 'Webhook received'): self
    {
        return new self([
            'event' => $payload['event'] ?? null,
            'transactionStatus' => $payload['status'] ?? 'success',
            'message' => $message,
            'content' => new Content($payload['data']['content']),
        ]);
    }
}
