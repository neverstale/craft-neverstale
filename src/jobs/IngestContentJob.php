<?php

namespace neverstale\neverstale\jobs;

use Craft;
use craft\errors\ElementNotFoundException;
use craft\helpers\Queue;
use craft\queue\BaseJob;
use Exception;
use neverstale\api\Client;
use neverstale\api\exceptions\AuthenticationException;
use neverstale\api\exceptions\ValidationException;
use neverstale\neverstale\elements\Content;
use neverstale\neverstale\enums\AnalysisStatus;
use neverstale\neverstale\models\TransactionLogItem;
use neverstale\neverstale\Plugin;

/**
 * Neverstale Ingest Content Job
 *
 * Background job for processing content ingestion to the Neverstale API.
 * Handles async processing of content submissions to prevent UI blocking
 * and provide reliable processing with error handling and retry capabilities.
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   2.0.0
 * @see     https://github.com/neverstale/craft-neverstale
 */
class IngestContentJob extends BaseJob
{
    /**
     * @var string|null Job description for queue display
     */
    public ?string $description = 'Ingesting content to Neverstale';

    /**
     * @var int Content element ID to process
     */
    public int $contentId;

    /**
     * @var int Maximum number of retry attempts
     */
    public int $maxRetries = 3;

    /**
     * @var int Current retry attempt number
     */
    public int $currentAttempt = 0;

    /**
     * Execute the job
     *
     * Processes the content ingestion, handling errors gracefully
     * and providing appropriate logging for monitoring.
     *
     * @param  \yii\queue\Queue  $queue  Queue instance
     * @return void
     * @throws ElementNotFoundException if content element is not found
     */
    public function execute($queue): void
    {
        $this->currentAttempt++;

        Plugin::debug("Starting IngestContentJob for content #{$this->contentId} (attempt {$this->currentAttempt})");

        // Retrieve the content element
        $content = Content::findOne($this->contentId);

        if (! $content) {
            Plugin::error("Content not found: {$this->contentId}");
            throw new ElementNotFoundException("Content with ID {$this->contentId} not found");
        }

        // Validate content before processing
        $validation = $this->validateContent($content);

        if (! $validation['valid']) {
            Plugin::error("Content #{$this->contentId} validation failed: ".implode(', ', $validation['errors']));
            $this->handleValidationFailure($content, $validation['errors']);

            return;
        }

        try {
            // Attempt to ingest the content
            $success = Plugin::getInstance()->content->ingest($content);

            if ($success) {
                Plugin::info("Successfully ingested content #{$this->contentId}");
            } else {
                $this->handleIngestFailure($content, 'Ingest returned false');
            }

        } catch (Exception $e) {
            Plugin::error("Exception during ingest for content #{$this->contentId}: {$e->getMessage()}");
            $this->handleIngestFailure($content, $e->getMessage(), $e);
        }
    }

