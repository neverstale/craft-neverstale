<?php

namespace neverstale\craft\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;
use neverstale\craft\elements\NeverstaleContent;

/**
 * Transaction record
 *
 * @property int $id ID
 * @property int|null $contentId Content ID
 * @property string|null $status Status
 * @property string|null $message Message
 * @property string|null $event Event
 * @property string $dateCreated Date created
 * @property-read \yii\db\ActiveQueryInterface $content
 * @property string $dateUpdated Date updated
 * @property array|null $debugTransaction Debug transaction data only populated in dev mode
 */
class TransactionLog extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%neverstale_transactions}}';
    }

    public function getContent(): ActiveQueryInterface
    {
        return self::hasOne(NeverstaleContent::class, ['id' => 'contentId']);
    }
}
