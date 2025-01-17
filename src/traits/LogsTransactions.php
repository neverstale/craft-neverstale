<?php

namespace neverstale\craft\traits;

use yii\db\ActiveQueryInterface;
use neverstale\craft\models\TransactionLogItem;
use neverstale\craft\Plugin;
use neverstale\craft\records\Content;

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
     * @return ActiveQueryInterface
     */
    public function getTransactionLogs(): ActiveQueryInterface
    {
        return $this->getRecord()?->getTransactionLogs();
    }

    abstract public function getRecord(): ?Content;
}
