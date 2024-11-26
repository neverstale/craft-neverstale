<?php

namespace zaengle\neverstale\services;

use Craft;
use yii\base\Component;
use zaengle\neverstale\support\ApiClient;

/**
 * Flag service
 */
class Flag extends Component
{
    public ApiClient $client;

    public function ignore(string $flagId): void
    {
        $this->client->ignore($flagId);
    }
    public function reschedule(string $flagId): void
    {
        $this->client->reschedule($flagId);
    }
}
