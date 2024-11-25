<?php

namespace zaengle\neverstale\traits;

use yii\db\ActiveQueryInterface;
use zaengle\neverstale\models\ApiTransaction;
use zaengle\neverstale\Plugin;
use zaengle\neverstale\records\Submission;

trait LogsApiTransactions
{
    public function logTransaction(ApiTransaction $transaction): bool
    {
        return Plugin::getInstance()->transactionLog->logTo($this, $transaction);
    }
    public function clearTransactionLog(): bool
    {
        return Plugin::getInstance()->transactionLog->deleteFor($this);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getTransactionLogs(): ActiveQueryInterface
    {
        return $this->getRecord()?->getTransactionLogs();
    }

    abstract public function getRecord(): ?Submission;
}
