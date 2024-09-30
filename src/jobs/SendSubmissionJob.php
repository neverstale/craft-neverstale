<?php

namespace zaengle\neverstale\jobs;

use Craft;
use craft\errors\ElementNotFoundException;
use craft\errors\InvalidElementException;
use craft\queue\BaseJob;
use zaengle\neverstale\elements\NeverstaleSubmission;
use zaengle\neverstale\Plugin;

class SendSubmissionJob extends BaseJob
{
    public int $submissionId;

    /**
     * @throws ElementNotFoundException
     * @throws InvalidElementException
     */
    public function execute($queue): void
    {
        $submission = Craft::$app->getElements()->getElementById($this->submissionId, NeverstaleSubmission::class);

        if (!$submission) {
            throw new ElementNotFoundException("NeverstaleSubmission with ID {$this->submissionId} not found");
        }
        Plugin::getInstance()->api->send($submission);
    }

    protected function defaultDescription(): ?string
    {
        return 'Submit a pending submission to the Neverstale API';
    }
}
