<?php

namespace neverstale\craft\services;

use Craft;
use craft\elements\Entry;
use craft\helpers\App;
use craft\helpers\Queue;
use neverstale\api\Client;
use neverstale\api\enums\AnalysisStatus;
use neverstale\api\exceptions\ApiException;
use neverstale\api\models\TransactionResult;
use neverstale\craft\jobs\IngestContentJob;
use yii\base\Component;
use neverstale\craft\elements\NeverstaleContent;
use neverstale\craft\models\TransactionLogItem;
use neverstale\craft\models\CustomId;
use neverstale\craft\Plugin;
use neverstale\api\Client as ApiClient;

/**
 * Neverstale API Service
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
class Content extends Component
{
    public ApiClient $client;
    public string $hashAlgorithm = 'sha256';

    public int $cacheHealthDuration = 60;
    protected const HEALTH_CACHE_KEY = 'neverstale:health';

    public function ingest(NeverstaleContent $content): bool
    {
        try {
            $result = $this->client->ingest(
                $content->forApi(),
                [
                    'webhook' => [
                        'endpoint' => $content->webhookUrl,
                    ],
                ]
            );

            $transaction = TransactionLogItem::fromContentResponse($result, 'api.ingest');

            Plugin::info("Ingest for content #{$content->id}: status {$transaction->transactionStatus}");

            // update the content element based on the response
            switch ($transaction->transactionStatus) {
                // @todo handle rate limiting here
                case ApiClient::STATUS_SUCCESS:
                    return $this->onIngestSuccess($content, $transaction);
                case ApiClient::STATUS_ERROR:
                    return $this->onIngestError($content, $transaction);
                default:
                    Plugin::error("Unknown transaction status: {$transaction->transactionStatus}");
                    return false;
            }
        } catch (ApiException $e) {
            Plugin::error("Failed to ingest content #{$content->id}: {$e->getMessage()}");
            $transaction = TransactionLogItem::fromException($e, 'api.error');
            return $this->onIngestError($content, $transaction);
        } catch (\Exception $e) {
            //  @TODO handle other exceptions
            Plugin::error("Failed to ingest content #{$content->id}: {$e->getMessage()}");
            return false;
        }
    }

    public function retrieveByCustomId(string $customId): ?\neverstale\api\models\Content
    {
        try {
            return $this->client->retrieve($customId);
        } catch (ApiException $e) {
            Plugin::error("Failed to fetch content by custom ID {$customId}: {$e->getMessage()}");
            return null;
        }
    }

    public function validateSignature(string $payload, string $userSignature): bool
    {
        return hash_equals($this->sign($payload), $userSignature);
    }

    public function sign(string $payload): string
    {
        $secret = App::parseEnv(Plugin::getInstance()->settings->webhookSecret);

        return hash_hmac($this->hashAlgorithm, $payload, $secret);
    }

    public function onIngestError(NeverstaleContent $content, TransactionLogItem $transaction): bool
    {
        $content->setAnalysisStatus($transaction->getAnalysisStatus());
        $content->logTransaction($transaction);

        return Plugin::getInstance()->content->save($content);
    }

    public function onIngestSuccess(NeverstaleContent $content, TransactionLogItem $transaction): bool
    {
        $content->neverstaleId = $transaction->neverstaleId;
        $content->setAnalysisStatus($transaction->getAnalysisStatus());
        $content->logTransaction($transaction);

        return Plugin::getInstance()->content->save($content);
    }

    public function onWebhook(NeverstaleContent $content, TransactionLogItem $transaction): bool
    {
        $content->setAnalysisStatus($transaction->getAnalysisStatus());
        $content->flagCount = $transaction->getFlagCount();

        if ($transaction->getDateAnalyzed()) {
            $content->dateAnalyzed = $transaction->getDateAnalyzed();
        }
        if ($transaction->getDateExpired()) {
            $content->dateExpired = $transaction->getDateExpired();
        }
        $content->logTransaction($transaction);

        return Plugin::getInstance()->content->save($content);
    }

    public function queue(Entry $entry): void
    {
        $content = Plugin::getInstance()->content->findOrCreateContentFor($entry);

        if (!$content->isUnsent()) {
            $content->setAnalysisStatus(AnalysisStatus::STALE);
            $content->flagCount = null;
            $content->dateExpired = null;
            $content->save();
        }

        Queue::push(new IngestContentJob([
            'contentId' => $content->id,
        ]));
    }

    public function findOrCreateContentFor(Entry $entry): NeverstaleContent
    {
        $content = $this->find($entry);

        if (!$content) {
            $content = $this->create($entry);
            $this->save($content);

            Plugin::log(Plugin::t("Created NeverstaleContent #{contentId} for Entry #{entryId}", [
                'entryId' => $entry->id,
                'contentId' => $content->id,
            ]));
        }

        return $content;
    }

    public function findOrCreateByCustomId(?string $strId): ?NeverstaleContent
    {
        if ($content = $this->getByCustomId($strId)) {
            return $content;
        }

        $entry = Entry::findOne([
            'id' => $customId->entryId,
            'siteId' => $customId->siteId,
        ]);

        if (!$entry) {
            Plugin::error("Entry {$customId->entryId} not found for custom ID: {$strId}");
            return null;
        }

        return $this->create($entry);
    }

    public function find(Entry $entry): ?NeverstaleContent
    {
        return NeverstaleContent::findOne([
            'entryId' => $entry->canonicalId,
            'siteId' => $entry->siteId,
        ]);
    }

    public function create(Entry $entry): NeverstaleContent
    {
        return new NeverstaleContent([
            'entryId' => $entry->canonicalId,
            'siteId' => $entry->siteId,
        ]);
    }

    public function delete(NeverstaleContent $content): bool
    {
        try {
            $result = $this->client->batchDelete([$content->customId]);

            if ($result->getWasError()) {
                Plugin::error("Failed to delete content #{$content->id}: {$result->message}");
            }
        } catch (ApiException $e) {
            Plugin::error("Failed to delete content #{$content->id}: {$e->getMessage()}");
        }

        return true;
    }

    public function save(NeverstaleContent $content): bool
    {
        $saved = Craft::$app->getElements()->saveElement($content);

        if (!$saved) {
            Plugin::error("Failed to save content #{$content->id}" . print_r($content->getErrors(), true));
        }

        return $saved;
    }


    public function refresh(NeverstaleContent $content): bool
    {
        Plugin::info("Refreshing content #{$content->id} from Neverstale");

        try {
            if ($data = $this->retrieveByCustomId($content->customId)) {
                $transaction = TransactionLogItem::fromContentResponse(new TransactionResult([
                    'status' => Client::STATUS_SUCCESS,
                    'message' => Plugin::t('Content refreshed from Neverstale'),
                    'data' => $data,
                ]), 'api.refreshContent');

                $content->setAnalysisStatus($transaction->analysisStatus);
                $content->logTransaction($transaction);

                return Plugin::getInstance()->content->save($content);
            }
            Plugin::error("Failed to refresh content #{$content->id}");
            return false;

        } catch (ApiException $e) {
            Plugin::error("Failed to refresh content #{$content->id}: {$e->getMessage()}");
            return false;
        }
    }

    public function checkCanConnect($forceFresh = false): bool
    {
        if ($forceFresh || !Craft::$app->cache->exists(self::HEALTH_CACHE_KEY)) {
            Craft::$app->cache->set(
                self::HEALTH_CACHE_KEY,
                $this->client->health(),
                $this->cacheHealthDuration
            );
        }
        return Craft::$app->cache->get(self::HEALTH_CACHE_KEY);
    }

    public function clearConnectionStatusCache(): void
    {
        Craft::$app->cache->delete(self::HEALTH_CACHE_KEY);
    }

    public function getLastSync(): ?\DateTime
    {
        return NeverstaleContent::find()
            ->select('dateUpdated')
            ->orderBy('dateUpdated DESC')
            ->one()
            ?->dateUpdated;
    }

    public function getByCustomId(string|CustomId $customId): ?NeverstaleContent
    {
        if (!$customId instanceof CustomId) {
            $customId = CustomId::parse($customId);
        }

        return NeverstaleContent::findOne($customId->id);
    }
}
