<?php

namespace zaengle\neverstale\services;

use Craft;
use craft\elements\Entry;
use craft\errors\ElementNotFoundException;
use craft\helpers\Queue;
use yii\base\Component;
use yii\base\Exception;
use zaengle\neverstale\elements\NeverstaleSubmission;
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
    public function queue(Entry $entry): ?string
    {
        return Queue::push(new CreateSubmissionJob([
            'entryId' => $entry->id,
        ]));
    }
    public function findOrCreate(Entry $entry): ?NeverstaleSubmission
    {
        $submission = $this->find($entry);

        if (!$submission) {
            $submission = $this->create($entry);
            $this->save($submission);

            Plugin::log(Plugin::t("Created NeverstaleSubmission #{submissionId} for Entry #{entryId}", [
                'entryId' => $entry->id,
                'submissionId' => $submission->id,
            ]));
        }

        return $submission;
    }
    public function find(Entry $entry): ?NeverstaleSubmission
    {
        return NeverstaleSubmission::findOne([
            'entryId' => $entry->canonicalId,
            'siteId' => $entry->siteId,
        ]);
    }
    public function create(Entry $entry): NeverstaleSubmission
    {
        return new NeverstaleSubmission([
            'entryId' => $entry->canonicalId,
            'siteId' => $entry->siteId,
        ]);
    }
    /**
     * @throws \Throwable
     * @throws Exception
     * @throws ElementNotFoundException
     */
    public function save(NeverstaleSubmission $submission): bool
    {
        $saved = Craft::$app->getElements()->saveElement($submission);

        if (!$saved) {
            Plugin::error("Failed to save submission #{$submission->id}" . print_r($submission->getErrors(), true));
        }

        return $saved;
    }
}
