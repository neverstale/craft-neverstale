<?php

namespace zaengle\neverstale\jobs;

use Craft;
use craft\queue\BaseJob;

/**
 * Scan Entry Type Job queue job
 */
class ScanEntryTypeJob extends BaseJob
{
    function execute($queue): void
    {
        // ...
    }

    protected function defaultDescription(): ?string
    {
        return null;
    }
}
