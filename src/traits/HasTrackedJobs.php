<?php

namespace zaengle\neverstale\traits;

use Craft;
use craft\queue\JobInterface;
use craft\helpers\Queue as QueueHelper;
use craft\queue\QueueInterface;
use zaengle\neverstale\helpers\SubmissionJobHelper;
use zaengle\neverstale\Plugin;

trait HasTrackedJobs
{
    protected array $jobIds = [];
    public function addJob(JobInterface $job): void
    {
        $jobId = QueueHelper::push($job, SubmissionJobHelper::getPriority(), SubmissionJobHelper::getDelay());

        Plugin::log('added Job ID: ' . $jobId . ' to submission ID: ' . $this->id);

        $this->jobIds = array_merge($this->jobIds, [(int) $jobId]);

        Craft::$app->getElements()->saveElement($this);
    }
    public function cleanOldJobs(QueueInterface $queue): void
    {
        $oldJobs = SubmissionJobHelper::getOldJobs($queue, $this);
        collect($this->jobIds)
            ->filter(fn(int $jobId) => $oldJobs->contains($jobId))
            ->each(fn(int $jobId) => $this->removeJob($jobId));

        Craft::$app->getElements()->saveElement($this);
    }
    public function removeJob(int $jobId): void
    {
        $this->jobIds = array_filter($this->jobIds, fn(int $id) => $id !== $jobId);
    }
    abstract public function getJobIds(): array;
}
