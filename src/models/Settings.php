<?php

namespace neverstale\neverstale\models;

use Craft;
use craft\base\Model;
use craft\helpers\App;
use craft\models\Section;
use Illuminate\Support\Collection;
use neverstale\neverstale\Plugin;

class Settings extends Model
{
    public const SECTION_CONFIG_KEY = 'sections';

    public bool $enable = false;
    public string $apiKey = '';
    public string $webhookSecret = '';
    public string $webhookDomain = '';
    public array $enabledSectionIds = [];
    public bool $allowAllSections = false;
    public bool $debugLogging = false;

    // Bulk ingestion settings
    public int $bulkIngestMaxItems = 10000;
    public int $bulkIngestBatchSize = 100;
    public int $bulkIngestMaxConcurrency = 3;
    public int $bulkIngestRetryAttempts = 3;
    public bool $bulkIngestEnabled = true;
    public int $bulkIngestTimeoutMinutes = 60;

    /**
     * @inheritdoc
     */
    public function attributes(): array
    {
        return [
            'enable',
            'apiKey',
            'webhookSecret',
            'webhookDomain',
            'enabledSectionIds',
            'allowAllSections',
            'debugLogging',
            'bulkIngestMaxItems',
            'bulkIngestBatchSize',
            'bulkIngestMaxConcurrency',
            'bulkIngestRetryAttempts',
            'bulkIngestEnabled',
            'bulkIngestTimeoutMinutes',
        ];
    }

    public function getEnabledSections(): array
    {
        if ($this->sectionsIsOverridden()) {
            $configValue = Plugin::getInstance()->config->get(self::SECTION_CONFIG_KEY);
            // Value from config file is a callable, so use it to filter the sections
            if (is_callable($configValue)) {
                return $this->collectAllSections()->filter($configValue)->toArray();
            }

            // Value from config file is a static array of section handles
            return $this->collectAllSections()->filter(
                fn($section) => in_array($section->handle, $configValue, true)
            )->toArray();
        }
        // not overridden, but no specific sections are enabled
        if ($this->allowAllSections) {
            return $this->collectAllSections()->toArray();
        }

        // Use the value from the Craft CP
        return $this->collectAllSections()->filter(
            function (Section $section): bool {
                return in_array($section->id, $this->getEnabledSectionIds(), true);
            }
        )->toArray();
    }

    public function sectionsIsOverridden(): bool
    {
        return Plugin::getInstance()->config->isOverriddenByFile('sections');
    }

    /**
     * @return Collection<int,Section>
     */
    protected function collectAllSections(): Collection
    {
        return collect(Craft::$app->getEntries()->getAllSections());
    }

    /**
     * Get the enabled section IDs
     *
     * @return int[]
     */
    public function getEnabledSectionIds(): array
    {
        return collect($this->enabledSectionIds)->map(fn($id) => (int) $id)->toArray();
    }

    /**
     * Get bulk ingest max items with ENV override support
     */
    public function getBulkIngestMaxItems(): int
    {
        return (int) App::parseEnv($this->bulkIngestMaxItems, '$NEVERSTALE_BULK_INGEST_MAX_ITEMS');
    }

    /**
     * Get bulk ingest batch size with ENV override support
     */
    public function getBulkIngestBatchSize(): int
    {
        return (int) App::parseEnv($this->bulkIngestBatchSize, '$NEVERSTALE_BULK_INGEST_BATCH_SIZE');
    }

    /**
     * Get bulk ingest max concurrency with ENV override support
     */
    public function getBulkIngestMaxConcurrency(): int
    {
        return (int) App::parseEnv($this->bulkIngestMaxConcurrency, '$NEVERSTALE_BULK_INGEST_MAX_CONCURRENCY');
    }

    /**
     * Get bulk ingest retry attempts with ENV override support
     */
    public function getBulkIngestRetryAttempts(): int
    {
        return (int) App::parseEnv($this->bulkIngestRetryAttempts, '$NEVERSTALE_BULK_INGEST_RETRY_ATTEMPTS');
    }

    /**
     * Get bulk ingest timeout minutes with ENV override support
     */
    public function getBulkIngestTimeoutMinutes(): int
    {
        return (int) App::parseEnv($this->bulkIngestTimeoutMinutes, '$NEVERSTALE_BULK_INGEST_TIMEOUT_MINUTES');
    }

    /**
     * Get bulk ingest enabled with ENV override support
     */
    public function getBulkIngestEnabled(): bool
    {
        return (bool) App::parseEnv($this->bulkIngestEnabled, '$NEVERSTALE_BULK_INGEST_ENABLED');
    }

    /**
     * Get debug logging with ENV override support
     */
    public function getDebugLogging(): bool
    {
        return (bool) App::parseEnv($this->debugLogging, '$NEVERSTALE_DEBUG_LOGGING');
    }

    /**
     * Get webhook domain with ENV override support
     */
    public function getWebhookDomain(): string
    {
        return App::parseEnv($this->webhookDomain, '$NEVERSTALE_WEBHOOK_DOMAIN');
    }

    public function setAttributes($values, $safeOnly = true): void
    {
        // Handle the enable field - convert to boolean (lightswitch sends "1" or nothing)
        if (array_key_exists('enable', $values)) {
            $values['enable'] = ! empty($values['enable']);
        }

        // Handle enabledSectionIds - ensure it stays as an array
        if (array_key_exists('enabledSectionIds', $values)) {
            if (! is_array($values['enabledSectionIds'])) {
                $values['enabledSectionIds'] = [];
            }
        }

        parent::setAttributes($values, $safeOnly);
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['apiKey', 'webhookSecret', 'webhookDomain'], 'string'],
            [['webhookDomain'], 'url', 'defaultScheme' => 'https', 'skipOnEmpty' => true],
            [['enable', 'allowAllSections', 'debugLogging', 'bulkIngestEnabled'], 'boolean'],
            [['enabledSectionIds'], 'each', 'rule' => ['integer']],
            [['bulkIngestMaxItems', 'bulkIngestBatchSize', 'bulkIngestMaxConcurrency', 'bulkIngestRetryAttempts', 'bulkIngestTimeoutMinutes'], 'integer', 'min' => 1],
            [['bulkIngestMaxItems'], 'integer', 'max' => 100000],
            [['bulkIngestBatchSize'], 'integer', 'max' => 100],
            [['bulkIngestMaxConcurrency'], 'integer', 'max' => 10],
            [['bulkIngestRetryAttempts'], 'integer', 'max' => 10],
            [['bulkIngestTimeoutMinutes'], 'integer', 'max' => 720], // 12 hours max
        ];
    }

}
