<?php

namespace neverstale\neverstale\jobs;

use craft\queue\BaseJob;
use Exception;
use neverstale\neverstale\elements\Content;
use neverstale\neverstale\Plugin;

/**
 * Batch Ingest Job
 *
 * Processes a batch of Content items via the API.
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   2.0.0
 */
class BatchIngestJob extends BaseJob
{
    /**
     * @var string Operation identifier
     */
    public string $operationId;

    /**
     * @var int Batch index
     */
    public int $batchIndex;

    /**
     * @var array Array of Content IDs to process
     */
    public array $contentIds = [];

    /**
     * Execute the batch job
     */
    public function execute($queue): void
    {
        $batchSize = count($this->contentIds);
        Plugin::info("Processing batch {$this->batchIndex} with {$batchSize} items");

        // Load content items
        $contents = [];
        foreach ($this->contentIds as $contentId) {
            $content = Content::findOne($contentId);
            if ($content) {
                $contents[] = $content;
            }
        }

        if (empty($contents)) {
            Plugin::warning("Batch {$this->batchIndex}: No valid content items found");

            return;
        }

        // Process batch through Content service
        try {
            $contentService = Plugin::getInstance()->content;
            $result = $contentService->batchIngest($contents);

            Plugin::info("Batch {$this->batchIndex} completed: {$result['successCount']} successful, {$result['errorCount']} failed");

        } catch (Exception $e) {
            Plugin::error("Batch {$this->batchIndex} failed: {$e->getMessage()}");
            throw $e; // Let Craft's queue handle the retry
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        $itemCount = count($this->contentIds);

        return "Batch {$this->batchIndex} - {$itemCount} items";
    }
}
