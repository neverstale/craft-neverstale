<?php

namespace zaengle\neverstale\elements;

use Craft;
use craft\base\Element;
use craft\db\Query;
use craft\elements\db\EagerLoadPlan;
use craft\elements\Entry;
use craft\elements\User;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\UrlHelper;
use craft\web\CpScreenResponseBehavior;
use DateTime;
use yii\base\InvalidConfigException;
use yii\web\Response;
use zaengle\neverstale\elements\conditions\NeverstaleSubmissionCondition;
use zaengle\neverstale\elements\db\NeverstaleSubmissionQuery;
use zaengle\neverstale\enums\FlagType;
use zaengle\neverstale\enums\SubmissionStatus;
use zaengle\neverstale\models\ApiSubmission;
use zaengle\neverstale\Plugin;

/**
 * Submission element type
 *
 * @property-read null|string $postEditUrl
 */
class NeverstaleSubmission extends Element
{
    public int $entryId;
    public int|null $siteId;
    public bool $isSent = false;
    public bool $isProcessed = false;
    public int $flagCount = 0;

    private ?Entry $entry;

    /**
     * @var array<string>
     */
    public array $flagTypes = [];

    public ?DateTime $nextFlagDate;

    public static function displayName(): string
    {
        return Craft::t('neverstale', 'Neverstale Submission');
    }
    public static function lowerDisplayName(): string
    {
        return Craft::t('neverstale', 'Neverstale submission');
    }
    public static function pluralDisplayName(): string
    {
        return Craft::t('neverstale', 'Neverstale Submissions');
    }
    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('neverstale', 'Neverstale submissions');
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

    public static function statuses(): array
    {
        return [
            SubmissionStatus::Pending->value => [
                'label' => SubmissionStatus::Pending->label(),
                'color' => SubmissionStatus::Pending->color(),
            ],
            SubmissionStatus::Processing->value => [
                'label' => SubmissionStatus::Processing->label(),
                'color' => SubmissionStatus::Processing->color(),
            ],
            SubmissionStatus::Clean->value => [
                'label' =>SubmissionStatus::Clean->label(),
                'color' => SubmissionStatus::Clean->color(),
            ],
            SubmissionStatus::Flagged->value => [
                'label' => SubmissionStatus::Flagged->label(),
                'color' => SubmissionStatus::Flagged->color(),
            ],
        ];
    }
    public static function eagerLoadingMap(array $sourceElements, string $handle): array|null|false
    {
        // Memoize the source element IDs:
        $sourceElementIds = ArrayHelper::getColumn($sourceElements, 'id');

        // The “handle” is the key that users will specify
        // when eager-loading this relationship:
        if ($handle === 'neverstaleSubmissions') {
            // Do a fresh selection from the products table
            // to create a map of element IDs to vendor IDs,
            // excluding products with no vendor ID:
            $map = (new Query())
                ->select(['id as source', 'entryId as target'])
                ->from(['{{%neverstale_submissions}}'])
                ->where([
                    'and',
                    ['id' => $sourceElementIds],
                    ['not', ['entryId' => null]],
                ])
                ->all();

            return [
                'elementType' => Entry::class,
                'map' => $map,
            ];
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
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
                'label' => Craft::t('neverstale', 'All Neverstale Submissions'),
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
            'uid' => ['label' => Craft::t('app', 'UID')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('app', 'Date Updated')],
            // ...
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'link',
            'dateCreated',
            // ...
        ];
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

    protected function previewTargets(): array
    {
        $previewTargets = [];
        $url = $this->getUrl();
        if ($url) {
            $previewTargets[] = [
                'label' => Craft::t('app', 'Primary {type} page', [
                    'type' => self::lowerDisplayName(),
                ]),
                'url' => $url,
            ];
        }
        return $previewTargets;
    }

    protected function route(): array|string|null
    {
        // Define how submissions should be routed when their URLs are requested
        return [
            'templates/render',
            [
                'template' => 'site/template/path',
                'variables' => ['submission' => $this],
            ]
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
        return $user->can('deleteSubmissions');
    }

    public function canCreateDrafts(User $user): bool
    {
        return false;
    }

    protected function cpEditUrl(): ?string
    {
        return sprintf('submissions/%s', $this->getCanonicalId());
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

    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            Db::upsert('{{%neverstale_submissions}}', [
                'id' => $this->id,
            ], [
                'entryId' => $this->entryId,
                'siteId' => $this->siteId,
                'isSent' => $this->isSent,
                'isProcessed' => $this->isProcessed,
            ]);
        }

        parent::afterSave($isNew);
    }

    public function formatForApi(): ApiSubmission
    {
        return Plugin::getInstance()->format->forApi($this);
    }

    protected static function defineSearchableAttributes(): array
    {
        return [
            'flagTypes',
        ];
    }

}
