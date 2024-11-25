<?php

namespace zaengle\neverstale\services;

use craft\helpers\App;
use yii\base\Component;
use yii\db\Exception;
use zaengle\neverstale\elements\NeverstaleSubmission;
use zaengle\neverstale\models\ApiTransaction as ApiTransactionModel;
use zaengle\neverstale\records\TransactionLog as TransactionLogRecord;

/**
 * Api Transaction service
 */
class TransactionLog extends Component
{
    /**
     * @throws Exception
     */
    public function logTo(NeverstaleSubmission $submission, ApiTransactionModel $apiTransaction): bool
    {
        $record = new TransactionLogRecord();

        $record->submissionId = $submission->id;
        $record->status = $apiTransaction->getAnalysisStatus()->value;
        $record->message = $apiTransaction->message;
        $record->event = $apiTransaction->event;

        if (App::devMode()) {
            $record->debugTransaction = $apiTransaction->content;
        }

        return $record->save();
    }
    public function deleteFor(NeverstaleSubmission $submission): bool
    {
        return TransactionLogRecord::deleteAll(['submissionId' => $submission->id]);
    }
}
