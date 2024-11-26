<?php

namespace zaengle\neverstale\utilities;

use Craft;
use craft\base\Utility;
use craft\elements\Entry;
use zaengle\neverstale\models\ContentSubmission;

/**
 * Preview Submission utility
 */
class PreviewSubmission extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('neverstale', 'Preview Neverstale Submission');
    }

    static function id(): string
    {
        return 'preview-neverstale-submission';
    }

    public static function icon(): ?string
    {
        return 'wrench';
    }

    static function contentHtml(): string
    {
        $entryId = Craft::$app->request->getParam('entryId');
        $previewEntry = null;
        if ($entryId) {
            $previewEntry = Entry::find()->id($entryId)->one();
        }

        return Craft::$app->getView()->renderTemplate('neverstale/utilities/_previewSubmission', [
            'previewEntry' => $previewEntry,
            'entryMeta' => $previewEntry ? ContentSubmission::metaFromEntry($previewEntry) : null,
        ]);
    }
}
