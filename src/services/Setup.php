<?php

namespace neverstale\craft\services;

use yii\base\Component;
use neverstale\craft\elements\NeverstaleContent;
use neverstale\craft\Plugin;

/**
 * Setup service
 */
class Setup extends Component
{
    public function isComplete(): bool
    {
        return $this->hasCredentials()
            && $this->isContentConfigured()
            && $this->canConnect();
    }
    public function hasCredentials(): bool
    {
        return Plugin::getInstance()->config->get('apiKey') && Plugin::getInstance()->config->get('webhookSecret');
    }
    public function isContentConfigured(): bool
    {
        return count(Plugin::getInstance()->settings->getEnabledSections()) > 0;
    }
    public function isSyncEnabled(): bool
    {
        return (bool) Plugin::getInstance()->config->get('enable');
    }
    public function canConnect(): bool
    {
        return true;
    }
    public function hasSentContent(): string
    {
        return count(NeverstaleContent::find()->ids()) > 0;
    }
    public function getDocsUrl(): string
    {
//        @todo: add real URL here
        return 'https://neverstale.io/docs/integrations/craft-cms';
    }
    public function getNeverstaleAppUrl(): string
    {
//        @todo: add real URL here
        return 'https://neverstale.io/login';
    }
}
