<?php

namespace neverstale\craft\jobs;

use craft\queue\BaseJob;

/**
 * Neverstale Scan EntryType Job
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
class ScanEntryTypeJob extends BaseJob
{
    public function execute($queue): void
    {
        // ...
    }

    protected function defaultDescription(): ?string
    {
        return null;
    }
}
