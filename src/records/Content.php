<?php

namespace zaengle\neverstale\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Neverstale Content record
 *
 * @property int $id ID
 * @property int|null $entryId Entry ID
 * @property int|null $siteId Site ID
 * @property string|null $neverstaleId Neverstale ID
 * @property string $uid Uid
 * @property string $analysisStatus Analysis status
 * @property int|null $flagCount Flag count
 * @property \DateTime|null $dateAnalyzed Last analyzed at
 * @property \DateTime|null $dateExpired Content expired at date
 * @property \DateTime $dateCreated Date created
 * @property \DateTime $dateUpdated Date updated
 * @property-read \yii\db\ActiveQueryInterface $transactions
 */
class Content extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%neverstale_content}}';
    }
    public function init(): void
    {
        parent::init();
    }
    public function getTransactionLogs(): ActiveQueryInterface
    {
        return self::hasMany(TransactionLog::class, ['contentId' => 'id']);
    }

    public function rules(): array
    {
        return [];
    }
}
