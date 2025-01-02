<?php

namespace zaengle\neverstale\utilities;

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
        // @todo implement
        return '';
    }
}
