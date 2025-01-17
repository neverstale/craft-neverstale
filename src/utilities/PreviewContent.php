<?php

namespace neverstale\craft\utilities;

use Craft;
use craft\base\Utility;
use craft\elements\Entry;
use neverstale\craft\models\IngestContent;

/**
 * Preview Content utility
 */
class PreviewContent extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('neverstale', 'Preview Neverstale Content');
    }

    public static function id(): string
    {
        return 'preview-neverstale-content';
    }

    public static function icon(): ?string
    {
        return 'wrench';
    }

    public static function contentHtml(): string
    {
        $entryId = Craft::$app->request->getParam('entryId');
        $previewEntry = $entryId ? null : Entry::find()->id($entryId)->one();

        return Craft::$app->getView()->renderTemplate('neverstale/utilities/_previewContent', [
            'previewEntry' => $previewEntry,
            'entryMeta' => $previewEntry ? IngestContent::metaFromEntry($previewEntry) : null,
        ]);
    }
}
