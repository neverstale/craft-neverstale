<?php

namespace zaengle\neverstale\jobs;

use Craft;
use craft\errors\ElementNotFoundException;
use craft\errors\InvalidElementException;
use craft\helpers\Queue;
use craft\queue\BaseJob;
use zaengle\neverstale\elements\NeverstaleSubmission;
use zaengle\neverstale\helpers\SubmissionJobHelper;
use zaengle\neverstale\Plugin;

/**
 * Create Submission Job
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
class CreateSubmissionJob extends BaseJob
{
    public int $entryId;
    /**
     * @throws ElementNotFoundException
     * @throws InvalidElementException
     */
    public function execute($queue): void
    {
        $entry = Craft::$app->getElements()->getElementById($this->entryId);

        if (!$entry) {
            throw new ElementNotFoundException();
        }
        /** @var NeverstaleSubmission $submission */
        $submission = Plugin::getInstance()->submission->findOrCreate($entry);

        Queue::push(new IngestSubmissionJob([
            'submissionId' => $submission->id,
        ]));

    }
    protected function defaultDescription(): ?string
    {
        return "Neverstale sync check for entry #{$this->entryId}";
    }
}
