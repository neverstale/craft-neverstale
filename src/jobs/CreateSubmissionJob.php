<?php

namespace zaengle\neverstale\jobs;

use Craft;
use craft\errors\ElementNotFoundException;
use craft\errors\InvalidElementException;
use craft\helpers\Queue as QueueHelper;
use craft\queue\BaseJob;
use zaengle\neverstale\elements\NeverstaleSubmission;
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
    public int $elementId;

    /**
     * @throws ElementNotFoundException
     * @throws InvalidElementException
     */
    public function execute($queue): void
    {
        $element = Craft::$app->getElements()->getElementById($this->elementId);

        if (!$element) {
            throw new ElementNotFoundException();
        }
        /** @var NeverstaleSubmission $submission */
        $submission = Plugin::getInstance()->submission->createOrUpdate($element);

        QueueHelper::push(new SendSubmissionJob([
            'submissionId' => $submission->id,
        ]));
    }
    protected function defaultDescription(): ?string
    {
        return 'Create a pending Neverstale submission';
    }
}
