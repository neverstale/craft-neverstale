<?php

namespace zaengle\neverstale\records;

use Craft;
use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;
use zaengle\neverstale\elements\NeverstaleSubmission;

/**
 * Transaction record
 *
 * @property int $id ID
 * @property int|null $submissionId Submission ID
 * @property string|null $status Status
 * @property string|null $message Message
 * @property string|null $event Event
 * @property string $dateCreated Date created
 * @property-read \yii\db\ActiveQueryInterface $submission
 * @property string $dateUpdated Date updated
 * @property array|null $debugTransaction Debug transaction data only populated in dev mode
 */
class TransactionLog extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%neverstale_transactions}}';
    }

    public function getSubmission(): ActiveQueryInterface
    {
        return self::hasOne(NeverstaleSubmission::class, ['id' => 'submissionId']);
    }
}

