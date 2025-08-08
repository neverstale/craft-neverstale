<?php

namespace neverstale\craft\elements;

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
use neverstale\craft\models\Status;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\Response;

use neverstale\craft\elements\conditions\NeverstaleContentCondition;
use neverstale\craft\elements\db\NeverstaleContentQuery;
use neverstale\api\enums\AnalysisStatus;
use neverstale\craft\enums\Permission;
use neverstale\craft\Plugin;
use neverstale\craft\traits\HasNeverstaleContent;

/**
 * Neverstale Content Custom Element Type
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
class NeverstaleContent extends Element
{
    use HasNeverstaleContent;

    public static function displayName(): string
    {
        return Plugin::t('Neverstale Content');
    }
    public static function lowerDisplayName(): string
    {
        return Plugin::t('Neverstale Content');
    }
    public static function pluralDisplayName(): string
    {
        return Plugin::t('Neverstale Content');
    }
    public static function pluralLowerDisplayName(): string
    {
        return Plugin::t('Neverstale Content');
    }
    public static function refHandle(): ?string
    {
        return 'neverstale-content';
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
        return Status::all()
            ->reduce(function(array $statuses, Status $status): array {
                $statuses[$status->value] = [
                    'label' => $status->label,
                    'color' => $status->color,
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
        if ($handle === 'neverstaleContent') {
            // Do a fresh selection from the content table
            // to create a map of content IDs to entry IDs,
            // excluding content with no entry ID:
            $map = (new Query())
                ->select(['id as source', 'entryId as target'])
                ->from(['{{%neverstale_content}}'])
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
        return $this->getStatusModel()->color->value;
    }

    public function getStatusModel(): Status
    {
        return Status::from($this->getStatus());
    }


    public function getUiLabel(): string
    {
        return (string) $this->id;
    }

    public function beforeDelete(): bool
    {
        $deleted =  Plugin::getInstance()->content->delete($this);

        if (!$deleted) {
            $this->addError('status', Plugin::t('Failed to delete content from Neverstale'));
        }

        return $deleted;
    }

    /**
     * @throws InvalidConfigException
     */
    public static function find(): ElementQueryInterface
    {
        return Craft::createObject(NeverstaleContentQuery::class, [static::class]);
    }

    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(NeverstaleContentCondition::class, [static::class]);
    }

    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => Plugin::t('All Neverstale Content'),
            ],
            [
                'heading' => Plugin::t('Processed'),
            ],
            [
                'key' => AnalysisStatus::ANALYZED_FLAGGED->value,
                'label' => AnalysisStatus::ANALYZED_FLAGGED->label(),
                'criteria' => [
                    'analysisStatus' => AnalysisStatus::ANALYZED_FLAGGED->value,
                ],
                'defaultSort' => ['flagCount', 'desc'],
            ],
            [
                'key' => AnalysisStatus::ANALYZED_CLEAN->value,
                'label' => AnalysisStatus::ANALYZED_CLEAN->label(),
                'criteria' => [
                    'analysisStatus' => AnalysisStatus::ANALYZED_CLEAN->value,
                ],
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
            [
                'label' => Plugin::t('Flag Count'),
                'orderBy' => 'neverstale_content.flagCount',
                'attribute' => 'flagCount',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Plugin::t('Date Analyzed'),
                'orderBy' => 'neverstale_content.dateAnalyzed',
                'attribute' => 'dateAnalyzed',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Plugin::t('Date Expired'),
                'orderBy' => 'neverstale_content.dateExpired',
                'attribute' => 'dateExpired',
                'defaultDir' => 'desc',
            ],
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
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'id' => ['label' => Craft::t('app', 'ID')],
            'status' => ['label' => Craft::t('app', 'Status')],
            'entry' => ['label' => Craft::t('app', 'Entry')],
            'uid' => ['label' => Craft::t('app', 'UID')],
            'flagCount' => ['label' => Plugin::t('Flag Count')],
            'dateAnalyzed' => ['label' => Plugin::t('Date Analyzed')],
            'dateExpired' => ['label' => Plugin::t('Date Expired')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('app', 'Date Updated')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'entry',
            'status',
            'flagCount',
            'dateAnalyzed',
            'dateUpdated',
            'dateExpired',
        ];
    }

    protected function attributeHtml(string $attribute): string
    {
        $entry = $this->getEntry();

        return match ($attribute) {
            'entry' => $entry ? Cp::elementChipHtml($this->getEntry()) : '',
            default => $entry ? parent::attributeHtml($attribute) : '',
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
        return UrlHelper::cpUrl('neverstale/content/' . $this->getCanonicalId());
    }
    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('neverstale/content');
    }
    public function prepareEditScreen(Response $response, string $containerId): void
    {
        /** @var Response|CpScreenResponseBehavior $response */
        $response->crumbs([
            [
                'label' => self::pluralDisplayName(),
                'url' => UrlHelper::cpUrl('neverstale/content'),
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
        return Plugin::getInstance()->content->save($this);
    }

    protected static function defineActions(string $source = null): array
    {
        $actions = [];

        // Add batch ingest action if user has permission
        if (Craft::$app->getUser()->checkPermission(Permission::Ingest->value)) {
            $actions[] = \neverstale\craft\elements\actions\BatchIngest::class;
        }

        return array_merge(parent::defineActions($source), $actions);
    }
}
