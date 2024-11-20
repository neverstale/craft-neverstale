<?php

namespace zaengle\neverstale\records;

use Craft;
use craft\db\ActiveRecord;
use craft\helpers\Json;
use yii\db\ActiveQueryInterface;
use zaengle\neverstale\enums\AnalysisStatus;
use zaengle\neverstale\models\ApiTransaction;

/**
 * Submission record
 *
 * @property int $id ID
 * @property int|null $entryId Entry ID
 * @property int|null $siteId Site ID
 * @property string $analysisStatus Analysis status
 * @property string|null $neverstaleId Neverstale ID
 * @property int|null $flagCount Flag count
 * @property string|null $flagTypes Flag types
 * @property string|null $jobIds Queue job IDs
 * @property string|null $nextFlagDate Next flag date
 * @property string $dateCreated Date created
 * @property string $dateUpdated Date updated
 * @property string $uid Uid
 */
class Submission extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%neverstale_submissions}}';
    }
    public function init(): void
    {
        parent::init();
    }
    public function getFlagTypes()
    {
        return Json::decode($this->flagTypes) ?? [];
    }
    public function getJobIds()
    {
        return Json::decode($this->jobIds) ?? [];
    }
    public function getTransactions(): ActiveQueryInterface
    {
        return self::hasMany(Transaction::class, ['submissionId' => 'id']);
    }

    public function rules(): array
    {
        return [
            [['flagTypes', 'jobIds'], 'safe'],
        ];
    }
}
