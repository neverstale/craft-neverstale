<?php

namespace neverstale\craft\services;

use Craft;
use craft\elements\Entry;
use craft\helpers\Json;
use craft\helpers\Queue;
use GuzzleHttp\Exception\GuzzleException;
use neverstale\api\exceptions\ApiException;
use yii\base\Component;
use neverstale\craft\elements\NeverstaleContent;
use neverstale\craft\jobs\CreateNeverstaleContentJob;
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
//            @todo handle other exceptions
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
        $secret = Plugin::getInstance()->config->get('webhookSecret');

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

    public function queue(Entry $entry): ?string
    {
        return Queue::push(new CreateNeverstaleContentJob([
            'entryId' => $entry->id,
        ]));
    }
    public function findOrCreate(Entry $entry): ?NeverstaleContent
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
        $customId = CustomId::parse($strId);

        if ($content = NeverstaleContent::findOne($customId->id)) {
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
            $data = $this->retrieveByCustomId($content->customId);

            $data['message'] = Plugin::t('Content refreshed from Neverstale');
            $transaction = TransactionLogItem::fromContentResponse($data, 'api.refreshContent');

            $content->setAnalysisStatus($transaction->analysisStatus);
            $content->logTransaction($transaction);

            return Plugin::getInstance()->content->save($content);
        } catch (ApiException $e) {
            Plugin::error("Failed to refresh content #{$content->id}: {$e->getMessage()}");
            return false;
        }
    }
}
