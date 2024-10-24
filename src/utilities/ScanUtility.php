<?php

namespace zaengle\neverstale\utilities;

use Craft;
use craft\base\Utility;
use zaengle\neverstale\Plugin;

/**
 * Scan utility
 */
class ScanUtility extends Utility
{
    public static function displayName(): string
    {
        return Plugin::t('Neverstale: Scan site');
    }

    public static function id(): string
    {
        return 'neverscale-scan';
    }

    public static function icon(): ?string
    {
        return 'wrench';
    }

    public static function contentHtml(): string
    {
        // @todo complete this
        $entryTypesBySite = collect(Craft::$app->getSites()->getAllSites())->mapWithKeys(function($site) {
            $entryTypes = Craft::$app->getEntries()->getEntryTypesBySiteId($site->id);
            return [
                'site' => [
                    'name' => $site->name,
                    'id' => $site->id,
                    'entryTypes' => $entryTypes,
                ],
            ];
        });

        return \Craft::$app->getView()->renderTemplate('neverstale/utilities/scan', [

        ]);
    }
}
