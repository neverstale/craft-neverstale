<?php

namespace neverstale\neverstale\traits;

use neverstale\neverstale\models\TransactionLogItem;
use neverstale\neverstale\Plugin;
use neverstale\neverstale\records\Content;
use yii\db\ActiveQueryInterface;

trait LogsTransactions
{
    public function logTransaction(TransactionLogItem $transaction): bool
    {
        return Plugin::getInstance()->transactionLog->logTo($this, $transaction);
    }

    public function clearTransactionLog(): bool
    {
        return Plugin::getInstance()->transactionLog->deleteFor($this);
    }

    /**
     * Get all transaction log records as an array
     *
     * @return array
     */
    public function getAllTransactionLogs(): array
    {
        $query = $this->getTransactionLogs();

        return $query ? $query->all() : [];
    }

    /**
     * @return ActiveQueryInterface|null
     */
    public function getTransactionLogs(): ?ActiveQueryInterface
    {
        $record = $this->getRecord();

        return $record?->getTransactionLogs();
    }

    abstract public function getRecord(): ?Content;
}