    /**
     * Validate content before processing
     *
     * Ensures the content is in a valid state for API submission
     * and meets all necessary requirements.
     *
     * @param  Content  $content  Content to validate
     * @return array Validation results ['valid' => bool, 'errors' => array]
     */
    protected function validateContent(Content $content): array
    {
        $errors = [];

        // Check if content has an associated entry
        if (! $content->getEntry()) {
            $errors[] = 'Content has no associated entry';
        }

        // Check if entry is still eligible for processing
        if ($content->getEntry() && ! Plugin::getInstance()->entry->shouldIngest($content->getEntry())) {
            $errors[] = 'Associated entry is no longer eligible for ingestion';
        }

        if (! Plugin::getInstance()->content->checkCanConnect()) {
            $errors[] = 'Cannot connect to Neverstale API';
        }

        // Validate content formatting
        try {
            $formatted = Plugin::getInstance()->format->forIngest($content);
            $formatValidation = Plugin::getInstance()->format->validateContent($formatted);

            if (! $formatValidation['valid']) {
                $errors = array_merge($errors, $formatValidation['errors']);
            }
        } catch (Exception $e) {
            $errors[] = "Content formatting failed: {$e->getMessage()}";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Handle validation failure
     *
     * Processes validation failures by logging appropriate messages
     * and updating content status if necessary.
     *
     * @param  Content  $content  Content that failed validation
     * @param  array    $errors   Validation error messages
     */
    protected function handleValidationFailure(Content $content, array $errors): void
    {
        $errorMessage = 'Validation failed: '.implode(', ', $errors);

        // Log the validation failure
        Plugin::error("Validation failed for content #{$content->id}: {$errorMessage}");

        // Update content with error status and create transaction log
        try {
            // Set the content status first to avoid issues
            $content->setAnalysisStatus(AnalysisStatus::API_ERROR);
            Craft::$app->getElements()->saveElement($content);

            // Then try to log the transaction (non-critical if it fails)
            try {
                $transactionLogItem = new TransactionLogItem([
                    'transactionStatus' => 'error',
                    'message' => $errorMessage,
                    'event' => 'job.validation_failed',
                ]);
                $content->logTransaction($transactionLogItem);
            } catch (Exception $logEx) {
                Plugin::error("Failed to log transaction: {$logEx->getMessage()}");
            }
        } catch (Exception $e) {
            Plugin::error("Failed to save validation error for content #{$content->id}: {$e->getMessage()}");
        }
    }

    /**
     * Handle ingestion failure
     *
     * Processes ingest failures, determining whether to retry
     * or mark the job as permanently failed.
     *
     * @param  Content         $content       Content that failed ingestion
     * @param  string          $errorMessage  Error description
     * @param  Exception|null  $exception     Original exception if available
     */
    protected function handleIngestFailure(Content $content, string $errorMessage, ?Exception $exception = null): void
    {
        Plugin::error("Ingest failed for content #{$content->id}: {$errorMessage}");

        // Determine if we should retry
        if ($this->shouldRetry($exception)) {
            Plugin::info("Will retry ingestion for content #{$content->id} (attempt {$this->currentAttempt}/{$this->maxRetries})");

            // Re-queue the job with incremented attempt counter
            Queue::push(new self([
                'contentId' => $this->contentId,
                'currentAttempt' => $this->currentAttempt,
                'maxRetries' => $this->maxRetries,
            ]), 0, $this->calculateRetryDelay());

            return;
        }

        // Maximum retries reached or non-retryable error
        Plugin::error("Permanently failed to ingest content #{$content->id} after {$this->currentAttempt} attempts");

        try {
            // Log the permanent failure
            $transactionLogItem = new TransactionLogItem([
                'transactionStatus' => Client::STATUS_ERROR,
                'message' => "Permanent ingestion failure: {$errorMessage}",
                'event' => 'job.failed',
            ]);

            $content->logTransaction($transactionLogItem);
            Craft::$app->getElements()->saveElement($content);
        } catch (Exception $e) {
            Plugin::error("Failed to log permanent failure for content #{$content->id}: {$e->getMessage()}");
        }
    }

    /**
     * Determine if the job should be retried
     *
     * Analyzes the failure type and attempt count to determine
     * if another attempt should be made.
     *
     * @param  Exception|null  $exception  Exception that caused the failure
     * @return bool True if job should be retried
     */
    protected function shouldRetry(?Exception $exception = null): bool
    {
        // Don't retry if we've exceeded maximum attempts
        if ($this->currentAttempt >= $this->maxRetries) {
            return false;
        }

        // Don't retry for certain types of exceptions
        if ($exception) {
            $nonRetryableExceptions = [
                AuthenticationException::class,
                ValidationException::class,
                ElementNotFoundException::class,
            ];

            foreach ($nonRetryableExceptions as $exceptionClass) {
                if ($exception instanceof $exceptionClass) {
                    Plugin::info("Not retrying due to non-retryable exception: ".get_class($exception));

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Calculate delay before retry attempt
     *
     * Implements exponential backoff to avoid overwhelming
     * the API with rapid retry attempts.
     *
     * @return int Delay in seconds
     */
    protected function calculateRetryDelay(): int
    {
        // Exponential backoff: 2^attempt * base delay (30 seconds)
        $baseDelay = 30;

        return min($baseDelay * (2 ** ($this->currentAttempt - 1)), 300); // Max 5 minutes
    }

    /**
     * Get the default description for this job
     *
     * @return string|null Job description
     */
    protected function defaultDescription(): ?string
    {
        return "Ingesting Content #{$this->contentId} to Neverstale";
    }
}
