<?php

namespace zaengle\neverstale\elements\db;

use craft\elements\db\ElementQuery;
use craft\elements\Entry;
use craft\helpers\Db;
use zaengle\neverstale\enums\SubmissionStatus;

/**
 * Neverstale Submission Query
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
class NeverstaleSubmissionQuery extends ElementQuery
{
    public mixed $isSent = null;
    public mixed $isProcessed = null;
    public mixed $isFailed = null;
    public mixed $entryId = null;
    public mixed $siteId = null;
    public mixed $neverstaleId = null;
    public mixed $flagCount = null;

    public function isSent(bool $value): self
    {
        $this->isSent = $value;

        return $this;
    }
    public function isProcessed(bool $value): self
    {
        $this->isProcessed = $value;

        return $this;
    }

    public function entryId(int $value): self
    {
        $this->entryId = $value;

        return $this;
    }

    public function entry(Entry $value): self
    {
        $this->entryId = $value->id;
        $this->siteId = $value->siteId;

        return $this;
    }

    protected function statusCondition(string $status): mixed
    {
        $statusVal = SubmissionStatus::tryFrom($status);

        return match ($statusVal ?? $status) {
            SubmissionStatus::Pending => [
                'neverstale_submissions.isSent' => false,
            ],
            SubmissionStatus::Processing => [
                'neverstale_submissions.isSent' => true,
                'neverstale_submissions.isProcessed' => false
            ],
            SubmissionStatus::Flagged => [
                'neverstale_submissions.isSent' => true,
                'neverstale_submissions.isProcessed' => true,
//                @todo check syntax
                'neverstale_submissions.flagCount' => '> 0',
            ],
            SubmissionStatus::Clean => [
                'neverstale_submissions.isSent' => true,
                'neverstale_submissions.isProcessed' => true,
                'neverstale_submissions.flagCount' => 0,
            ],
            SubmissionStatus::Failed => [
                'neverstale_submissions.isErrored' => true,
            ],
            SubmissionStatus::Archived => [
                'elements.archived' => true,
            ],
            default => parent::statusCondition($status),
        };
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('neverstale_submissions');

        $this->query->select([
            'neverstale_submissions.isSent',
            'neverstale_submissions.isProcessed',
            'neverstale_submissions.isFailed',
            'neverstale_submissions.flagCount',
            'neverstale_submissions.entryId',
            'neverstale_submissions.neverstaleId',
            'neverstale_submissions.siteId',
        ]);

        if ($this->isSent) {
            $this->subQuery->andWhere(Db::parsebooleanparam('neverstale_submissions.isSent', $this->isSent));
        }

        if ($this->isProcessed) {
            $this->subQuery->andWhere(Db::parsebooleanparam('neverstale_submissions.isProcessed', $this->isProcessed));
        }

        if ($this->isFailed) {
            $this->subQuery->andWhere(Db::parsebooleanparam('neverstale_submissions.isFailed', $this->isFailed));
        }

        if ($this->flagCount) {
            $this->subQuery->andWhere(Db::parseParam('neverstale_submissions.flagCount', $this->flagCount));
        }

        if ($this->entryId) {
            $this->subQuery->andWhere(Db::parsenumericparam('neverstale_submissions.entryId', $this->entryId));
        }

        if ($this->siteId) {
            $this->subQuery->andWhere(Db::parsenumericparam('neverstale_submissions.siteId', $this->siteId));
        }

        if ($this->neverstaleId) {
            $this->subQuery->andWhere(Db::parseParam('neverstale_submissions.neverstaleId', $this->neverstaleId));
        }

        return parent::beforePrepare();
    }
}
