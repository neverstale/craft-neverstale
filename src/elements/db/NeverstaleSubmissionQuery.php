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
    public mixed $dateExpired = null;
    public mixed $dateAnalyzed = null;

    public mixed $hasFlags = null;
    public mixed $isExpired = null;
    public mixed $isAnalyzed = null;


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

    public function dateExpired(mixed $value): self
    {
        $this->dateExpired = $value;

        return $this;
    }
    public function dateAnalyzed(mixed $value): self
    {
        $this->dateAnalyzed = $value;

        return $this;
    }

    public function hasFlags(mixed $value): self
    {
        $this->hasFlags = $value;
    }
    public function isExpired(mixed $value): self
    {
        $this->isExpired = $value;
    }
    public function isAnalyzed(mixed $value): self
    {
        $this->isAnalyzed = $value;
    }

    protected function statusCondition(string $status): mixed
    {
        return match (AnalysisStatus::tryFrom($status) ?? $status) {
            AnalysisStatus::UNSENT,
            AnalysisStatus::PENDING_INITIAL_ANALYSIS,
            AnalysisStatus::PENDING_REANALYSIS,
            AnalysisStatus::PROCESSING_REANALYSIS,
            AnalysisStatus::ANALYZED_CLEAN,
            AnalysisStatus::ANALYZED_FLAGGED,
            AnalysisStatus::ANALYZED_ERROR,
            AnalysisStatus::UNKNOWN,
            AnalysisStatus::API_ERROR => [
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
            'neverstale_submissions.dateAnalyzed',
            'neverstale_submissions.dateExpired',
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
        if ($this->dateExpired) {
            $this->subQuery->andWhere(Db::parseDateParam('neverstale_submissions.dateExpired', $this->dateExpired));
        }
        if ($this->dateAnalyzed) {
            $this->subQuery->andWhere(Db::parseDateParam('neverstale_submissions.dateAnalyzed', $this->dateAnalyzed));
        }
        if ($this->hasFlags) {
            $this->subQuery->andFilterCompare('neverstale_submissions.flagCount', 0, '>');
        }
        if ($this->isAnalyzed) {
            // @todo
        }
        if ($this->isExpired) {
            // @todo
        }

        return parent::beforePrepare();
    }
}
