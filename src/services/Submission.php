<?php

namespace zaengle\neverstale\services;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\errors\ElementNotFoundException;
use craft\errors\InvalidElementException;
use craft\helpers\ElementHelper;
use craft\helpers\Queue;
use yii\base\Component;
use yii\base\Exception;
use zaengle\neverstale\elements\NeverstaleSubmission;
use zaengle\neverstale\jobs\CreateSubmissionJob;
use zaengle\neverstale\support\ApiClient;

/**
 * Submission service
 */
class Submission extends Component
{
    public function queue(ElementInterface $element): int
    {
        return Queue::push(new CreateSubmissionJob([
            'elementId' => $element->id,
        ]));
    }
    /**
     * @throws ElementNotFoundException
     * @throws InvalidElementException
     */
    public function create(ElementInterface $element): ?ElementInterface
    {
        $submission = new NeverstaleSubmission([
            'entryId' => $element->id,
            'siteId' => $element->siteId,
        ]);

        if (!Craft::$app->getElements()->saveElement($submission)) {
            throw new InvalidElementException($submission);
        }

        return $submission;
    }
}
