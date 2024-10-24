<?php

namespace zaengle\neverstale\elements;

use Craft;
use craft\base\Element;
use craft\db\Query;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\EagerLoadPlan;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\Db;
use craft\helpers\UrlHelper;
use craft\web\CpScreenResponseBehavior;
use DateTime;
use yii\base\InvalidConfigException;
use yii\web\Response;
use zaengle\neverstale\elements\conditions\NeverstaleSubmissionCondition;
use zaengle\neverstale\elements\db\NeverstaleSubmissionQuery;
use zaengle\neverstale\enums\Permission;
use zaengle\neverstale\enums\SubmissionStatus;
use zaengle\neverstale\models\ApiSubmission;
use zaengle\neverstale\Plugin;

/**
 * Neverstale Submission Custom Element Type
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 *
 * @property-read null|string $entryCpUrl
 * @property-read null|string $postEditUrl
 */
class NeverstaleSubmission extends Element
{
    public int $entryId;
    public int|null $siteId = null;
    public bool $isSent = false;
    public bool $isProcessed = false;
    public int $flagCount = 0;

    private ?Entry $entry = null;

    /**
     * @var array<string>
     */
    public array $flagTypes = [];

    public ?DateTime $nextFlagDate;

    public static function displayName(): string
    {
        return Plugin::t('Neverstale Submission');
    }
    public static function lowerDisplayName(): string
    {
        return Plugin::t('Neverstale submission');
    }
    public static function pluralDisplayName(): string
    {
        return Plugin::t('Neverstale Submissions');
    }
    public static function pluralLowerDisplayName(): string
    {
        return Plugin::t('Neverstale submissions');
    }
    public static function refHandle(): ?string
    {
        return 'neverstale-submission';
    }
    public static function trackChanges(): bool
    {
        return true;
    }

    public static function hasTitles(): bool
    {
        return false;
    }

    public static function hasUris(): bool
    {
        return false;
    }

    public static function isLocalized(): bool
    {
        return false;
    }

    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function statuses(): array
    {
        return collect(SubmissionStatus::cases())
            ->reduce(function($statuses, $status): array {
                $statuses[$status->value] = [
                    'label' => $status->label(),
                    'color' => $status->color(),
                ];
                return $statuses;
            }, []);
    }
    public static function eagerLoadingMap(array $sourceElements, string $handle): array|null|false
    {
        // Memoize the source element IDs:
        $sourceElementIds = ArrayHelper::getColumn($sourceElements, 'id');

        // The “handle” is the key that users will specify
        // when eager-loading this relationship:
        if ($handle === 'neverstaleSubmission') {
            // Do a fresh selection from the submissions table
            // to create a map of submission IDs to entry IDs,
            // excluding submissions with no entry ID:
            $map = (new Query())
                ->select(['id as source', 'entryId as target'])
                ->from(['{{%neverstale_submissions}}'])
                ->where([
                    'and',
                    ['id' => $sourceElementIds],
                    ['not', ['entryId' => null]],
                ])
                ->orderBy(['dateCreated' => SORT_DESC])
                ->all();

            return [
                'elementType' => Entry::class,
                'map' => $map,
            ];
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            Plugin::log("Saving submission $this->id", 'info');
            // @todo perhaps move this to a record?
            $rows = Db::upsert('{{%neverstale_submissions}}', [
                'id' => $this->id,
                'entryId' => $this->entryId,
                'siteId' => $this->siteId,
            ], [
                'isSent' => $this->isSent,
                'isProcessed' => $this->isProcessed,
            ]);

            Plugin::log("Saved $rows submission", 'info');
        }

        parent::afterSave($isNew);
    }

    public function setEagerLoadedElements(string $handle, array $elements, EagerLoadPlan $plan): void
    {
        // The handle can be anything, so long as it matches what is used in `eagerLoadingMap()`:
        if ($handle === 'entry') {
            $entry = $elements[0] ?? null;
            $this->setEntry($entry);
        } else {
            parent::setEagerLoadedElements($handle, $elements, $plan);
        }
    }

