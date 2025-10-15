<?php

namespace neverstale\neverstale\traits;

use Craft;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use DateTime;
use Exception;
use neverstale\neverstale\elements\Flag;
use neverstale\neverstale\enums\AnalysisStatus;
use neverstale\neverstale\models\CustomId;
use neverstale\neverstale\Plugin;
use neverstale\neverstale\records\Content as ContentRecord;

/**
 * Has Neverstale Content Trait
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   1.0.0
 * @see     https://github.com/zaengle/craft-neverstale
 *
 *
 * @property-read string             $webhookUrl
 * @property-read string|null        $customId
 * @property-read ContentRecord|null $record
 */
trait HasContent
{
    use LogsTransactions, HasEntry;

    public ?string $neverstaleId = null;
    public ?int $flagCount = null;
    public ?DateTime $dateAnalyzed = null;
    public ?DateTime $dateExpired = null;
    public int $lastWebhookVersion = 0;
    protected ?string $analysisStatus = null;
    protected ?CustomId $customId = null;

    public function updateNeverStaleRecord(bool $isNew = true): void
    {
        Plugin::debug("HasContent::updateNeverStaleRecord() called for element ID: ".($this->id ?? 'null').", isNew: ".($isNew ? 'true' : 'false'));

        // Get the content record
        if (! $isNew) {
            Plugin::debug("Updating existing record for element ID: {$this->id}");
            $record = $this->getRecord();

            if (! $record) {
                Plugin::error("Invalid content ID during update: {$this->id}");
                throw new Exception('Invalid content ID: '.$this->id);
            }
            Plugin::debug("Found existing ContentRecord with ID: {$record->id}");
        } else {
            Plugin::debug("Creating new ContentRecord for element ID: ".($this->id ?? 'null'));
            $record = new ContentRecord();
            $record->id = $this->id;
            Plugin::debug("New ContentRecord instantiated with ID: ".($record->id ?? 'null'));
        }

        // Set record attributes
        $record->entryId = $this->entryId;
        $record->siteId = $this->siteId;
        $record->neverstaleId = $this->neverstaleId;
        $record->analysisStatus = $this->analysisStatus ?? AnalysisStatus::UNSENT->value;
        $record->flagCount = $this->flagCount;
        $record->dateAnalyzed = $this->dateAnalyzed;
        $record->dateExpired = $this->dateExpired;
        $record->lastWebhookVersion = $this->lastWebhookVersion;

        Plugin::debug("ContentRecord attributes set: entryId={$record->entryId}, siteId={$record->siteId}, analysisStatus={$record->analysisStatus}");

        Plugin::debug("Attempting to save ContentRecord...");
        $saveResult = $record->save(false);
        Plugin::debug("ContentRecord save() returned: ".($saveResult ? 'true' : 'false'));

        if (! $saveResult) {
            Plugin::error("ContentRecord save failed. Errors: ".json_encode($record->getErrors()));
            Plugin::debug("ContentRecord attributes at time of failure: ".json_encode($record->getAttributes()));
        } else {
            Plugin::debug("ContentRecord saved successfully with ID: {$record->id}");
        }

        Plugin::debug("Running ContentRecord validation...");
        $validationResult = $record->validate();
        Plugin::debug("ContentRecord validation returned: ".($validationResult ? 'true' : 'false'));

        if (! $validationResult) {
            Plugin::warning("ContentRecord validation failed. Errors: ".json_encode($record->getErrors()));
        }

        Plugin::debug("HasContent::updateNeverStaleRecord() completed");
    }

    public function getRecord(): ?ContentRecord
    {
        if ($this->id === null) {
            return null;
        }

        return ContentRecord::findOne($this->id);
    }

    public function isAnalyzed(): bool
    {
        return match ($this->getAnalysisStatus()) {
            AnalysisStatus::ANALYZED_FLAGGED, AnalysisStatus::ANALYZED_CLEAN => true,
            default => false,
        };
    }

    public function getAnalysisStatus(): AnalysisStatus
    {
        // If the property isn't set, try to load it from the database record
        if (! $this->analysisStatus) {
            $record = $this->getRecord();
            if ($record && $record->analysisStatus) {
                $this->analysisStatus = $record->analysisStatus;
            } else {
                return AnalysisStatus::UNSENT;
            }
        }

        return AnalysisStatus::tryFrom($this->analysisStatus) ?? AnalysisStatus::UNKNOWN;
    }

    public function setAnalysisStatus(AnalysisStatus|string $status): void
    {
        if ($status instanceof AnalysisStatus) {
            $status = $status->value;
        }
        $this->analysisStatus = $status;
    }

    public function isFlagged(): bool
    {
        return $this->getAnalysisStatus() === AnalysisStatus::ANALYZED_FLAGGED;
    }

    public function isUnsent(): bool
    {
        return $this->getAnalysisStatus() === AnalysisStatus::UNSENT;
    }

    public function isPendingProcessingOrStale(): bool
    {
        return match ($this->getAnalysisStatus()) {
            AnalysisStatus::UNSENT,
            AnalysisStatus::PENDING_INITIAL_ANALYSIS,
            AnalysisStatus::PENDING_REANALYSIS,
            AnalysisStatus::PROCESSING_REANALYSIS,
            AnalysisStatus::PROCESSING_INITIAL_ANALYSIS => true,
            default => false,
        };
    }

    public function getWebhookUrl(): string
    {
        $settings = Plugin::getInstance()->getSettings();

        // Use custom webhook domain if configured
        $configValue = Plugin::getInstance()->config->get('webhookDomain');
        $webhookDomain = $configValue !== null ? (string) $configValue : App::parseEnv($settings->webhookDomain, '$NEVERSTALE_WEBHOOK_DOMAIN');

        if (! empty($webhookDomain)) {
            $customUrl = rtrim($webhookDomain, '/');

            // If it's a full URL, append the action path
            if (parse_url($customUrl, PHP_URL_SCHEME)) {
                return "{$customUrl}/index.php?p=actions/neverstale/webhooks";
            }

            // If it's just a domain, add https and action path
            return "https://{$customUrl}/index.php?p=actions/neverstale/webhooks";
        }

        // Default behavior - use current site's domain
        return UrlHelper::actionUrl("neverstale/webhooks");
    }

    public function forApi(): array
    {
        return Plugin::getInstance()->format->forIngest($this)->toArray();
    }

    public function getCustomId(): string
    {
        if ($this->customId === null) {
            $this->customId = CustomId::fromContent($this);
        }

        return $this->customId->toString();
    }

    /**
     * Get all flags for this content
     *
     * @return Flag[]
     */
    public function getFlags(): array
    {
        if (! $this->id) {
            return [];
        }

        return Flag::find()
            ->contentId($this->id)
            ->all();
    }

    /**
     * Get active (non-ignored) flags for this content
     *
     * @return Flag[]
     */
    public function getActiveFlags(): array
    {
        if (! $this->id) {
            return [];
        }

        return Flag::find()
            ->contentId($this->id)
            ->active(true)
            ->all();
    }

    /**
     * Get the count of active flags
     *
     * @return int
     */
    public function getActiveFlagCount(): int
    {
        if (! $this->id) {
            return 0;
        }

        return Flag::find()
            ->contentId($this->id)
            ->active(true)
            ->count();
    }
}
