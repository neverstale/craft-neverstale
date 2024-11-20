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

            $transaction = ApiTransaction::fromIngestResponse($responseBody);
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
//            @todo handle guzzle exceptions
            dd($e);
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
        $submission->logTransaction($transaction);
        switch ($transaction->analysisStatus) {
            case AnalysisStatus::PENDING_INITIAL_ANALYSIS:
            case AnalysisStatus::ANALYZED_CLEAN:
            case AnalysisStatus::PENDING_REANALYSIS:
                $submission->flagCount = 0;
                break;
            case AnalysisStatus::ANALYZED_FLAGGED:
//                @todo: handle flag count + types
//                $submission->flagCount = $transaction->data['flag_count'] ?? 0;
                break;
        }

        return Plugin::getInstance()->submission->save($submission);
    }
}
