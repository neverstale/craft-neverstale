<?php

namespace neverstale\craft\models;

use Craft;
use craft\base\Model;
use craft\helpers\Typecast;
use craft\models\Section;
use Illuminate\Support\Collection;
use neverstale\craft\Plugin;

/**
 * Neverstale Plugin Settings
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 *
 * @property-read Section[] $enabledSections
 */
class Settings extends Model
{
    public const SECTION_CONFIG_KEY = 'sections';
    /**
     * To enable / disable ingest per-entry, use a callable that returns a boolean
     * in the config file.
     * @var bool|callable
     */
    public mixed $enable = true;
    public string $apiKey = '';
    public string $webhookSecret = '';

    /** @var int[] */
    public array $enabledSectionIds = [];
    public bool $allowAllSections = false;

    /**
     * @inheritDoc
     * @return array<array>
     */
    public function defineRules(): array
    {
        return [
            [['apiKey', 'webhookSecret'], 'required'],
        ];
    }
    /**
     * Get the enabled sections
     *
     * @return Section[]
     */
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
            function(Section $section): bool {
                return in_array($section->id, $this->getEnabledSectionIds(), true);
            }
        )->toArray();
    }

    public function sectionsIsOverridden(): bool
    {
        return Plugin::getInstance()->config->isOverriddenByFile('sections');
    }

    /**
     * Get the enabled section IDs
     *
     * @return int[]
     */
    public function getEnabledSectionIds(): array
    {
        return collect($this->enabledSectionIds)->map(fn($id) => (int)$id)->toArray();
    }

    public function getAllowAllSections(): bool
    {
        return !$this->sectionsIsOverridden()
            &&
                $this->allowAllSections
            &&
                (
                    empty($this->enabledSectionIds)
                ||
                    count($this->enabledSectionIds) === $this->collectAllSections()->count()
                );
    }
    /**
     * @inheritDoc
     * @param array<string,mixed> $values
     * @param bool $safeOnly
     */
    public function setAttributes($values, $safeOnly = true): void
    {
        Typecast::properties(static::class, $values);

        if (isset($values['enabledSectionIds'])) {
            if (empty($values['enabledSectionIds'])) {
                $values['allowAllSections'] = false;
                $values['enabledSectionIds'] = [];
            }
            elseif ($values['enabledSectionIds'][0] === '*') {
                $values['allowAllSections'] = true;
                $values['enabledSectionIds'] = [];
            }
        }

        if (!isset($values['enable'])) {
            $values['enable'] = false;
        }

        parent::setAttributes($values, $safeOnly);
    }

    /**
     * @return Collection<int,Section>
     */
    protected function collectAllSections(): Collection
    {
        return collect(Craft::$app->getEntries()->getAllSections());
    }
}
