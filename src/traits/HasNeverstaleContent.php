<?php

namespace zaengle\neverstale\traits;


use craft\helpers\UrlHelper;

use zaengle\neverstale\enums\AnalysisStatus;
use zaengle\neverstale\Plugin;
use zaengle\neverstale\records\Content as ContentRecord;

/**
 * Has Neverstale Content Trait
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 *
 *
 * @property-read string $webhookUrl
 * @property-read ContentRecord|null $record
 */
trait HasNeverstaleContent
{
    use LogsApiTransactions, HasEntry;
    public ?string $neverstaleId = null;
    protected ?string $analysisStatus = null;

    public ?int $flagCount = null;
    public ?\DateTime $dateAnalyzed = null;
    public ?\DateTime $dateExpired = null;
    public function getRecord(): ?ContentRecord
    {
        if ($this->id === null) {
            return null;
        }
        return ContentRecord::findOne($this->id);
    }

    public function setAnalysisStatus(AnalysisStatus|string $status): void
    {
        if ($status instanceof AnalysisStatus) {
            $status = $status->value;
        }
        $this->analysisStatus = $status;
    }

    public function getAnalysisStatus(): AnalysisStatus
    {
        return AnalysisStatus::tryFrom($this->analysisStatus) ?? AnalysisStatus::UNKNOWN;
    }
    public function updateNeverStaleRecord(bool $isNew = true): void
    {
        Plugin::log("Saving content $this->id", 'info');
        // Get the content record
        if (!$isNew) {
            $record = $this->getRecord();

            if (!$record) {
                throw new \Exception('Invalid content ID: ' . $this->id);
            }
        } else {
            $record = new ContentRecord();
            $record->id = $this->id;
        }

        $record->entryId = $this->entryId;
        $record->siteId = $this->siteId;
        $record->neverstaleId = $this->neverstaleId;
        $record->analysisStatus = $this->analysisStatus ?? AnalysisStatus::UNSENT->value;
        $record->flagCount = $this->flagCount;
        $record->dateAnalyzed = $this->dateAnalyzed;
        $record->dateExpired = $this->dateExpired;

        $record->save(false);
        $record->validate();
    }

    public function isAnalyzed(): bool
    {
        return match ($this->getAnalysisStatus()) {
            AnalysisStatus::ANALYZED_FLAGGED, AnalysisStatus::ANALYZED_CLEAN => true,
            default => false,
        };
    }
    public function isFlagged(): bool
    {
        return match ($this->getAnalysisStatus()) {
            AnalysisStatus::ANALYZED_FLAGGED => true,
            default => false,
        };
    }

    public function getWebhookUrl()
    {
        return UrlHelper::actionUrl("neverstale/webhooks");
    }

    public function forApi(): array
    {
        return Plugin::getInstance()->format->forIngest($this)->toArray();
    }
}
