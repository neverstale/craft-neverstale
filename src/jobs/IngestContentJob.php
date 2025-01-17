<?php

namespace neverstale\craft\jobs;

use craft\errors\ElementNotFoundException;
use craft\queue\BaseJob;
use neverstale\craft\elements\NeverstaleContent;
use neverstale\craft\Plugin;

/**
 *  Neverstale Ingest Content Job
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
class IngestContentJob extends BaseJob
{
    public ?string $description = 'Ingesting entry to Neverstale';
    public int $contentId;

    /**
     * @throws ElementNotFoundException
     */
    public function execute($queue): void
    {
        $content = NeverstaleContent::findOne($this->contentId);

        if (!$content) {
            Plugin::error("Content not found: {$this->contentId}");
            throw new ElementNotFoundException();
        }

        Plugin::getInstance()->content->ingest($content);
    }

    protected function defaultDescription(): ?string
    {
        return "Ingesting Content #{$this->contentId} to Neverstale";
    }
}
