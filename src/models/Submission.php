<?php

namespace zaengle\neverstale\models;

use Craft;
use craft\base\Model;

/**
 * Submission model
 */
class Submission extends Model
{
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // ...
        ]);
    }
}
