<?php

namespace zaengle\neverstale\jobs;

use craft\errors\ElementNotFoundException;
use craft\errors\InvalidElementException;
use craft\helpers\Queue as QueueHelper;
use craft\queue\BaseJob;
use zaengle\neverstale\Plugin;
use zaengle\neverstale\elements\NeverstaleContent;

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
