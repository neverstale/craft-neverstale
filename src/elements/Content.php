<?php

namespace neverstale\neverstale\elements;

use Craft;
use craft\base\Element;
use craft\db\Query;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\EagerLoadPlan;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\elements\User;
use craft\enums\Color;
use craft\errors\ElementNotFoundException;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\UrlHelper;
use craft\web\CpScreenResponseBehavior;
use neverstale\neverstale\elements\actions\BatchIngest;
use neverstale\neverstale\elements\conditions\ContentCondition;
use neverstale\neverstale\elements\db\ContentQuery;
use neverstale\neverstale\enums\AnalysisStatus;
use neverstale\neverstale\enums\Permission;
use neverstale\neverstale\models\Status;
use neverstale\neverstale\Plugin;
use neverstale\neverstale\traits\HasContent;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\Response;

/**
 * Neverstale Content Custom Element Type
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   1.0.0
 * @see     https://github.com/zaengle/craft-neverstale
 * @property int              $entryId
 * @property int              $siteId
 * @property-read null|string $postEditUrl
 * @property-read string      $statusColor
 * @property-read string      $uiLabel
 * @property-read Entry|null  $entry
 */
class Content extends Element
{
    use HasContent;

    public static function displayName(): string
    {
        return Plugin::t('Neverstale Content');
    }

    public static function lowerDisplayName(): string
    {
        return Plugin::t('neverstale content');
    }

    public static function pluralLowerDisplayName(): string
    {
        return Plugin::t('neverstale content');
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
            ->reduce(function (array $statuses, Status $status): array {
                $statuses[$status->getValue()] = [
                    'label' => $status->getLabel(),
                    'color' => $status->getColor()->value,
                ];

                return $statuses;
            }, []);
    }

