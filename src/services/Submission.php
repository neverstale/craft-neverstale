<?php

namespace zaengle\neverstale\services;

use Craft;
use craft\elements\Entry;
use craft\errors\ElementNotFoundException;
use craft\errors\InvalidElementException;
use craft\helpers\Queue;
use Illuminate\Support\Collection;
use yii\base\Component;
use yii\base\Exception;
use zaengle\neverstale\elements\db\NeverstaleSubmissionQuery;
use zaengle\neverstale\elements\NeverstaleSubmission;
use zaengle\neverstale\enums\AnalysisStatus;
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
    public function queue(Entry $entry): int
    {
        return Queue::push(new CreateSubmissionJob([
            'entryId' => $entry->id,
        ]));
    }

    public function forEntry(Entry $entry): ?NeverstaleSubmission
    {
        /** @var NeverstaleSubmissionQuery $query */
        $query = NeverstaleSubmission::find();

        // @todo cleanup
        /** @var Collection<NeverstaleSubmission> $existingSubmissions */
        $existingSubmissions = $query
            ->entryId($entry->canonicalId)
            ->siteId($entry->siteId)
            ->collect();

        $pendingSubmissions = $existingSubmissions->where(
            fn(NeverstaleSubmission $submission) => $submission->status === AnalysisStatus::PendingInitialAnalysis->value ||
                $submission->status === AnalysisStatus::PendingReanalysis->value
        );

        if ($pendingSubmissions->count()) {
            return $pendingSubmissions->first();
        }

        $processingSubmissions = $existingSubmissions->where(
            fn(NeverstaleSubmission $submission) => $submission->status === AnalysisStatus::Processing->value
        );

        if ($processingSubmissions->count()) {
            // @todo: what logic do we actually want here?
            return $processingSubmissions->first();
        }

        $submission = $this->create($entry);

        Plugin::log(Plugin::t("Created NeverstaleSubmission #{submissionId} for Entry #{entryId}", [
            'entryId' => $entry->id,
            'submissionId' => $submission->id,
        ]));

        if (!$this->save($submission)) {
            throw new InvalidElementException($submission, 'Failed to save NeverstaleSubmission');
        }

        return $submission;
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
