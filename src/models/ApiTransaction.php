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
    public array $data = [];
    public \DateTime $createdAt;

    public function getNeverstaleId(): ?string
    {
        return $this->data['id'] ?? null;
    }
    public function getCustomId(): ?string
    {
        return $this->data['custom_id'] ?? null;
    }
    public function getAnalysisStatus(): AnalysisStatus
    {
        return AnalysisStatus::from($this->data['analysis_status'] ?? AnalysisStatus::Unknown->value);
    }
    public function getChannelId(): ?string
    {
        return $this->data['channel_id'] ?? null;
    }

    public function getMessage(): ?string
    {
        return  $this->event ?? $this->message;
    }
    /**
     * Create a new ApiTransaction from a Neverstale response / webhook payload
     *
     * @param array $data
     * @return ApiTransaction
     */
    public static function fromNeverstaleData(array $data): self
    {
        return new self([
            'event' => $data['event'] ?? null,
            'message' => $data['message'] ?? null,
            'transactionStatus' => $data['status'] ?? 'success',
            'createdAt' => new \DateTime(),
            'data' => $data['data']['content'],
        ]);
    }
}
