<?php

namespace neverstale\neverstale\traits;

use Craft;
use craft\base\ElementInterface;
use craft\elements\Entry;

/**
 * Trait HasEntry
 *
 * @property Entry|null $entry
 */
trait HasEntry
{
    public ?int $entryId = null;
    public ?int $siteId = null;
    private ?Entry $entry = null;

    public function setEntry(Entry|ElementInterface $entry): void
    {
        $this->entry = $entry;
        $this->siteId = $entry->siteId;
        $this->entryId = $entry->canonicalId;
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