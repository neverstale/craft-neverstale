<?php

namespace zaengle\neverstale\records;

use Craft;
use craft\db\ActiveRecord;

/**
 * Submission record
 *
 * @property int $id ID
 * @property int|null $entryId Entry ID
 * @property int|null $siteId Site ID
 * @property string|null $neverstaleId Neverstale ID
 * @property string|null $transactionLog Status log
 * @property int|null $isSent Is sent
 * @property int|null $isFailed Is failed
 * @property int|null $isProcessed Is processed
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

        if ($this->flagTypes !== null) {
            $this->flagTypes = json_decode($this->flagTypes, true);
        }
    }

    public function getTransactionLog(): ?array
    {
        if ($this->transactionLog !== null) {
            return json_decode($this->transactionLog, true);
        }
        return null;
    }
    public function getFlagTypes(): ?array
    {
        if ($this->flagTypes !== null) {
            return json_decode($this->flagTypes, true);
        }
        return null;
    }
    public function getJobIds(): ?array
    {
        if ($this->jobIds !== null) {
            return json_decode($this->jobIds, true);
        }
        return null;
    }
    public function rules(): array
    {
        return [
            [['transactionLog', 'flagTypes', 'jobIds'], 'safe'],
        ];
    }
}
