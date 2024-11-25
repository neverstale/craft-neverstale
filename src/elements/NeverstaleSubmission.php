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
use craft\errors\ElementNotFoundException;
use craft\helpers\ArrayHelper;

use craft\helpers\Cp;
use craft\helpers\UrlHelper;

use craft\web\CpScreenResponseBehavior;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\Response;

use zaengle\neverstale\enums\AnalysisStatus;
use zaengle\neverstale\Plugin;
use zaengle\neverstale\elements\conditions\NeverstaleSubmissionCondition;
use zaengle\neverstale\elements\db\NeverstaleSubmissionQuery;
use zaengle\neverstale\enums\Permission;
use zaengle\neverstale\traits\HasNeverstaleContent;

/**
 * Neverstale Submission Custom Element Type
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 * @property int $entryId
 * @property int $siteId
 * @property-read null|string $postEditUrl
 * @property-read string $statusColor
 * @property-read string $uiLabel
 * @property-read Entry|null $entry
 */
class NeverstaleSubmission extends Element
{
    use HasNeverstaleContent;

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
    public static function hasStatuses(): bool
    {
        return true;
    }
    /**
     * @return array<string, array<string, string>>
     */
    public static function statuses(): array
    {
        return collect(AnalysisStatus::cases())
            ->reduce(function($statuses, $status): array {
                $statuses[$status->value] = [
                    'label' => $status->label(),
                    'color' => $status->color(),
                ];
                return $statuses;
            }, []);
    }
    /**
     * @param array $sourceElements
     * @param string $handle
     * @return array<string,mixed>|false|null
     */
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
        $this->updateNeverstaleRecord($isNew);
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
        return $this->getRecord()?->analysisStatus;
    }

    public function getStatusColor(): string
    {
        return $this->getStatusAsEnum()->color()->value;
    }

        public function getStatusAsEnum(): AnalysisStatus
    {
        return AnalysisStatus::from($this->getStatus());
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

    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'entry' => Cp::elementChipHtml($this->getEntry()),
            default => parent::attributeHtml($attribute),
        };
    }

    public function getUriFormat(): ?string
    {
        return null;
    }
    public function canView(User $user): bool
    {
        if (parent::canView($user)) {
            return true;
        }

        return $user->can(Permission::View->value);
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


    protected static function defineSearchableAttributes(): array
    {
        return [];
    }

    /**
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws \Throwable
     */
    public function save(): bool
    {
        return Plugin::getInstance()->submission->save($this);
    }
}
