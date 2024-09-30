<?php

namespace zaengle\neverstale\elements\db;

use craft\base\ElementInterface;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * Neverstale Submission query
 */
class NeverstaleSubmissionQuery extends ElementQuery
{
    public mixed $isSent;
    public mixed $isProcessed;
    public mixed $entryId;

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

    public function elementId(int $value): self
    {
        $this->entryId = $value;

        return $this;
    }

    public function element(ElementInterface $value): self
    {
        $this->entryId = $value->id;

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
        ]);

        if ($this->isSent) {
            $this->subQuery->andWhere(Db::parsebooleanparam('neverstale_submissions.isSent', $this->isSent));
        }

        if ($this->isProcessed) {
            $this->subQuery->andWhere(Db::parsebooleanparam('neverstale_submissions.isProcessed', $this->isProcessed));
        }

        if ($this->entryId) {
            $this->subQuery->andWhere(Db::parsenumericparam('neverstale_submissions.elementId', $this->entryId));
        }


        return parent::beforePrepare();
    }
}
