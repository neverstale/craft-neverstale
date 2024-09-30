<?php

namespace zaengle\neverstale\utilities;

use Craft;
use craft\base\Utility;

/**
 * Scan utility
 */
class ScanUtility extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('neverstale', 'Neverstale: Scan site');
    }

    static function id(): string
    {
        return 'neverscale-scan';
    }

    public static function icon(): ?string
    {
        return 'wrench';
    }

    static function contentHtml(): string
    {

        $entryTypesBySite = collect(Craft::$app->getSites()->getAllSites())->mapWithKeys(function($site) {
            $entryTypes = Craft::$app->getEntries()->getEntryTypesBySiteId($site->id);
            return [
                'site' => [
                    'name' => $site->name,
                    'id' => $site->id,
                    'entryTypes' => $entryTypes,
                ]
            ];
        });

        return \Craft::$app->getView()->renderTemplate('neverstale/utilities/scan', [

        ]);
    }
}
