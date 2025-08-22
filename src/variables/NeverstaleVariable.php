<?php

namespace neverstale\neverstale\variables;

use craft\elements\Entry;
use neverstale\neverstale\elements\Content;
use neverstale\neverstale\Plugin;

/**
 * Neverstale template variable
 */
class NeverstaleVariable
{
    /**
     * Get the config service
     */
    public function getConfig()
    {
        return Plugin::getInstance()->config;
    }

    /**
     * Get Content for an entry
     *
     * @param  Entry  $entry
     * @return Content|null
     */
    public function getContentForEntry(Entry $entry): ?Content
    {
        return Plugin::getInstance()->content->find($entry);
    }
}
