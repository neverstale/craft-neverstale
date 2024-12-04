<?php

namespace zaengle\neverstale\services;

use yii\base\Component;

/**
 * Setup service
 */
class Setup extends Component
{
    public function isComplete(): bool
    {
        return true;
    }
    public function isConfigured(): bool
    {
        return true;
    }
    public function hasCredentials(): bool
    {
        return true;
    }
}
