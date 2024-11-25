<?php

namespace zaengle\neverstale\helpers;

use zaengle\neverstale\Plugin;

class SubmissionJobHelper
{
    public const DEFAULT_PRIORITY = 512;
    public const DEFAULT_DELAY = 15;

    public static function getDelay(): int
    {
        return (int)(Plugin::getInstance()->config->get('queueDelay') ?? self::DEFAULT_DELAY);
    }
    public static function getPriority(): int
    {
        return (int)(Plugin::getInstance()->config->get('queuePriority') ?? self::DEFAULT_PRIORITY);
    }
}
