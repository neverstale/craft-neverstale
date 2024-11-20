<?php

namespace zaengle\neverstale\jobs;

use craft\errors\ElementNotFoundException;
use craft\errors\InvalidElementException;
use craft\helpers\Queue as QueueHelper;
use craft\queue\BaseJob;
use zaengle\neverstale\Plugin;
use zaengle\neverstale\elements\NeverstaleSubmission;

/**
 *  Neverstale Send Submission Job
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
class IngestSubmissionJob extends BaseJob
{
    public ?string $description = 'Ingesting entry to Neverstale';
    public int $submissionId;

    /**
     * @throws ElementNotFoundException
     */
    public function execute($queue): void
    {
        $submission = NeverstaleSubmission::findOne($this->submissionId);

        if (!$submission) {
            throw new ElementNotFoundException("NeverstaleSubmission with ID {$this->submissionId} not found");
        }

        Plugin::getInstance()->api->ingest($submission);
    }

    protected function defaultDescription(): ?string
    {
        return "Ingesting Submission #{$this->submissionId} to Neverstale";
    }
}
