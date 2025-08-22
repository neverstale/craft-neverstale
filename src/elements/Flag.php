<?php

namespace neverstale\neverstale\elements;

use Craft;
use craft\base\Element;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Cp;
use craft\helpers\UrlHelper;
use DateTime;
use neverstale\neverstale\elements\db\NeverstaleFlagQuery;
use neverstale\neverstale\enums\Permission;
use neverstale\neverstale\Plugin;
use neverstale\neverstale\records\Flag as FlagRecord;
use yii\base\InvalidConfigException;
use yii\web\Response;

/**
 * Neverstale Flag Element
 *
 * Represents a content analysis flag from the Neverstale API.
 * Flags indicate potential issues or concerns with content.
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   2.1.0
 *
 * @property int               $contentId
 * @property string            $flagId
 * @property string            $flag
 * @property string|null       $reason
 * @property string|null       $snippet
 * @property DateTime|null     $lastAnalyzedAt
 * @property DateTime|null     $expiredAt
 * @property DateTime|null     $ignoredAt
 * @property-read Content|null $content
 * @property-read bool         $isActive
 * @property-read bool         $isExpired
 */
class Flag extends Element
{
    public int $contentId;
    public string $flagId;
    public string $flag;
    public ?string $reason = null;
    public ?string $snippet = null;
    public ?DateTime $lastAnalyzedAt = null;
    public ?DateTime $expiredAt = null;
    public ?DateTime $ignoredAt = null;

    /**
     * @var Content|null Cached content element
     */
    private ?Content $_content = null;

    public static function displayName(): string
    {
        return Plugin::t('Neverstale Flag');
    }

    public static function lowerDisplayName(): string
    {
        return Plugin::t('neverstale flag');
    }

    public static function pluralLowerDisplayName(): string
    {
        return Plugin::t('neverstale flags');
    }

    public static function pluralDisplayName(): string
    {
        return Plugin::t('Neverstale Flags');
    }

    public static function refHandle(): ?string
    {
        return 'neverstale-flag';
    }

    public static function trackChanges(): bool
    {
        return true;
    }

    public static function hasStatuses(): bool
    {
        return true;
    }

