<?php

namespace neverstale\neverstale\models;

use craft\base\Model;
use neverstale\neverstale\enums\AnalysisStatus;
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
    public ?Content $content = null;

    public function getNeverstaleId(): ?string
    {
        return $this->content?->id;
    }

    public function getCustomId(): ?string
    {
        return $this->content?->custom_id;
    }

    public function getAnalysisStatus(): ?AnalysisStatus
    {
        $apiStatus = $this->content?->analysis_status;
        
        if ($apiStatus === null) {
            return null;
        }
        
        // Convert API enum to plugin enum
        return $this->convertApiStatusToPluginStatus($apiStatus);
    }
    
    /**
     * Convert API AnalysisStatus to plugin AnalysisStatus
     */
    private function convertApiStatusToPluginStatus(\neverstale\api\enums\AnalysisStatus $apiStatus): AnalysisStatus
    {
        return match ($apiStatus) {
            \neverstale\api\enums\AnalysisStatus::UNSENT => AnalysisStatus::UNSENT,
            \neverstale\api\enums\AnalysisStatus::STALE => AnalysisStatus::ANALYZED_FLAGGED, // Map legacy STALE to ANALYZED_FLAGGED
            \neverstale\api\enums\AnalysisStatus::PENDING_INITIAL_ANALYSIS => AnalysisStatus::PENDING_INITIAL_ANALYSIS,
            \neverstale\api\enums\AnalysisStatus::PENDING_REANALYSIS => AnalysisStatus::PENDING_REANALYSIS,
            \neverstale\api\enums\AnalysisStatus::PENDING_TOKEN_AVAILABILITY => AnalysisStatus::PENDING_INITIAL_ANALYSIS, // Map to closest equivalent
            \neverstale\api\enums\AnalysisStatus::PROCESSING_REANALYSIS => AnalysisStatus::PROCESSING_REANALYSIS,
            \neverstale\api\enums\AnalysisStatus::PROCESSING_INITIAL_ANALYSIS => AnalysisStatus::PROCESSING_INITIAL_ANALYSIS,
            \neverstale\api\enums\AnalysisStatus::ANALYZED_CLEAN => AnalysisStatus::ANALYZED_CLEAN,
            \neverstale\api\enums\AnalysisStatus::ANALYZED_FLAGGED => AnalysisStatus::ANALYZED_FLAGGED,
            \neverstale\api\enums\AnalysisStatus::ANALYZED_ERROR => AnalysisStatus::ANALYZED_ERROR,
            \neverstale\api\enums\AnalysisStatus::API_ERROR => AnalysisStatus::API_ERROR,
            \neverstale\api\enums\AnalysisStatus::UNKNOWN => AnalysisStatus::UNKNOWN,
            default => AnalysisStatus::UNKNOWN,
        };
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