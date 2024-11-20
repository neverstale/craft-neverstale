<?php

namespace zaengle\neverstale\traits;

use zaengle\neverstale\models\ApiTransaction;
use zaengle\neverstale\records\Submission;

trait LogsApiTransactions
{
    protected array $transactionLog = [];
    public function logTransaction(ApiTransaction $item): void
    {
        $this->transactionLog[] = $item->toArray([
            'transactionStatus',
            'message',
            'neverstaleId',
            'channelId',
            'customId',
            'createdAt'
        ]);
    }

    /**
     * @return array<ApiTransaction>
     */
    public function getTransactionLog(): array
    {
        return $this->getRecord()?->getTransactionLog() ?? [];
    }

    abstract public function getRecord(): ?Submission;
}
