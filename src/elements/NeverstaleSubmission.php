<?php

namespace zaengle\neverstale\elements;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\EagerLoadPlan;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\Queue as QueueHelper;
use craft\helpers\Cp;
use craft\helpers\Db;
use craft\helpers\UrlHelper;
use craft\queue\JobInterface;

use craft\queue\QueueInterface;
use craft\web\CpScreenResponseBehavior;
use DateTime;
use yii\base\InvalidConfigException;
use yii\web\Response;

use zaengle\neverstale\Plugin;
use zaengle\neverstale\elements\conditions\NeverstaleSubmissionCondition;
use zaengle\neverstale\elements\db\NeverstaleSubmissionQuery;
use zaengle\neverstale\enums\Permission;
use zaengle\neverstale\enums\SubmissionStatus;
use zaengle\neverstale\helpers\SubmissionJobHelper;
use zaengle\neverstale\models\ApiData;
use zaengle\neverstale\models\ApiTransaction;
use zaengle\neverstale\records\Submission as SubmissionRecord;
use zaengle\neverstale\services\Submission as SubmissionService;

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
 * @property-read string $webhookUrl
 * @property-read array $transactionLog
 * @property-read array $jobIds
 */
class NeverstaleSubmission extends Element
{
    public int $entryId;
    public int|null $siteId = null;
    public ?string $neverstaleId = null;
    public bool $isSent = false;
    public bool $isProcessed = false;
    public bool $isFailed = false;
    public int $flagCount = 0;
    protected array $transactionLog = [];
    protected array $jobIds = [];
    private ?Entry $entry = null;
    /**
     * @var array<string>
     */
    public array $flagTypes = [];
    public ?DateTime $nextFlagDate = null;
    public function logTransaction(ApiTransaction $item): void
    {
        $this->transactionLog[] = $item->toArray([
            'transactionStatus',
            'message',
            'neverstaleId',
            'channelId',
            'customId',
            'createdAt'
        ]);
    }
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
        return collect(SubmissionStatus::cases())
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
        Plugin::log("Saving submission $this->id", 'info');
        // Get the submission record
        if (!$isNew) {
            $record = $this->getRecord();

            if (!$record) {
                throw new \Exception('Invalid submission ID: ' . $this->id);
            }
        } else {
            $record = new SubmissionRecord();
            $record->id = $this->id;
        }

        $record->entryId = $this->entryId;
        $record->siteId = $this->siteId;
        $record->isSent = $this->isSent;
        $record->isProcessed = $this->isProcessed;
        $record->isFailed = $this->isFailed;
        $record->neverstaleId = $this->neverstaleId;
        $record->transactionLog = $this->transactionLog;
        $record->flagCount = $this->flagCount;
        $record->flagTypes = $this->flagTypes;
        $record->jobIds = $this->jobIds;
        $record->nextFlagDate = Db::prepareDateForDb($this->nextFlagDate);

        $record->save(false);

        parent::afterSave($isNew);
    }

    public function setProcessing(ApiTransaction $transaction): void
    {
        $this->isProcessed = false;
        $this->logTransaction($transaction);
    }
    public function setFailed(ApiTransaction $transaction): void
    {
        $this->isSent = true;
        $this->isFailed = true;
        $this->isProcessed = false;
        $this->logTransaction($transaction);
    }

    public function setFlagged(ApiTransaction $transaction): void
    {
        $this->logTransaction($transaction);
    }
    public function setClean(ApiTransaction $transaction): void
    {
        $this->logTransaction($transaction);
    }

    public function getWebhookUrl()
    {
        return UrlHelper::actionUrl("neverstale/submissions/webhook/");
    }

    public function addJob(JobInterface $job): void
    {
        $jobId = QueueHelper::push($job, SubmissionJobHelper::getPriority(), SubmissionJobHelper::getDelay());

        Plugin::log('added Job ID: ' . $jobId . ' to submission ID: ' . $this->id);

        $this->jobIds = array_merge($this->getJobIds(), [(int) $jobId]);

        Craft::$app->getElements()->saveElement($this);
    }
    public function cleanOldJobs(QueueInterface $queue): void
    {
        $oldJobs = SubmissionJobHelper::getOldJobs($queue, $this);
        collect($this->jobIds)
            ->filter(fn(int $jobId) => $oldJobs->contains($jobId))
            ->each(fn(int $jobId) => $this->removeJob($jobId));

        Craft::$app->getElements()->saveElement($this);
    }
    public function removeJob(int $jobId): void
    {
        $this->jobIds = array_filter($this->jobIds, fn(int $id) => $id !== $jobId);
    }
    public function getJobIds(): array
    {
        return $this->getRecord()?->getJobIds() ?? [];
    }

    public function getTransactionLog(): array
    {
        return $this->getRecord()?->getTransactionLog() ?? [];
    }

    public function getRecord(): ?SubmissionRecord
    {
        if ($this->id === null) {
            return null;
        }
        return SubmissionRecord::findOne($this->id);
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

    public function getStatusColor(): string
    {
        return SubmissionStatus::from($this->getStatus())->color()->value;
    }

    public function setEntry(Entry|ElementInterface $entry): void
    {
        $this->entry = $entry;
        $this->entryId = $entry->id;
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

    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'entry' => Cp::elementChipHtml($this->getEntry()),
            default => parent::attributeHtml($attribute),
        };
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

    public function toApiData(): ApiData
    {
        return Plugin::getInstance()->format->forApi($this);
    }

    /**
     * Get the front-end URL for the related entry
     *
     * Used when formatting data for submission to the API
     */
    public function getEntryUrl(): ?string
    {
        if ($entry = $this->getEntry()) {
            return $entry->getUrl();
        }

        return null;
    }
    /**
     * Get the URL to edit the related entry in the Craft CP
     *
     * Used when formatting data for submission to the API
     */
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

    public function save(): bool
    {
        return SubmissionService::save($this);
    }
}
