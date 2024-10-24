<?php

namespace zaengle\neverstale\services;

use Craft;
use craft\base\ElementInterface;
use craft\errors\ElementNotFoundException;
use craft\errors\InvalidElementException;
use craft\helpers\Queue;
use yii\base\Component;
use zaengle\neverstale\elements\NeverstaleSubmission;
use zaengle\neverstale\enums\SubmissionStatus;
use zaengle\neverstale\jobs\CreateSubmissionJob;
use zaengle\neverstale\Plugin;

/**
 * Neverstale Submission service
 *
 * Handles the management of NeverstaleSubmissions Elements
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
class Submission extends Component
{
    public function queue(ElementInterface $element): int
    {
        return Queue::push(new CreateSubmissionJob([
            'elementId' => $element->id,
        ]));
    }

    public function createOrUpdate(ElementInterface $element): ?ElementInterface
    {
        $existingSubmissions = NeverstaleSubmission::find()
            ->entryId($element->canonicalId)
            ->siteId($element->siteId);

        $pendingSubmissions = $existingSubmissions->where(fn(NeverstaleSubmission $submission) => $submission->status === SubmissionStatus::Pending->value);

        if ($pendingSubmissions->count()) {
            return $pendingSubmissions->first();
        }

        $processingSubmissions = $existingSubmissions->where(fn(NeverstaleSubmission $submission) => $submission->status === SubmissionStatus::Processing->value);

        if ($processingSubmissions->count()) {
            // @todo: what logic do we actually want here?
            return $processingSubmissions->first();
        }
        $submission = new NeverstaleSubmission([
            'entryId' => $element->canonicalId,
            'siteId' => $element->siteId,
        ]);

        Plugin::log('Created NeverstaleSubmission for Element with ID ' . $this->elementId . ' and submission id:' . $submission->id, 'info');


        if (!Craft::$app->getElements()->saveElement($submission)) {
            throw new InvalidElementException($submission);
        }

        return $submission;
    }

    /**
     * @throws ElementNotFoundException
     * @throws InvalidElementException
     */
    public function create(ElementInterface $element): ?ElementInterface
    {
        $submission = new NeverstaleSubmission([
            'entryId' => $element->canonicalId,
            'siteId' => $element->siteId,
        ]);

        if (!Craft::$app->getElements()->saveElement($submission)) {
            throw new InvalidElementException($submission);
        }

        return $submission;
    }
}
