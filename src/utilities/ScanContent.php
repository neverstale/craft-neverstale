<?php

namespace neverstale\craft\utilities;

use Craft;
use craft\base\Utility;
use craft\models\EntryType;

class ScanContent extends Utility
{
    public static function id(): string
    {
        return 'neverstale-scan-content';
    }

    public static function displayName(): string
    {
        return Craft::t('neverstale', 'Scan Neverstale Content');
    }

    public static function contentHtml(): string
    {
        $entryTypes = EntryType::find()->all();

        return Craft::$app->getView()->renderTemplate('neverstale/utilities/scan', [
            'entryTypes' => $entryTypes,
        ]);
    }
}
