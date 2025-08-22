<?php

namespace neverstale\neverstale\services;

use craft\elements\Entry as EntryElement;
use craft\helpers\ElementHelper;
use craft\models\Section;
use Exception;
use neverstale\neverstale\Plugin;
use yii\base\Component;

/**
 * Neverstale Entry Service
 *
 * Handles entry validation, filtering, and submission logic.
 * Determines which entries should be processed by the Neverstale API
 * based on plugin configuration and entry characteristics.
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   2.0.0
 * @see     https://github.com/neverstale/craft-neverstale
 */
class Entry extends Component
{
    /**
     * Determine if an Entry should be submitted to Neverstale
     *
     * Performs comprehensive validation to determine if an entry
     * meets all criteria for Neverstale processing.
     *
     * @param  EntryElement  $entry  Entry to validate
     * @return bool True if entry should be ingested
     */
    public function shouldIngest(EntryElement $entry): bool
    {
        Plugin::debug("shouldIngest check started for entry #{$entry->id}");

        // Skip non-canonical entries (drafts, revisions, etc.)
        if ($this->entryIsNonCanonical($entry)) {
            Plugin::debug("Entry #{$entry->id} is non-canonical (draft/revision) - skipping");

            return false;
        }

        Plugin::debug("Entry #{$entry->id} is canonical - check passed");

        // Skip disabled entries
        if (! $entry->getEnabledForSite()) {
            Plugin::debug("Entry #{$entry->id} is disabled for site - skipping");

            return false;
        }

        Plugin::debug("Entry #{$entry->id} is enabled for site - check passed");

        // Check if user has enabled submission for this entry
        if ($this->hasUserEnabledSubmissionForEntry($entry)) {
            Plugin::info("Entry #{$entry->id} is eligible for submission");

            return true;
        }

        Plugin::debug("Entry #{$entry->id} is not submittable - failed user enabled submission check");

        return false;
    }

    /**
     * Check if entry is non-canonical (draft, revision, etc.)
     *
     * Non-canonical entries should not be processed as they
     * represent temporary or historical versions.
     *
     * @param  EntryElement  $entry  Entry to check
     * @return bool True if entry is non-canonical
     */
    public function entryIsNonCanonical(EntryElement $entry): bool
    {
        return ElementHelper::isDraftOrRevision($entry) || ! $entry->getIsCanonical();
    }

    /**
     * Check if user has enabled submission for this entry
     *
     * Evaluates plugin settings to determine if the entry
     * should be processed based on global and section-specific settings.
     *
     * @param  EntryElement  $entry  Entry to check
     * @return bool True if submission is enabled
     */
    public function hasUserEnabledSubmissionForEntry(EntryElement $entry): bool
    {
        $settings = Plugin::getInstance()->getSettings();
        $contentEnabled = $settings->enable;

        Plugin::debug("Checking if submission enabled for entry #{$entry->id}");
        Plugin::debug("Plugin enable setting: ".($contentEnabled ? 'true' : 'false'));

        // If enable setting is a callable, use it for custom logic
        if (is_callable($contentEnabled)) {
            Plugin::debug("Enable setting is callable, evaluating for entry #{$entry->id}");

            try {
                $result = $contentEnabled($entry);
                Plugin::debug("Callable returned: ".($result ? 'true' : 'false'));

                return $result;
            } catch (Exception $e) {
                Plugin::error("Error in custom enable callable: {$e->getMessage()}");

                return false;
            }
        }

        // If globally enabled, check section-specific settings
        if ($contentEnabled) {
            Plugin::debug("Plugin is globally enabled, checking section settings...");

            return $this->isEntryInEnabledSection($entry);
        }

        Plugin::debug("Plugin is globally disabled");

        return false;
    }

    /**
     * Check if entry belongs to an enabled section
     *
     * Validates that the entry's section is configured for
     * Neverstale processing in the plugin settings.
     *
     * @param  EntryElement  $entry  Entry to check
     * @return bool True if entry is in enabled section
     */
    public function isEntryInEnabledSection(EntryElement $entry): bool
    {
        $settings = Plugin::getInstance()->getSettings();

        Plugin::debug("Checking if entry #{$entry->id} is in enabled section");
        Plugin::debug("Allow all sections: ".($settings->allowAllSections ? 'true' : 'false'));

        $enabledSections = collect($settings->getEnabledSections());

        Plugin::debug("Number of enabled sections: ".$enabledSections->count());

        if ($enabledSections->isEmpty()) {
            Plugin::debug("No enabled sections configured - entry not eligible");

            return false;
        }

        // Log the enabled section IDs
        $enabledSectionIds = $enabledSections->pluck('id')->toArray();
        Plugin::debug("Enabled section IDs: ".json_encode($enabledSectionIds));
        Plugin::debug("Entry section ID: {$entry->sectionId}");

        // Check if entry section is in enabled sections list
        $isEnabled = $enabledSections->contains(function (Section $section) use ($entry) {
            return $section->id === $entry->sectionId;
        });

        if (! $isEnabled) {
            Plugin::debug("Entry #{$entry->id} section (#{$entry->sectionId}) is NOT in enabled sections");

            return false;
        }

        Plugin::debug("Entry #{$entry->id} IS in enabled section (#{$entry->sectionId})");

        return true;
    }
}
