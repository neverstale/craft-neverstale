<?php

namespace zaengle\neverstale\traits;


use craft\helpers\UrlHelper;

use zaengle\neverstale\enums\AnalysisStatus;
use zaengle\neverstale\Plugin;
use zaengle\neverstale\records\Submission as SubmissionRecord;

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
 * @property-read SubmissionRecord|null $record
 */
trait HasNeverstaleContent
{
    use LogsApiTransactions, HasEntry;
    public ?string $neverstaleId = null;
    public ?string $analysisStatus = null;
    public int $flagCount = 0;

    /**
     * @var array<string>
     */
    public array $flagTypes = [];
    public ?\DateTime $nextFlagDate = null;


    public function getRecord(): ?SubmissionRecord
    {
        if ($this->id === null) {
            return null;
        }
        return SubmissionRecord::findOne($this->id);
    }

    public function setAnalysisStatus(AnalysisStatus $status): void
    {
        $this->analysisStatus = $status->value;
    }

    public function getAnalysisStatus(): AnalysisStatus
    {
        return AnalysisStatus::tryFrom($this->analysisStatus) ?? AnalysisStatus::UNKNOWN;
    }
    public function updateNeverStaleRecord(bool $isNew = true): void
    {
        Plugin::log("Saving submission $this->id", 'info');
        // Get the submission record
        if (!$isNew) {
            $record = $this->getRecord();

            if (!$record) {
                throw new \Exception('Invalid submission ID: ' . $this->id);
            }
        } else {
            $record = new SubmissionRecord();
            $record->id = $this->id;
        }

        $record->entryId = $this->entryId;
        $record->siteId = $this->siteId;
        $record->neverstaleId = $this->neverstaleId;
        $record->analysisStatus = $this->analysisStatus ?? AnalysisStatus::UNSENT->value;
        $record->flagCount = $this->flagCount;
        $record->flagTypes = $this->flagTypes;
        $record->nextFlagDate = $this->nextFlagDate;

        $record->save(false);
        $record->validate();
    }

    public function getWebhookUrl()
    {
        return UrlHelper::actionUrl("neverstale/webhooks");
    }

    public function forApi(): array
    {
        return Plugin::getInstance()->format->forApi($this)->toArray();
    }
}
