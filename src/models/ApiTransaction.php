<?php

namespace zaengle\neverstale\models;
use craft\base\Model;
use zaengle\neverstale\enums\AnalysisStatus;

/**
 * API Transaction model
 *
 * @property-read string|null $neverstaleId
 * @property-read string|null $customId
 * @property-read string|null $channelId
 * @property-read AnalysisStatus $analysisStatus
 * @property-read string $transactionStatus
 */
class ApiTransaction extends Model
{
    public ?string $message;
    public ?string $event;
    public string $transactionStatus;
    public array $content = [];
    public \DateTime $createdAt;

    public function getNeverstaleId(): ?string
    {
        return $this->content['id'] ?? null;
    }
    public function getCustomId(): ?string
    {
        return $this->content['custom_id'] ?? null;
    }
    public function getAnalysisStatus(): AnalysisStatus
    {
        return AnalysisStatus::from($this->content['analysis_status'] ?? AnalysisStatus::UNKNOWN->value);
    }
    public function getChannelId(): ?string
    {
        return $this->content['channel_id'] ?? null;
    }

    public function getMessage(): ?string
    {
        return  $this->event ?? $this->message;
    }
    /**
     * Create a new ApiTransaction from a Neverstale response
     *
     * @param array $data
     * @return ApiTransaction
     */
    public static function fromIngestResponse(array $data): self
    {
        return new self([
            'message' => $data['message'] ?? null,
            'transactionStatus' => $data['status'] ?? 'success',
            'createdAt' => new \DateTime(),
            'content' => $data['data'],
        ]);
    }
    /**
     * Create a new ApiTransaction from a Neverstale webhook payload
     *
     * @param array $data
     * @return ApiTransaction
     */
    public static function fromWebhookPayload(array $data): self
    {
        return new self([
            'event' => $data['event'] ?? null,
            'transactionStatus' => $data['status'] ?? 'success',
            'createdAt' => new \DateTime(),
            'content' => $data['data']['content'],
        ]);
    }
}
