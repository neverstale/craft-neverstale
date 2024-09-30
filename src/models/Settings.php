<?php

namespace zaengle\neverstale\models;

use Craft;
use craft\base\Model;

/**
 * NeverStale settings
 */
class Settings extends Model
{
    public bool $enable = true;
    public string $apiKey = '';
    public string $apiSecret = '';

    /**
     * @inheritDoc
     */
    public function defineRules(): array
    {
        return [
            [['apiKey', 'apiSecret'], 'required'],
        ];
    }
}
