<?php

namespace neverstale\craft\services;

use craft\elements\Entry as EntryElement;
use craft\helpers\ElementHelper;
use craft\models\Section;
use yii\base\Component;
use neverstale\craft\Plugin;

/**
 * Neverstale Entry Service
 *
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
class Entry extends Component
{
    /**
     * Should an Entry be submitted?
     */
    public function shouldIngest(EntryElement $entry): bool
    {
        if ($this->entryIsNonCanonical($entry)) {
            return false;
        }
        if ($this->hasUserEnabledSubmissionForEntry($entry)) {
            return true;
        }

        Plugin::log(Plugin::t("Entry #{id} is not submittable", [
            'id' => $entry->id,
        ]));

        return false;
    }

    public function entryIsNonCanonical(EntryElement $entry): bool
    {
        return ElementHelper::isDraftOrRevision($entry) || !$entry->getIsCanonical();
    }

    public function hasUserEnabledSubmissionForEntry(EntryElement $entry): bool
    {
        $contentEnabled = Plugin::getInstance()->getSettings()->enable;

        if (is_callable($contentEnabled)) {
            Plugin::log(Plugin::t("enable setting is a callable"));
            return $contentEnabled($entry);
        }
        Plugin::log(Plugin::t("enable setting is a bool"));
        if ($contentEnabled) {
            return $this->isEntryInEnabledSection($entry);
        }
        return false;
    }

    public function isEntryInEnabledSection(EntryElement $entry): bool
    {
        $enabledSections = collect(Plugin::getInstance()->getSettings()->getEnabledSections());

        if ($enabledSections->isEmpty()) {
            Plugin::log(Plugin::t("No enabled sections exist"));
            return false;
        }

        // Entry is not in an enabled section
        if ($enabledSections->doesntContain(fn(Section $section) => $section->id === $entry->sectionId)) {
            Plugin::log(Plugin::t("Entry #{id} is not in an enabled section", [
                'id' => $entry->id,
            ]));
            return false;
        }

        Plugin::log(Plugin::t("Entry #{id} is in an enabled section", [
            'id' => $entry->id,
        ]));

        return true;
    }
}
