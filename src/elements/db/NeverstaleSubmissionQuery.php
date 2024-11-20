<?php

namespace zaengle\neverstale\elements\db;

use craft\elements\db\ElementQuery;
use craft\elements\Entry;
use craft\helpers\Db;
use zaengle\neverstale\enums\AnalysisStatus;

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
    public mixed $entryId = null;
    public mixed $siteId = null;
    public mixed $uid = null;
    public mixed $neverstaleId = null;
    public mixed $flagCount = null;
    public mixed $analysisStatus = null;
    public mixed $nextFlagDate = null;

    public function entryId(mixed $value): self
    {
        $this->entryId = (int) $value;

        return $this;
    }

    public function entry(Entry $value): self
    {
        $this->entryId = $value->id;
        $this->siteId = $value->siteId;

        return $this;
    }

    public function neverstaleId(string $value): self
    {
        $this->neverstaleId = $value;

        return $this;
    }

    public function nextFlagDate(mixed $value): self
    {
        $this->nextFlagDate = $value;

        return $this;
    }

    protected function statusCondition(string $status): mixed
    {
        return match (AnalysisStatus::tryFrom($status) ?? $status) {
            AnalysisStatus::Unsent,
            AnalysisStatus::PendingInitialAnalysis,
            AnalysisStatus::PendingReanalysis,
            AnalysisStatus::Processing,
            AnalysisStatus::AnalysedClean,
            AnalysisStatus::AnalysedFlagged,
            AnalysisStatus::AnalysedError,
            AnalysisStatus::Unknown,
            AnalysisStatus::ApiError => [
                'neverstale_submissions.analysisStatus' => $status,
            ],
            default => parent::statusCondition($status),
        };
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('neverstale_submissions');

        $this->query->select([
            'neverstale_submissions.analysisStatus',
            'neverstale_submissions.flagCount',
            'neverstale_submissions.entryId',
            'neverstale_submissions.neverstaleId',
            'neverstale_submissions.siteId',
        ]);

        if ($this->analysisStatus) {
            $this->subQuery->andWhere(Db::parsebooleanparam('neverstale_submissions.analysisStatus', $this->analysisStatus));
        }
        if ($this->flagCount) {
            $this->subQuery->andWhere(Db::parsenumericparam('neverstale_submissions.flagCount', $this->flagCount));
        }
        if ($this->entryId) {
            $this->subQuery->andWhere(Db::parsenumericparam('neverstale_submissions.entryId', $this->entryId));
        }
        if ($this->neverstaleId) {
            $this->subQuery->andWhere(Db::parseParam('neverstale_submissions.neverstaleId', $this->neverstaleId));
        }
        if ($this->siteId) {
            $this->subQuery->andWhere(Db::parsenumericparam('neverstale_submissions.siteId', $this->siteId));
        }
        if ($this->nextFlagDate) {
            $this->subQuery->andWhere(Db::parseDateParam('neverstale_submissions.nextFlagDate', $this->nextFlagDate));
        }

        return parent::beforePrepare();
    }
}
