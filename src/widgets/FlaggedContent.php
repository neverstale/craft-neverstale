<?php

namespace neverstale\craft\widgets;

use Craft;
use craft\base\Widget;
use neverstale\craft\elements\NeverstaleContent;
use neverstale\craft\Plugin;

/**
 * Neverstale Flagged Content widget type
 *
 * @property-read null|string $bodyHtml
 */
class FlaggedContent extends Widget
{
    public static function displayName(): string
    {
        return Plugin::t('Neverstale Flagged Content');
    }

    public static function isSelectable(): bool
    {
        return true;
    }

    public static function icon(): ?string
    {
        return 'flag';
    }

    public function getBodyHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('neverstale/_widgets/flagged-content', [
            'setup' => Plugin::getInstance()->setup,
            'lastSync' => Plugin::getInstance()->content->getLastSync(),
            'flaggedContent' => NeverstaleContent::find()
                ->hasFlags(true)
                ->orderBy('dateExpired DESC')
                ->limit(5)
                ->collect(),
        ]);
    }
}
