<?php

namespace zaengle\neverstale\services;

use craft\helpers\Json;
use GuzzleHttp\Exception\GuzzleException;
use yii\base\Component;
use zaengle\neverstale\elements\NeverstaleSubmission;
use zaengle\neverstale\enums\SubmissionStatus;
use zaengle\neverstale\enums\AnalysisStatus;
use zaengle\neverstale\models\ApiTransaction;
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
class Api extends Component
{
    public ApiClient $client;
    public string $hashAlgorithm = 'sha256';

    public function ingest(NeverstaleSubmission $submission): bool
    {
        try {
            /** @var NeverstaleSubmission $submission */
            $response = $this->client->ingest(
                $submission->forApi(),
                [
                    'webhook' => [
                        'endpoint' => $submission->webhookUrl,
                    ],
                ]
            );

            $responseBody = Json::decode($response->getBody()->getContents());

            $transaction = ApiTransaction::fromIngestResponse($responseBody, 'api.ingest');
            Plugin::info("Ingest for submission #{$submission->id}: status {$transaction->transactionStatus}");

            // update the submission element based on the response
            switch ($transaction->transactionStatus) {
                // @todo handle rate limiting here
                case ApiClient::STATUS_SUCCESS:
                    return $this->onIngestSuccess($submission, $transaction);
                case ApiClient::STATUS_ERROR:
                    return $this->onIngestError($submission, $transaction);
                default:
                    Plugin::error("Unknown transaction status: {$transaction->transactionStatus}");
                    return false;
            }
        } catch (GuzzleException $e) {
            $transaction = ApiTransaction::fromGuzzleException($e, 'api.error');
            return $this->onIngestError($submission, $transaction);

            return false;
        } catch (\Exception $e) {
            dd($e);
//            @todo handle other exceptions
            Plugin::error("Failed to ingest submission #{$submission->id}: {$e->getMessage()}");
            return false;
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
    public function onIngestError(NeverstaleSubmission $submission, ApiTransaction $transaction): bool
    {
        $submission->setAnalysisStatus($transaction->getAnalysisStatus());
        $submission->logTransaction($transaction);

        return Plugin::getInstance()->submission->save($submission);
    }
    public function onIngestSuccess(NeverstaleSubmission $submission, ApiTransaction $transaction): bool
    {
        $submission->neverstaleId = $transaction->neverstaleId;
        $submission->setAnalysisStatus($transaction->getAnalysisStatus());
        $submission->logTransaction($transaction);

        return Plugin::getInstance()->submission->save($submission);
    }

    public function onWebhook(NeverstaleSubmission $submission, ApiTransaction $transaction): bool
    {
        $submission->setAnalysisStatus($transaction->getAnalysisStatus());
        $submission->flagCount = $transaction->getFlagCount();

        if ($transaction->getDateAnalyzed()) {
            $submission->dateAnalyzed = $transaction->getDateAnalyzed();
        }
        if ($transaction->getDateExpired()) {
            $submission->dateExpired = $transaction->getDateExpired();
        }
        $submission->logTransaction($transaction);

        return Plugin::getInstance()->submission->save($submission);
    }
}
