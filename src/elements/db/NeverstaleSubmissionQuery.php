<?php

namespace zaengle\neverstale\elements\db;

use craft\elements\db\ElementQuery;
use craft\elements\Entry;
use craft\helpers\Db;

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
    public mixed $entryId = null;
    public mixed $siteId = null;

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
        // @todo
        return match ($status) {
            'foo' => ['foo' => true],
            'bar' => ['bar' => true],
            default => parent::statusCondition($status),
        };
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('neverstale_submissions');

        $this->query->select([
            'neverstale_submissions.isSent',
            'neverstale_submissions.isProcessed',
            'neverstale_submissions.entryId',
            'neverstale_submissions.siteId',
        ]);

        if ($this->isSent) {
            $this->subQuery->andWhere(Db::parsebooleanparam('neverstale_submissions.isSent', $this->isSent));
        }

        if ($this->isProcessed) {
            $this->subQuery->andWhere(Db::parsebooleanparam('neverstale_submissions.isProcessed', $this->isProcessed));
        }

        if ($this->entryId) {
            $this->subQuery->andWhere(Db::parsenumericparam('neverstale_submissions.entryId', $this->entryId));
        }

        if ($this->siteId) {
            $this->subQuery->andWhere(Db::parsenumericparam('neverstale_submissions.siteId', $this->siteId));
        }

        return parent::beforePrepare();
    }
}
