<?php

namespace zaengle\neverstale\utilities;

use Craft;
use craft\base\Utility;
use craft\elements\Entry;
use zaengle\neverstale\models\IngestContent;

/**
 * Preview Content utility
 */
class PreviewContent extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('neverstale', 'Preview Neverstale Content');
    }

    static function id(): string
    {
        return 'preview-neverstale-content';
    }

    public static function icon(): ?string
    {
        return 'wrench';
    }

    static function contentHtml(): string
    {
        $entryId = Craft::$app->request->getParam('entryId');
        $previewEntry = $entryId ? null : Entry::find()->id($entryId)->one();

        return Craft::$app->getView()->renderTemplate('neverstale/utilities/_previewContent', [
            'previewEntry' => $previewEntry,
            'entryMeta' => $previewEntry ? IngestContent::metaFromEntry($previewEntry) : null,
        ]);
    }
}
