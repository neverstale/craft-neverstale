<?php

namespace zaengle\neverstale\services;

use Craft;
use craft\elements\Entry;
use craft\helpers\Json;
use craft\helpers\Queue;
use GuzzleHttp\Exception\GuzzleException;
use yii\base\Component;
use zaengle\neverstale\elements\NeverstaleContent;
use zaengle\neverstale\jobs\CreateNeverstaleContentJob;
use zaengle\neverstale\models\ApiTransaction;
use zaengle\neverstale\models\CustomId;
use zaengle\neverstale\Plugin;
use zaengle\neverstale\support\ApiClient;
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
            /** @var NeverstaleContent $content */
            $response = $this->client->ingest(
                $content->forApi(),
                [
                    'webhook' => [
                        'endpoint' => $content->webhookUrl,
                    ],
                ]
            );

            $responseBody = Json::decode($response->getBody()->getContents());

            $transaction = ApiTransaction::fromContentResponse($responseBody, 'api.ingest');
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
        } catch (GuzzleException $e) {
            $transaction = ApiTransaction::fromGuzzleException($e, 'api.error');
            return $this->onIngestError($content, $transaction);
        } catch (\Exception $e) {
//            @todo handle other exceptions
            Plugin::error("Failed to ingest content #{$content->id}: {$e->getMessage()}");
            dd($e);
        }
    }

    public function fetchByCustomId(string $customId): mixed
    {
        $response = $this->client->getByCustomId($customId);

        //        @todo return an object, handle errors
        return Json::decode($response->getBody()->getContents());
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
    public function onIngestError(NeverstaleContent $content, ApiTransaction $transaction): bool
    {
        $content->setAnalysisStatus($transaction->getAnalysisStatus());
        $content->logTransaction($transaction);

        return Plugin::getInstance()->content->save($content);
    }
    public function onIngestSuccess(NeverstaleContent $content, ApiTransaction $transaction): bool
    {
        $content->neverstaleId = $transaction->neverstaleId;
        $content->setAnalysisStatus($transaction->getAnalysisStatus());
        $content->logTransaction($transaction);

        return Plugin::getInstance()->content->save($content);
    }

    public function onWebhook(NeverstaleContent $content, ApiTransaction $transaction): bool
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
    public function save(NeverstaleContent $content): bool
    {
        $saved = Craft::$app->getElements()->saveElement($content);

        if (!$saved) {
            Plugin::error("Failed to save content #{$content->id}" . print_r($content->getErrors(), true));
        }

        return $saved;
    }

    public function refresh(NeverstaleContent $content)
    {
        Plugin::info("Refreshing content #{$content->id} from Neverstale");

        $data = $this->fetchByCustomId($content->customId);

        $data['message'] = 'Content refreshed from Neverstale';
        $transaction = ApiTransaction::fromContentResponse($data, 'api.refreshContent');

        $content->setAnalysisStatus($transaction->analysisStatus);
        $content->logTransaction($transaction);

        return Plugin::getInstance()->content->save($content);
    }
}