    public function getStatus(): ?string
    {
        if (!$this->isSent) {
            return SubmissionStatus::Pending->value;
        }

        if (!$this->isProcessed) {
            return SubmissionStatus::Processing->value;
        }

        if ($this->flagCount > 0) {
            return SubmissionStatus::Flagged->value;
        }

        return SubmissionStatus::Clean->value;
    }

    public function setEntry(Entry $entry = null): void
    {
        $this->entry = $entry;
        $this->entryId = $entry?->id;
    }

    public function getEntry(): ?Entry
    {
        if ($this->entry !== null) {
            return $this->entry;
        }

        if (!$this->entryId) {
            return null;
        }

        return $this->entry = Craft::$app->getEntries()->getEntryById($this->entryId, $this->siteId);
    }

    public function getUiLabel(): string
    {
        return "#{$this->id}: {$this->getEntry()?->title}";
    }

    /**
     * @throws InvalidConfigException
     */
    public static function find(): ElementQueryInterface
    {
        return Craft::createObject(NeverstaleSubmissionQuery::class, [static::class]);
    }

    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(NeverstaleSubmissionCondition::class, [static::class]);
    }

    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => Plugin::t('All Neverstale Submissions'),
            ],
        ];
    }

    protected static function defineActions(string $source): array
    {
        return [];
    }

    protected static function includeSetStatusAction(): bool
    {
        return false;
    }

    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            'slug' => Craft::t('app', 'Slug'),
            'uri' => Craft::t('app', 'URI'),
            [
                'label' => Craft::t('app', 'Date Created'),
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'Date Updated'),
                'orderBy' => 'elements.dateUpdated',
                'attribute' => 'dateUpdated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'ID'),
                'orderBy' => 'elements.id',
                'attribute' => 'id',
            ],
            // ...
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'id' => ['label' => Craft::t('app', 'ID')],
            'status' => ['label' => Craft::t('app', 'Status')],
            'entry' => ['label' => Craft::t('app', 'Entry')],
            'uid' => ['label' => Craft::t('app', 'UID')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('app', 'Date Updated')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'entry',
            'status',
            'dateCreated',
            'dateUpdated',
        ];
    }

    protected function tableAttributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'entry' => Cp::elementChipHtml($this->getEntry()),
            default => parent::tableAttributeHtml($attribute),
        };
    }

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // ...
        ]);
    }

    public function getUriFormat(): ?string
    {
        // If submissions should have URLs, define their URI format here
        return null;
    }

    protected function route(): array|string|null
    {
        // Define how submissions should be routed when their URLs are requested
        return [
            'templates/render',
            [
                'template' => 'site/template/path',
                'variables' => ['submission' => $this],
            ],
        ];
    }

    public function canView(User $user): bool
    {
        if (parent::canView($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('viewSubmissions');
    }

    public function canSave(User $user): bool
    {
        return false;
    }

    public function canDuplicate(User $user): bool
    {
        return false;
    }

    public function canDelete(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can(Permission::Delete->value);
    }

    public function canCreateDrafts(User $user): bool
    {
        return false;
    }

    protected function cpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('neverstale/submissions/' . $this->getCanonicalId());
    }

    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('submissions');
    }

    public function prepareEditScreen(Response $response, string $containerId): void
    {
        /** @var Response|CpScreenResponseBehavior $response */
        $response->crumbs([
            [
                'label' => self::pluralDisplayName(),
                'url' => UrlHelper::cpUrl('submissions'),
            ],
        ]);
    }

    public function formatForApi(): ApiSubmission
    {
        return Plugin::getInstance()->format->forApi($this);
    }

    public function getEntryCpUrl(): ?string
    {
        if ($entry = $this->getEntry()) {
            return $entry->getCpEditUrl();
        }

        return null;
    }


    protected static function defineSearchableAttributes(): array
    {
        return [
            'flagTypes',
        ];
    }
}
