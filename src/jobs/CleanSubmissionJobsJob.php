<?php

namespace zaengle\neverstale\jobs;

use Craft;
use craft\errors\ElementNotFoundException;
use craft\queue\BaseJob;
use zaengle\neverstale\elements\NeverstaleSubmission;

/**
 * Clean Submission Jobs Job queue job
 */
class CleanSubmissionJobsJob extends BaseJob
{
    public int $submissionId;

    function execute($queue): void
    {
        $submission = NeverstaleSubmission::findOne($this->submissionId);

        if (!$submission) {
            throw new ElementNotFoundException("NeverstaleSubmission with ID {$this->submissionId} not found");
        }

        $submission->cleanOldJobs($queue);
    }

    protected function defaultDescription(): ?string
    {
        return null;
    }
}
