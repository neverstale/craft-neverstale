<?php

namespace neverstale\neverstale\jobs;

use craft\helpers\Queue;
use craft\queue\BaseJob;
use neverstale\neverstale\Plugin;

/**
 * Bulk Ingest Orchestrator Job
 *
 * Queues individual batch jobs for processing content items in chunks.
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   2.0.0
 */
class BulkIngestOrchestrator extends BaseJob
{
    /**
     * @var string Operation identifier
     */
    public string $operationId;

    /**
     * @var array Array of Content IDs to process
     */
    public array $contentIds = [];

    /**
     * @var int Maximum items per batch
     */
    public int $batchSize = 100;

    /**
     * @var int|null User ID who initiated the operation
     */
    public ?int $userId = null;

    /**
     * @var array Additional metadata
     */
    public array $metadata = [];

    /**
     * Execute the orchestrator job
     */
    public function execute($queue): void
    {
        $totalItems = count($this->contentIds);
        Plugin::info("Starting bulk ingest for {$totalItems} items (Operation: {$this->operationId})");

        if (empty($this->contentIds)) {
            Plugin::error("No content IDs provided for bulk ingest operation {$this->operationId}");

            return;
        }

        // Split into batches and queue jobs
        $batches = array_chunk($this->contentIds, $this->batchSize);
        $batchCount = count($batches);

        Plugin::info("Creating {$batchCount} batch jobs for operation {$this->operationId}");

        foreach ($batches as $index => $batchContentIds) {
            $batchJob = new BatchIngestJob([
                'operationId' => $this->operationId,
                'batchIndex' => $index,
                'contentIds' => $batchContentIds,
            ]);

            Queue::push($batchJob);

            $this->setProgress(
                $queue,
                ($index + 1) / $batchCount,
                "Queued batch ".($index + 1)." of {$batchCount}"
            );
        }

        Plugin::info("Completed queuing {$batchCount} batches for operation {$this->operationId}");
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        $itemCount = count($this->contentIds);

        return "Bulk Ingest - {$itemCount} items";
    }
}
