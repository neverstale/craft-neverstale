<?php

namespace zaengle\neverstale\traits;

use Craft;
use craft\base\ElementInterface;
use craft\elements\Entry;

trait HasEntry
{
    public int $entryId;
    public int|null $siteId = null;
    private ?Entry $entry = null;

    public function setEntry(Entry|ElementInterface $entry): void
    {
        $this->entry = $entry;
        $this->siteId = $entry->siteId;
        $this->entryId = $entry->id;
    }

    public function getEntry(): ?Entry
    {
        if ($this->entry !== null) {
            return $this->entry;
        }

        if (!$this->entryId) {
            return null;
        }

        return $this->entry = Craft::$app->getEntries()->getEntryById($this->entryId, $this->siteId);
    }
}
