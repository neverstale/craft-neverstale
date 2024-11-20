<?php

namespace zaengle\neverstale\helpers;

use Craft;
use craft\queue\Queue;
use craft\queue\QueueInterface;
use Illuminate\Support\Collection;
use zaengle\neverstale\elements\NeverstaleSubmission;
use zaengle\neverstale\enums\CraftJobStatus;
use zaengle\neverstale\Plugin;

class SubmissionJobHelper
{
    public const DEFAULT_PRIORITY = 512;
    public const DEFAULT_DELAY = 15;

    public static function getDelay(): int
    {
        return Plugin::getInstance()->config->get('queueDelay') ?? self::DEFAULT_DELAY;
    }
    public static function getPriority(): int
    {
        return Plugin::getInstance()->config->get('queuePriority') ?? self::DEFAULT_PRIORITY;
    }

    public static function getOldJobs(QueueInterface $queue, NeverstaleSubmission $submission): Collection
    {
        $completeStatuses = collect([CraftJobStatus::DONE->value, CraftJobStatus::FAILED->value]);

        return collect($submission->jobIds)
            ->filter(function($jobId) use ($completeStatuses, $queue) {
                try {
                    $job = $queue->getJobDetails($jobId);
                    return $completeStatuses->contains($job['status']);
                } catch (\Exception $e) {
                    return true;
                }
            });
    }

    public static function hasInProgressJob(QueueInterface $queue, NeverstaleSubmission $submission, ?int $currentJobId = null): bool
    {
        $inProgressStatuses = collect([CraftJobStatus::WAITING->value, CraftJobStatus::RESERVED->value]);

        return collect($submission->jobIds)
            ->contains(function($jobId) use ($inProgressStatuses, $queue, $currentJobId) {
                if ($jobId === $currentJobId) {
                    return false;
                }
                try {
                    $job = $queue->getJobDetails($jobId);
                    return $inProgressStatuses->contains($job['status']);
                } catch (\Exception $e) {
                    return false;
                }
            });
    }

    public static function cleanOldJobsFromSubmission(QueueInterface $queue, NeverstaleSubmission $submission): bool
    {
        $oldJobs = self::getOldJobs($queue, $submission);

        if ($oldJobs->isNotEmpty()) {
            Plugin::info("Removing stale job IDs for {$submission->id}: " . $oldJobs->implode(', '));
            $oldJobs->each(function($jobId) use ($submission) {
                $submission->removeJob($jobId);
            });

            return Craft::$app->getElements()->saveElement($submission);
        }

        return true;
    }
}
