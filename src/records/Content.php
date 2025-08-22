<?php

namespace neverstale\neverstale\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;
use neverstale\neverstale\enums\AnalysisStatus;

/**
 * Neverstale Content record
 *
 * This ActiveRecord represents the neverstale_content table which tracks
 * content analysis status and metadata for Craft CMS entries.
 *
 * @property int $id ID
 * @property int|null $entryId Entry ID
 * @property int|null $siteId Site ID
 * @property string|null $neverstaleId Neverstale API ID
 * @property string $uid Uid
 * @property string $analysisStatus Analysis status
 * @property int|null $flagCount Flag count
 * @property \DateTime|null $dateAnalyzed Last analyzed at
 * @property \DateTime|null $dateExpired Content expired at date
 * @property \DateTime $dateCreated Date created
 * @property \DateTime $dateUpdated Date updated
 * @property-read \yii\db\ActiveQueryInterface $transactionLogs
 *
 * @author Zaengle
 * @package neverstale/neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
class Content extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%neverstale_content}}';
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
    }

    /**
     * Returns the related transaction logs for this content record
     *
     * @return ActiveQueryInterface
     */
    public function getTransactionLogs(): ActiveQueryInterface
    {
        return $this->hasMany(TransactionLog::class, ['contentId' => 'id'])->orderBy(['dateCreated' => SORT_DESC]);
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['entryId', 'siteId', 'flagCount'], 'integer'],
            [['entryId', 'siteId'], 'required'],
            [['neverstaleId', 'analysisStatus'], 'string'],
            [['analysisStatus'], 'default', 'value' => AnalysisStatus::UNSENT->value],
            [['analysisStatus'], 'in', 'range' => array_map(fn($status) => $status->value, AnalysisStatus::cases())],
            [['flagCount'], 'integer', 'min' => 0],
            [['dateAnalyzed', 'dateExpired'], 'datetime'],
            [['uid'], 'string', 'max' => 36],
            [['neverstaleId'], 'string', 'max' => 255],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'entryId' => 'Entry ID',
            'siteId' => 'Site ID',
            'neverstaleId' => 'Neverstale ID',
            'uid' => 'UID',
            'analysisStatus' => 'Analysis Status',
            'flagCount' => 'Flag Count',
            'dateAnalyzed' => 'Date Analyzed',
            'dateExpired' => 'Date Expired',
            'dateCreated' => 'Date Created',
            'dateUpdated' => 'Date Updated',
        ];
    }

    /**
     * Get the analysis status enum value
     *
     * @return AnalysisStatus
     */
    public function getAnalysisStatusEnum(): AnalysisStatus
    {
        return AnalysisStatus::tryFrom($this->analysisStatus) ?? AnalysisStatus::UNKNOWN;
    }

    /**
     * Set the analysis status from enum
     *
     * @param AnalysisStatus $status
     * @return void
     */
    public function setAnalysisStatusEnum(AnalysisStatus $status): void
    {
        $this->analysisStatus = $status->value;
    }
}