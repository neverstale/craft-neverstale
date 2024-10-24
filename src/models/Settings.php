<?php

namespace zaengle\neverstale\models;

use Craft;
use craft\base\Model;
use craft\helpers\Typecast;
use craft\models\Section;
use Illuminate\Support\Collection;
use zaengle\neverstale\Plugin;

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
     * To enable / disable submission per-entry, use a callable that returns a boolean
     * in the config file.
     * @var bool|callable
     */
    public mixed $enable = true;
    public string $apiKey = '';

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
            [['apiKey'], 'required'],
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
            $configValue = Plugin::getInstance()->config->getConfigFile(self::SECTION_CONFIG_KEY);
            // Value from config file is a callable, so use it to filter the sections
            if (is_callable($configValue)) {
                return $this->collectAllSections()->filter($configValue)->toArray();
            }
            // Value from config file is a static array of section handles
            return $this->collectAllSections()->filter(
                fn ($section) => in_array($section->handle, $configValue, true)
            )->toArray();
        }
        // not overridden, but no specific sections are enabled
        if ($this->allowAllSections) {
            return $this->collectAllSections()->toArray();
        }
        // Use the value from the Craft CP
        return $this->collectAllSections()->filter(
            fn ($section) => in_array($section->id, $this->enabledSectionIds, true)
        )->toArray();
    }

    public function sectionsIsOverridden(): bool
    {
        return Plugin::getInstance()->config->isOverriddenByFile('sections');
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

        if(isset($values['enabledSectionIds']) && $values['enabledSectionIds'][0] === '*') {
            $values['allowAllSections'] = true;
            $values['enabledSectionIds'] = [];
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
