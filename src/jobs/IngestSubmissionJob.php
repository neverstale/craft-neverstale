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
    public int $submissionId;
    public int $entryId;
    // The minimum number of seconds to wait since the last save before submitting the entry
    public int $wait = 15;
    public bool $wasDelayed = false;
    public \DateTime $createdAt;

    /**
     * @throws ElementNotFoundException
     * @throws InvalidElementException
     */
    public function execute($queue): void
    {
        // Preflight
        $submission = NeverstaleSubmission::findOne($this->submissionId);

        if (!$submission) {
            throw new ElementNotFoundException("NeverstaleSubmission with ID {$this->submissionId} not found");
        }

        // Delay
//        @todo fix this
//        if ($this->isTooFresh($submission)) {
//            $wait = $this->wait + 15;
//            Plugin::info("[IngestSubmissionJob] Delaying submission for Entry #{$this->entryId} by {$wait} seconds");
//
//            $submission->addJob(new self([
//                'submissionId' => $this->submissionId,
//                'entryId' => $this->entryId,
//                'wait' => $wait,
//                'wasDelayed' => true,
//                'createdAt' => new \DateTime(),
//            ]));
//
//            return;
//        }
        // Act
        Plugin::getInstance()->api->ingest($submission);

        // Clean
        QueueHelper::push(new CleanSubmissionJobsJob([
            'submissionId' => $this->submissionId,
        ]));
    }

    public function isTooFresh(NeverstaleSubmission $submission): bool
    {
        $minTimeSinceEntrySave = $submission->getEntry()->dateUpdated->add(new \DateInterval("PT{$this->wait}S"));

        Plugin::info("[IngestSubmissionJob] Minimum age for Entry #{$this->entryId} is {$minTimeSinceEntrySave->format('c')}");
        Plugin::info("[IngestSubmissionJob] Job was created at {$this->createdAt->format('c')}");
        Plugin::info("[IngestSubmissionJob] isTooFresh: " . ($this->createdAt < $minTimeSinceEntrySave ? 'true' : 'false'));

        return $this->createdAt < $minTimeSinceEntrySave;
    }
    protected function defaultDescription(): ?string
    {
        $suffix = $this->wasDelayed ? " (deferred by {$this->wait}s)" : '';
        return "Submit Entry #{$this->entryId} to Neverstale{$suffix}";
    }
}
