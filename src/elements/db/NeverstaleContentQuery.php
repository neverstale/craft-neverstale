<?php

namespace zaengle\neverstale\elements\db;

use craft\elements\db\ElementQuery;
use craft\elements\Entry;
use craft\helpers\Db;
use zaengle\neverstale\enums\AnalysisStatus;

/**
 * Neverstale Content Query
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
class NeverstaleContentQuery extends ElementQuery
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
                'neverstale_content.analysisStatus' => $status,
            ],
            default => parent::statusCondition($status),
        };
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('neverstale_content');

        $this->query->select([
            'neverstale_content.analysisStatus',
            'neverstale_content.dateAnalyzed',
            'neverstale_content.dateExpired',
            'neverstale_content.flagCount',
            'neverstale_content.entryId',
            'neverstale_content.neverstaleId',
            'neverstale_content.siteId',
        ]);

        if ($this->analysisStatus) {
            $this->subQuery->andWhere(Db::parsebooleanparam('neverstale_content.analysisStatus', $this->analysisStatus));
        }
        if ($this->flagCount) {
            $this->subQuery->andWhere(Db::parsenumericparam('neverstale_content.flagCount', $this->flagCount));
        }
        if ($this->entryId) {
            $this->subQuery->andWhere(Db::parsenumericparam('neverstale_content.entryId', $this->entryId));
        }
        if ($this->neverstaleId) {
            $this->subQuery->andWhere(Db::parseParam('neverstale_content.neverstaleId', $this->neverstaleId));
        }
        if ($this->siteId) {
            $this->subQuery->andWhere(Db::parsenumericparam('neverstale_content.siteId', $this->siteId));
        }
        if ($this->dateExpired) {
            $this->subQuery->andWhere(Db::parseDateParam('neverstale_content.dateExpired', $this->dateExpired));
        }
        if ($this->dateAnalyzed) {
            $this->subQuery->andWhere(Db::parseDateParam('neverstale_content.dateAnalyzed', $this->dateAnalyzed));
        }
        if ($this->hasFlags) {
            $this->subQuery->andFilterCompare('neverstale_content.flagCount', 0, '>');
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
