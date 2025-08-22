<?php

namespace neverstale\neverstale\elements\db;

use craft\elements\db\ElementQuery;
use craft\elements\Entry;
use craft\helpers\Db;
use DateTime;
use neverstale\neverstale\enums\AnalysisStatus;

/**
 * Neverstale Content Query
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   1.0.0
 * @see     https://github.com/zaengle/craft-neverstale
 */
class ContentQuery extends ElementQuery
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

    /**
     * Filter by entry ID
     *
     * @param  mixed  $value
     * @return static
     */
    public function entryId(mixed $value): self
    {
        $this->entryId = $value;

        return $this;
    }

    /**
     * Filter by entry element
     *
     * @param  Entry  $value
     * @return static
     */
    public function entry(Entry $value): self
    {
        $this->entryId = $value->id;
        $this->siteId = $value->siteId;

        return $this;
    }

    /**
     * Filter by site ID
     *
     * @param  mixed  $value
     * @return static
     */
    public function siteId($value): static
    {
        $this->siteId = $value;

        return $this;
    }

    /**
     * Filter by Neverstale ID
     *
     * @param  string  $value
     * @return static
     */
    public function neverstaleId(string $value): static
    {
        $this->neverstaleId = $value;

        return $this;
    }

    /**
     * Filter by analysis status
     *
     * @param  mixed  $value
     * @return static
     */
    public function analysisStatus(mixed $value): static
    {
        $this->analysisStatus = $value;

        return $this;
    }

    /**
     * Filter by flag count
     *
     * @param  mixed  $value
     * @return static
     */
    public function flagCount(mixed $value): static
    {
        $this->flagCount = $value;

        return $this;
    }

    /**
     * Filter by date expired
     *
     * @param  mixed  $value
     * @return static
     */
    public function dateExpired(mixed $value): static
    {
        $this->dateExpired = $value;

        return $this;
    }

    /**
     * Filter by date analyzed
     *
     * @param  mixed  $value
     * @return static
     */
    public function dateAnalyzed(mixed $value): static
    {
        $this->dateAnalyzed = $value;

        return $this;
    }

    /**
     * Filter content that has flags
     *
     * @param  mixed  $value
     * @return static
     */
    public function hasFlags(mixed $value = true): static
    {
        $this->hasFlags = $value;

        return $this;
    }

    /**
     * Filter content that is expired
     *
     * @param  mixed  $value
     * @return static
     */
    public function isExpired(mixed $value = true): static
    {
        $this->isExpired = $value;

        return $this;
    }

    /**
     * Filter content that has been analyzed
     *
     * @param  mixed  $value
     * @return static
     */
    public function isAnalyzed(mixed $value = true): static
    {
        $this->isAnalyzed = $value;

        return $this;
    }

    /**
     * Handle status conditions for Neverstale-specific statuses
     *
     * @param  string  $status
     * @return mixed
     */
    protected function statusCondition(string $status): mixed
    {
        return match (AnalysisStatus::tryFrom($status) ?? $status) {
            AnalysisStatus::UNSENT,
            AnalysisStatus::PENDING_INITIAL_ANALYSIS,
            AnalysisStatus::PENDING_REANALYSIS,
            AnalysisStatus::PROCESSING_REANALYSIS,
            AnalysisStatus::PROCESSING_INITIAL_ANALYSIS,
            AnalysisStatus::ANALYZED_CLEAN,
            AnalysisStatus::ANALYZED_FLAGGED,
            AnalysisStatus::ANALYZED_ERROR,
            AnalysisStatus::API_ERROR,
            AnalysisStatus::ARCHIVED,
            AnalysisStatus::STALE,
            AnalysisStatus::UNKNOWN => [
                'neverstale_content.analysisStatus' => $status,
            ],
            default => parent::statusCondition($status),
        };
    }

    /**
     * Prepare the query before execution
     *
     * @return bool
     */
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

        if ($this->analysisStatus !== null) {
            $this->subQuery->andWhere(Db::parseParam('neverstale_content.analysisStatus', $this->analysisStatus));
        }

        if ($this->flagCount !== null) {
            $this->subQuery->andWhere(Db::parseNumericParam('neverstale_content.flagCount', $this->flagCount));
        }

        if ($this->entryId !== null) {
            $this->subQuery->andWhere(Db::parseNumericParam('neverstale_content.entryId', $this->entryId));
        }

        if ($this->neverstaleId !== null) {
            $this->subQuery->andWhere(Db::parseParam('neverstale_content.neverstaleId', $this->neverstaleId));
        }

        if ($this->siteId !== null) {
            $this->subQuery->andWhere(Db::parseNumericParam('neverstale_content.siteId', $this->siteId));
        }

        if ($this->dateExpired !== null) {
            $this->subQuery->andWhere(Db::parseDateParam('neverstale_content.dateExpired', $this->dateExpired));
        }

        if ($this->dateAnalyzed !== null) {
            $this->subQuery->andWhere(Db::parseDateParam('neverstale_content.dateAnalyzed', $this->dateAnalyzed));
        }

        if ($this->hasFlags) {
            $this->subQuery->andWhere(['>', 'neverstale_content.flagCount', 0]);
        } elseif ($this->hasFlags !== null && ! $this->hasFlags) {
            $this->subQuery->andWhere(['or',
                ['neverstale_content.flagCount' => null],
                ['neverstale_content.flagCount' => 0],
            ]);
        }

        if ($this->isAnalyzed) {
            $this->subQuery->andWhere(['in', 'neverstale_content.analysisStatus', [
                AnalysisStatus::ANALYZED_CLEAN->value,
                AnalysisStatus::ANALYZED_FLAGGED->value,
                AnalysisStatus::ANALYZED_ERROR->value,
            ]]);
        } elseif ($this->isAnalyzed !== null && ! $this->isAnalyzed) {
            $this->subQuery->andWhere(['not in', 'neverstale_content.analysisStatus', [
                AnalysisStatus::ANALYZED_CLEAN->value,
                AnalysisStatus::ANALYZED_FLAGGED->value,
                AnalysisStatus::ANALYZED_ERROR->value,
            ]]);
        }

        if ($this->isExpired) {
            $this->subQuery->andWhere(['<=', 'neverstale_content.dateExpired', new DateTime()]);
        } elseif ($this->isExpired !== null && ! $this->isExpired) {
            $this->subQuery->andWhere(['or',
                ['neverstale_content.dateExpired' => null],
                ['>', 'neverstale_content.dateExpired', new DateTime()],
            ]);
        }

        return parent::beforePrepare();
    }
}
