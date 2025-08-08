<?php

namespace neverstale\craft\jobs;

use craft\elements\Entry;
use craft\queue\BaseJob;
use neverstale\craft\Plugin;

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
    public int $entryTypeId;

    public function execute($queue): void
    {
        $entries = Entry::find()
            ->typeId($this->entryTypeId)
            ->status(Entry::STATUS_LIVE)
            ->all();

        $totalEntries = count($entries);
        $processed = 0;

        foreach ($entries as $entry) {
            if (Plugin::getInstance()->entry->shouldIngest($entry)) {
                Plugin::getInstance()->content->queue($entry);
            }

            $processed++;
            $this->setProgress($queue, $processed / $totalEntries);
        }
    }

    protected function defaultDescription(): ?string
    {
        return null;
    }
}
