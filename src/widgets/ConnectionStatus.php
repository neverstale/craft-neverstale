<?php

namespace neverstale\craft\widgets;

use Craft;
use craft\base\Widget;
use neverstale\craft\Plugin;

/**
 * Neverstale Connection Status widget type
 *
 * @property-read null|string $bodyHtml
 */
class ConnectionStatus extends Widget
{
    public static function displayName(): string
    {
        return Plugin::t('Neverstale Connection Status');
    }

    public static function isSelectable(): bool
    {
        return true;
    }

    public static function icon(): ?string
    {
        return 'satellite-dish';
    }

    public function getBodyHtml(): ?string
    {
        Plugin::getInstance()->setup->canConnect();

        return Craft::$app->getView()->renderTemplate('neverstale/_widgets/connection-status', [
            'setup' => Plugin::getInstance()->setup,
            'lastSync' => Plugin::getInstance()->content->getLastSync(),
        ]);
    }
}