    public static function statuses(): array
    {
        return [
            'active' => [
                'label' => Plugin::t('Active'),
                'color' => 'red',
            ],
            'ignored' => [
                'label' => Plugin::t('Ignored'),
                'color' => 'gray',
            ],
            'expired' => [
                'label' => Plugin::t('Expired'),
                'color' => 'yellow',
            ],
        ];
    }

    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => Plugin::t('All Flags'),
            ],
            [
                'key' => 'active',
                'label' => Plugin::t('Active Flags'),
                'criteria' => ['active' => true],
            ],
            [
                'key' => 'ignored',
                'label' => Plugin::t('Ignored Flags'),
                'criteria' => ['active' => false],
            ],
            [
                'key' => 'expired',
                'label' => Plugin::t('Expired Flags'),
                'criteria' => ['expired' => true],
            ],
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'flag' => ['label' => Plugin::t('Flag')],
            'reason' => ['label' => Plugin::t('Reason')],
            'content' => ['label' => Plugin::t('Neverstale Content')],
            'snippet' => ['label' => Plugin::t('Snippet')],
            'expiredAt' => ['label' => Plugin::t('Expires At')],
            'lastAnalyzedAt' => ['label' => Plugin::t('Last Analyzed')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('app', 'Date Updated')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'reason',
            'content',
            'expiredAt',
        ];
    }

    protected static function defineSortOptions(): array
    {
        return [
            [
                'label' => Plugin::t('Flag Type'),
                'orderBy' => 'neverstale_flags.flag',
                'attribute' => 'flag',
            ],
            [
                'label' => Plugin::t('Expires At'),
                'orderBy' => 'neverstale_flags.expiredAt',
                'attribute' => 'expiredAt',
                'defaultDir' => 'asc',
            ],
            [
                'label' => Plugin::t('Last Analyzed'),
                'orderBy' => 'neverstale_flags.lastAnalyzedAt',
                'attribute' => 'lastAnalyzedAt',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'Date Created'),
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
        ];
    }

    public function afterSave(bool $isNew): void
    {
        // Get the flag record
        if (! $isNew) {
            $record = FlagRecord::findOne($this->id);
            if (! $record) {
                $record = new FlagRecord();
                $record->id = $this->id;
            }
        } else {
            $record = new FlagRecord();
            $record->id = $this->id;
        }

        // Set record attributes
        $record->contentId = $this->contentId;
        $record->flagId = $this->flagId;
        $record->flag = $this->flag;
        $record->reason = $this->reason;
        $record->snippet = $this->snippet;
        $record->lastAnalyzedAt = $this->lastAnalyzedAt;
        $record->expiredAt = $this->expiredAt;
        $record->ignoredAt = $this->ignoredAt;

        $record->save(false);
        parent::afterSave($isNew);
    }

    public function getStatus(): ?string
    {
        if ($this->ignoredAt !== null) {
            return 'ignored';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        return 'active';
    }

    public function isExpired(): bool
    {
        return $this->expiredAt && $this->expiredAt < new DateTime();
    }

    public function getUiLabel(): string
    {
        // Format the flag type for better display (e.g., "missing_alt_text" -> "Missing Alt Text")
        $formatted = str_replace('_', ' ', $this->flag);

        return ucwords($formatted);
    }

    public function isActive(): bool
    {
        return $this->ignoredAt === null;
    }

    public function markAsIgnored(): bool
    {
        $this->ignoredAt = new DateTime();

        return Craft::$app->getElements()->saveElement($this);
    }

    public function updateExpiration(DateTime $newExpiredAt): bool
    {
        $this->expiredAt = $newExpiredAt;

        return Craft::$app->getElements()->saveElement($this);
    }

    public function canView($user): bool
    {
        if (parent::canView($user)) {
            return true;
        }

        return $user->can(Permission::View->value);
    }

    public function canSave($user): bool
    {
        return false; // Flags are managed through the API
    }

    public function canDelete($user): bool
    {
        return true; // Flags are managed through the API
    }

    public function canCreateDrafts($user): bool
    {
        return false;
    }

    public function canDuplicate($user): bool
    {
        return false;
    }

    public function getPostEditUrl(): ?string
    {
        // Redirect to the parent content details page after any flag operations
        $content = $this->getContent();
        if ($content) {
            return UrlHelper::cpUrl('neverstale/content/'.$content->getCanonicalId());
        }

        return UrlHelper::cpUrl('neverstale/content');
    }

    public function getContent(): ?Content
    {
        if ($this->_content === null && $this->contentId) {
            $this->_content = Content::findOne($this->contentId);
        }

        return $this->_content;
    }

    public function setContent(?Content $content): void
    {
        $this->_content = $content;
        $this->contentId = $content?->id ?? 0;
    }

    public function prepareEditScreen(Response $response, string $containerId): void
    {
        // Redirect to parent content details page instead of showing flag edit screen
        $content = $this->getContent();
        if ($content) {
            $response->redirect(UrlHelper::cpUrl('neverstale/content/'.$content->getCanonicalId()));

            return;
        }

        // Fallback to content index if no parent content found
        $response->redirect(UrlHelper::cpUrl('neverstale/content'));
    }


    /**
     * @throws InvalidConfigException
     */
    public static function find(): ElementQueryInterface
    {
        return Craft::createObject(NeverstaleFlagQuery::class, [static::class]);
    }



    protected function cpEditUrl(): ?string
    {
        // Link to the parent content details page instead of a flag-specific page
        $content = $this->getContent();
        if ($content) {
            return UrlHelper::cpUrl('neverstale/content/'.$content->getCanonicalId());
        }

        return null;
    }

    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'flag' => '<span class="status-badge" style="background: #fef2f2; color: #991b1b; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; display: inline-block; white-space: nowrap;">'.
                htmlspecialchars(ucwords(str_replace('_', ' ', $this->flag))).'</span>',
            'content' => $this->getContent() ? Cp::elementChipHtml($this->getContent()) : '',
            'reason' => $this->reason ? '<span title="'.htmlspecialchars($this->reason).'">'.
                (strlen($this->reason) > 50 ? substr($this->reason, 0, 50).'...' : $this->reason).'</span>' : '',
            'snippet' => $this->snippet ? '<code title="'.htmlspecialchars($this->snippet).'">'.
                (strlen($this->snippet) > 30 ? substr($this->snippet, 0, 30).'...' : $this->snippet).'</code>' : '',
            'expiredAt' => $this->expiredAt ? Craft::$app->formatter->asDatetime($this->expiredAt) : '',
            'lastAnalyzedAt' => $this->lastAnalyzedAt ? Craft::$app->formatter->asDatetime($this->lastAnalyzedAt) : '',
            default => parent::attributeHtml($attribute),
        };
    }
}
