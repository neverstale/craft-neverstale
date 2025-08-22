<?php

namespace neverstale\neverstale\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;
use DateTime;

/**
 * Neverstale Flag record
 *
 * This ActiveRecord represents the neverstale_flags table which stores
 * individual flag details for content analysis results.
 *
 * @property int $id ID
 * @property int $contentId Reference to neverstale_content.id
 * @property string $flagId Neverstale API flag ID
 * @property string $flag Flag type/category
 * @property string|null $reason Reason for the flag
 * @property string|null $snippet Content snippet that triggered the flag
 * @property DateTime|null $lastAnalyzedAt When this flag was last analyzed
 * @property DateTime|null $expiredAt When this flag expires
 * @property DateTime|null $ignoredAt When this flag was ignored (null if active)
 * @property string $uid Uid
 * @property DateTime $dateCreated Date created
 * @property DateTime $dateUpdated Date updated
 * @property-read Content $content Related content record
 *
 * @author Zaengle
 * @package neverstale/neverstale
 * @since 2.1.0
 */
class Flag extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%neverstale_flags}}';
    }

    /**
     * Returns the related content record
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
            [['contentId'], 'integer', 'min' => 1],
            [['contentId', 'flagId', 'flag'], 'required'],
            [['flagId', 'flag'], 'string', 'max' => 255],
            [['reason', 'snippet'], 'string'],
            [['lastAnalyzedAt', 'expiredAt', 'ignoredAt'], 'datetime'],
            [['uid'], 'string', 'max' => 36],
            [['flagId'], 'unique'], // Each flag should be unique across the system
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
            'flagId' => 'Flag ID',
            'flag' => 'Flag Type',
            'reason' => 'Reason',
            'snippet' => 'Snippet',
            'lastAnalyzedAt' => 'Last Analyzed At',
            'expiredAt' => 'Expires At',
            'ignoredAt' => 'Ignored At',
            'dateCreated' => 'Date Created',
            'dateUpdated' => 'Date Updated',
        ];
    }

    /**
     * Check if this flag is currently active (not ignored)
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->ignoredAt === null;
    }

    /**
     * Check if this flag has expired
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expiredAt && $this->expiredAt < new DateTime();
    }

    /**
     * Mark this flag as ignored
     *
     * @return bool
     */
    public function markAsIgnored(): bool
    {
        $this->ignoredAt = new DateTime();
        return $this->save(false);
    }

    /**
     * Update the expiration date
     *
     * @param DateTime $newExpiredAt
     * @return bool
     */
    public function updateExpiration(DateTime $newExpiredAt): bool
    {
        $this->expiredAt = $newExpiredAt;
        return $this->save(false);
    }
}