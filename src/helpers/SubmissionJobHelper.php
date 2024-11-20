<?php

namespace zaengle\neverstale\helpers;

use Craft;
use craft\queue\Queue;
use craft\queue\QueueInterface;
use Illuminate\Support\Collection;
use zaengle\neverstale\elements\NeverstaleSubmission;
use zaengle\neverstale\enums\CraftJobStatus;
use zaengle\neverstale\Plugin;

class SubmissionJobHelper
{
    public const DEFAULT_PRIORITY = 512;
    public const DEFAULT_DELAY = 15;

    public static function getDelay(): int
    {
        return Plugin::getInstance()->config->get('queueDelay') ?? self::DEFAULT_DELAY;
    }
    public static function getPriority(): int
    {
        return Plugin::getInstance()->config->get('queuePriority') ?? self::DEFAULT_PRIORITY;
    }
}
