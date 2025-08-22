<?php

namespace neverstale\neverstale\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use DateTime;

/**
 * Flag element query class
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   2.1.0
 *
 *          TODO - rename to FlagQuery
 */
class NeverstaleFlagQuery extends ElementQuery
{
    /**
     * @var int|int[]|null The content ID(s) to filter by
     */
    public mixed $contentId = null;

    /**
     * @var string|string[]|null The flag ID(s) to filter by
     */
    public mixed $flagId = null;

    /**
     * @var string|string[]|null The flag type(s) to filter by
     */
    public mixed $flag = null;

    /**
     * @var bool|null Whether to only return active (non-ignored) flags
     */
    public ?bool $active = null;

    /**
     * @var bool|null Whether to only return expired flags
     */
    public ?bool $expired = null;

    /**
     * @var DateTime|string|null Filter flags that expire after this date
     */
    public mixed $expiresAfter = null;

    /**
     * @var DateTime|string|null Filter flags that expire before this date
     */
    public mixed $expiresBefore = null;

    /**
     * Filter by content ID
     *
     * @param  int|int[]|null  $value
     * @return static
     */
    public function contentId(mixed $value): static
    {
        $this->contentId = $value;

        return $this;
    }

    /**
     * Filter by flag ID
     *
     * @param  string|string[]|null  $value
     * @return static
     */
    public function flagId(mixed $value): static
    {
        $this->flagId = $value;

        return $this;
    }

    /**
     * Filter by flag type
     *
     * @param  string|string[]|null  $value
     * @return static
     */
    public function flag(mixed $value): static
    {
        $this->flag = $value;

        return $this;
    }

    /**
     * Filter by active status (non-ignored flags)
     *
     * @param  bool|null  $value
     * @return static
     */
    public function active(?bool $value = true): static
    {
        $this->active = $value;

        return $this;
    }

    /**
     * Filter by expired status
     *
     * @param  bool|null  $value
     * @return static
     */
    public function expired(?bool $value = true): static
    {
        $this->expired = $value;

        return $this;
    }

    /**
     * Filter flags that expire after a specific date
     *
     * @param  DateTime|string|null  $value
     * @return static
     */
    public function expiresAfter(mixed $value): static
    {
        $this->expiresAfter = $value;

        return $this;
    }

    /**
     * Filter flags that expire before a specific date
     *
     * @param  DateTime|string|null  $value
     * @return static
     */
    public function expiresBefore(mixed $value): static
    {
        $this->expiresBefore = $value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('neverstale_flags');

        $this->query->select([
            'neverstale_flags.contentId',
            'neverstale_flags.flagId',
            'neverstale_flags.flag',
            'neverstale_flags.reason',
            'neverstale_flags.snippet',
            'neverstale_flags.lastAnalyzedAt',
            'neverstale_flags.expiredAt',
            'neverstale_flags.ignoredAt',
        ]);

        // Apply content ID filter
        if ($this->contentId !== null) {
            $this->subQuery->andWhere(Db::parseParam('neverstale_flags.contentId', $this->contentId));
        }

        // Apply flag ID filter
        if ($this->flagId !== null) {
            $this->subQuery->andWhere(Db::parseParam('neverstale_flags.flagId', $this->flagId));
        }

        // Apply flag type filter
        if ($this->flag !== null) {
            $this->subQuery->andWhere(Db::parseParam('neverstale_flags.flag', $this->flag));
        }

        // Apply active filter (non-ignored flags)
        if ($this->active !== null) {
            if ($this->active) {
                $this->subQuery->andWhere(['neverstale_flags.ignoredAt' => null]);
            } else {
                $this->subQuery->andWhere(['not', ['neverstale_flags.ignoredAt' => null]]);
            }
        }

        // Apply expired filter
        if ($this->expired !== null) {
            $now = (new DateTime())->format('Y-m-d H:i:s');
            if ($this->expired) {
                $this->subQuery->andWhere(['<', 'neverstale_flags.expiredAt', $now]);
            } else {
                $this->subQuery->andWhere(['>=', 'neverstale_flags.expiredAt', $now]);
            }
        }

        // Apply expires after filter
        if ($this->expiresAfter !== null) {
            $date = $this->expiresAfter instanceof DateTime ?
                $this->expiresAfter->format('Y-m-d H:i:s') :
                $this->expiresAfter;
            $this->subQuery->andWhere(['>=', 'neverstale_flags.expiredAt', $date]);
        }

        // Apply expires before filter
        if ($this->expiresBefore !== null) {
            $date = $this->expiresBefore instanceof DateTime ?
                $this->expiresBefore->format('Y-m-d H:i:s') :
                $this->expiresBefore;
            $this->subQuery->andWhere(['<=', 'neverstale_flags.expiredAt', $date]);
        }

        return parent::beforePrepare();
    }
}
