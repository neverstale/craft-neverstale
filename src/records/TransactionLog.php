<?php

namespace neverstale\neverstale\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Neverstale Transaction Log record
 *
 * This ActiveRecord represents the neverstale_transactions table which tracks
 * transaction history and debug information for content analysis operations.
 *
 * @property int $id ID
 * @property int|null $contentId Content ID
 * @property string|null $status Status
 * @property string|null $message Message
 * @property string|null $event Event
 * @property array|null $debugTransaction Debug transaction data only populated in dev mode
 * @property \DateTime $dateCreated Date created
 * @property \DateTime $dateUpdated Date updated
 * @property-read \yii\db\ActiveQueryInterface $content
 *
 * @author Zaengle
 * @package neverstale/neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
class TransactionLog extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%neverstale_transactions}}';
    }

    /**
     * Returns the related content record for this transaction log
     *
     * @return ActiveQueryInterface
     */
    public function getContent(): ActiveQueryInterface
    {
        return $this->hasOne(Content::class, ['id' => 'contentId']);
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['contentId'], 'integer'],
            [['contentId'], 'required'],
            [['status', 'event'], 'string', 'max' => 255],
            [['message'], 'string'],
            [['debugTransaction'], 'safe'],
            [['debugTransaction'], 'default', 'value' => null],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'contentId' => 'Content ID',
            'status' => 'Status',
            'message' => 'Message',
            'event' => 'Event',
            'debugTransaction' => 'Debug Transaction',
            'dateCreated' => 'Date Created',
            'dateUpdated' => 'Date Updated',
        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert): bool
    {
        if (parent::beforeSave($insert)) {
            // Ensure debugTransaction is properly encoded as JSON if it's an array
            if (is_array($this->debugTransaction)) {
                $this->debugTransaction = $this->debugTransaction;
            }
            return true;
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function afterFind()
    {
        parent::afterFind();
        
        // Ensure debugTransaction is properly decoded from JSON
        if (is_string($this->debugTransaction)) {
            $decoded = json_decode($this->debugTransaction, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->debugTransaction = $decoded;
            }
        }
    }
}