    /**
     * @param  array   $sourceElements
     * @param  string  $handle
     * @return array<string,mixed>|false|null
     */
    public static function eagerLoadingMap(array $sourceElements, string $handle): array|null|false
    {
        // Memoize the source element IDs:
        $sourceElementIds = ArrayHelper::getColumn($sourceElements, 'id');

        // The "handle" is the key that users will specify
        // when eager-loading this relationship:
        if ($handle === 'entry') {
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

    /**
     * @throws InvalidConfigException
     */
    public static function find(): ElementQueryInterface
    {
        return Craft::createObject(ContentQuery::class, [static::class]);
    }

    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(ContentCondition::class, [static::class]);
    }

    /**
     * @inheritdoc
     */
    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'dateAnalyzed';
        $attributes[] = 'dateExpired';
        return $attributes;
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
            [
                'heading' => Plugin::t('Unprocessed'),
            ],
            [
                'key' => AnalysisStatus::UNSENT->value,
                'label' => Plugin::t('Pending Submission'),
                'criteria' => [
                    'analysisStatus' => AnalysisStatus::UNSENT->value,
                ],
            ],
            [
                'key' => AnalysisStatus::PENDING_INITIAL_ANALYSIS->value,
                'label' => AnalysisStatus::PENDING_INITIAL_ANALYSIS->label(),
                'criteria' => [
                    'analysisStatus' => AnalysisStatus::PENDING_INITIAL_ANALYSIS->value,
                ],
            ],
            [
                'key' => AnalysisStatus::PENDING_REANALYSIS->value,
                'label' => AnalysisStatus::PENDING_REANALYSIS->label(),
                'criteria' => [
                    'analysisStatus' => AnalysisStatus::PENDING_REANALYSIS->value,
                ],
            ],
            [
                'key' => AnalysisStatus::STALE->value,
                'label' => Plugin::t('Stale'),
                'criteria' => [
                    'analysisStatus' => AnalysisStatus::STALE->value,
                ],
            ],
            [
                'key' => 'processing',
                'label' => Plugin::t('Processing'),
                'criteria' => [
                    'analysisStatus' => [
                        AnalysisStatus::PROCESSING_INITIAL_ANALYSIS->value,
                        AnalysisStatus::PROCESSING_REANALYSIS->value,
                    ],
                ],
            ],
            [
                'heading' => Plugin::t('Errors'),
            ],
            [
                'key' => 'analysis-errors',
                'label' => Plugin::t('Analysis Errors'),
                'criteria' => [
                    'analysisStatus' => [
                        AnalysisStatus::ANALYZED_ERROR->value,
                        AnalysisStatus::API_ERROR->value,
                    ],
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
            'status',
            'dateAnalyzed',
            'dateUpdated',
        ];
    }

    protected static function defineSearchableAttributes(): array
    {
        return [];
    }

    protected static function defineActions(?string $source = null): array
    {
        $actions = [];

        // Add batch ingest action if user has permission
        if (Craft::$app->getUser()->checkPermission(Permission::Ingest->value)) {
            $actions[] = BatchIngest::class;
        }

        return array_merge(parent::defineActions($source), $actions);
    }

    public function afterSave(bool $isNew): void
    {
        $this->updateNeverStaleRecord($isNew);

        parent::afterSave($isNew);
    }

    public function setEagerLoadedElements(string $handle, array $elements, EagerLoadPlan $plan): void
    {
        // The handle can be anything, so long as it matches what is used in `eagerLoadingMap()`:
        if ($handle === 'entry') {
            $entry = $elements[0] ?? null;
            if ($entry !== null) {
                $this->setEntry($entry);
            }
        } else {
            parent::setEagerLoadedElements($handle, $elements, $plan);
        }
    }

    public function getStatusColor(): string
    {
        try {
            $statusModel = $this->getStatusModel();
            $color = $statusModel->getColor();

            return $color->value;
        } catch (\Exception $e) {
            Plugin::error("Error getting status color: ".$e->getMessage());

            return Color::Gray->value;
        }
    }

    public function getStatusModel(): Status
    {
        try {
            $status = $this->getStatus() ?? AnalysisStatus::UNSENT->value;

            return Status::from($status);
        } catch (\Exception $e) {
            Plugin::error("Error creating status model: ".$e->getMessage().", using default UNSENT status");

            return Status::from(AnalysisStatus::UNSENT);
        }
    }

    public function getStatus(): ?string
    {
        // Get status directly from database record to avoid circular calls
        $record = $this->getRecord();

        return $record?->analysisStatus ?? AnalysisStatus::UNSENT->value;
    }

    public function getUiLabel(): string
    {
        $entry = $this->getEntry();
        if ($entry) {
            return $entry->title ?: "Entry #{$this->entryId}";
        }

        return "Content #{$this->id}";
    }

    public function beforeDelete(): bool
    {
        // First delete all related flags
        $flags = $this->getFlags();

        foreach ($flags as $flag) {
            if (! Craft::$app->getElements()->deleteElement($flag)) {
                $this->addError('flags', Plugin::t('Failed to delete related flag: {flag}', ['flag' => $flag->flag]));

                return false;
            }
        }

        $plugin = Plugin::getInstance();
        if (! $plugin || ! method_exists($plugin, 'content') || ! $plugin->content) {
            // If no content service, just allow deletion
            return true;
        }

        $deleted = $plugin->content->delete($this);

        if (! $deleted) {
            $this->addError('status', Plugin::t('Failed to delete content from Neverstale'));
        }

        return $deleted;
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

    public function canSave(User $user): bool
    {
        return false;
    }

    public function canCreateDrafts(User $user): bool
    {
        return false;
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

    public static function pluralDisplayName(): string
    {
        return Plugin::t('Neverstale Content');
    }

    /**
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
     */

    /**
     * Load transaction logs for this content element
     *
     * @return array Array of TransactionLog records
     */
    public function loadTransactionLogs(): array
    {
        if (! $this->id) {
            return [];
        }

        $record = $this->getRecord();
        if (! $record) {
            return [];
        }

        return $record->getTransactionLogs()->all();
    }

    protected function attributeHtml(string $attribute): string
    {
        $entry = $this->getEntry();

        return match ($attribute) {
            'entry' => $entry ? Cp::elementChipHtml($entry) : '',
            'flagCount' => $this->flagCount ? (string) $this->flagCount : '0',
            'dateAnalyzed' => $this->dateAnalyzed ? Craft::$app->formatter->asDatetime($this->dateAnalyzed) : '',
            'dateExpired' => $this->dateExpired ? Craft::$app->formatter->asDatetime($this->dateExpired) : '',
            'status' => $this->getStatusHtml(),
            default => parent::attributeHtml($attribute),
        };
    }

    public function getStatusHtml(): string
    {
        try {
            $statusModel = $this->getStatusModel();
            $flagCount = $this->flagCount ?? 0;

            // Use the Status model's color and convert Craft Color enum to CSS class
            $craftColor = $statusModel->getColor();
            $statusClass = match ($craftColor) {
                Color::Green => 'green',
                Color::Red => 'red',
                Color::Blue => 'blue',
                Color::Orange => 'orange',
                Color::Yellow => 'yellow',
                Color::Purple => 'purple',
                Color::Pink => 'pink',
                Color::Teal => 'turquoise', // Craft uses 'turquoise' for teal
                Color::Gray => 'gray',
                default => 'gray'
            };

            $statusLabel = $statusModel->getLabel();
            $statusLabelText = $statusLabel;

            // Add flag count if there are flags
            if ($flagCount > 0) {
                $statusLabelText .= ' – '.$flagCount;
            }

            return '<span class="status-label '.$statusClass.'" style="white-space: nowrap;"><span class="status '.$statusClass.'"></span><span class="status-label-text">'.$statusLabelText.'</span></span>';
        } catch (\Exception $e) {
            Plugin::error("Error generating status HTML: ".$e->getMessage());

            return '<span class="status-label gray" style="white-space: nowrap;"><span class="status gray"></span><span class="status-label-text">Unknown</span></span>';
        }
    }

    protected function cpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('neverstale/content/'.$this->getCanonicalId());
    }
}
