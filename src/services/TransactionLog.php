<?php

namespace neverstale\craft\services;

use craft\helpers\App;
use yii\base\Component;
use yii\db\Exception;
use neverstale\craft\elements\NeverstaleContent;
use neverstale\craft\models\TransactionLogItem;
use neverstale\craft\records\TransactionLog as TransactionLogRecord;

/**
 * Api Transaction service
 */
class TransactionLog extends Component
{
    /**
     * @throws Exception
     */
    public function logTo(NeverstaleContent $content, TransactionLogItem $logItem): bool
    {
        $record = new TransactionLogRecord();
        $record->contentId = $content->id;
        $record->status = $logItem->getAnalysisStatus()->value;
        $record->message = $logItem->message;
        $record->event = $logItem->event;
        $record->debugTransaction = $logItem->content;

        // Temporarily disabling this so we get debugTransaction in the db
        //        if (App::devMode()) {
        //            $record->debugTransaction = $logItem->content;
        //        }

        return $record->save();
    }
    public function deleteFor(NeverstaleContent $content): bool
    {
        TransactionLogRecord::deleteAll(['contentId' => $content->id]);

        return true;
    }
}
