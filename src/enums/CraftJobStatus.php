<?php

namespace neverstale\neverstale\enums;

/**
 * Job status is essentially an enum but it's not exposed as such in Craft.
 * @see https://github.com/craftcms/cms/blob/5.x/src/queue/QueueInterface.php#L105-L126
 */
enum CraftJobStatus: int
{
    case WAITING = 1;
    case RESERVED = 2;
    case DONE = 3;
    case FAILED = 4;
}