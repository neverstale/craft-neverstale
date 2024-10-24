<?php

namespace zaengle\neverstale\services;

use craft\elements\Entry;
use craft\helpers\ElementHelper;
use craft\models\Section;
use yii\base\Component;
use zaengle\neverstale\Plugin;

/**
 * Neverstale Element Service
 *
 * Handles determining if an Element should be submitted to Neverstale
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
class Element extends Component
{
    /**
     * Should this Element be submitted?
     */
    public function isSubmittable(Entry $entry): bool
    {
        if (
            // Entry is a draft or revision
                ElementHelper::isDraftOrRevision($entry)
            ||
            // Entry is not canonical
                !$entry->getIsCanonical()
        ) {
            Plugin::log(Plugin::t("Entry #{id} is not submittable because it is either a draft, a revision or not canonical", [
                'id' => $entry->id,
            ]));
            return false;
        }

        $submissionEnabled = Plugin::getInstance()->getSettings()->enable;

        if (is_callable($submissionEnabled)) {
            $result = $submissionEnabled($entry);

            Plugin::log(Plugin::t("enabled callable determined that Entry #{id} is {submittable}", [
                'id' => $entry->id,
                'submittable' => $result ? 'submittable' : 'not submittable',
            ]));

            return $submissionEnabled();
        }

        if (!$submissionEnabled === false) {
            Plugin::log(Plugin::t("Entry #{id} is not submittable because submission is globally disabled", [
                'id' => $entry->id,
            ]));
            return false;
        }

        $enabledSections = collect(Plugin::getInstance()->getSettings()->getEnabledSections());

        if ($enabledSections->isEmpty()) {
            Plugin::log(Plugin::t("Entry #{id} is not submittable because no enabled sections exist", [
                'id' => $entry->id,
            ]));
            return false;
        }

        // Entry is not in an enabled section
        if ($enabledSections->doesntContain(fn(Section $section) => $section->id === $entry->sectionId)) {
            Plugin::log(Plugin::t("Not submitting entry #{id} because it is not in an enabled section", [
                'id' => $entry->id,
            ]));
            return false;
        }

        Plugin::log(Plugin::t("Entry #{id} is submittable", [
            'id' => $entry->id,
        ]));
        return true;
    }
}
