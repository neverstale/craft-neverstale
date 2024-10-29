<?php

namespace zaengle\neverstale\models;

use Craft;
use craft\base\Model;
use zaengle\neverstale\enums\SubmissionStatus;

/**
 * Log Item model
 *
 * @property-read string|null $neverstaleId
 * @property-read string|null $customId
 * @property-read string|null $channelId
 * @property-read SubmissionStatus $submissionStatus
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
    public function getSubmissionStatus(): SubmissionStatus
    {
//        @todo confirm this is the correct key
        return SubmissionStatus::from($this->data['content_status'] ?? SubmissionStatus::Unknown->value);
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
            'data' => (array) $data['data'],
        ]);
    }
}
