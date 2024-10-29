<?php

namespace zaengle\neverstale\services;

use craft\helpers\Json;
use GuzzleHttp\Exception\GuzzleException;
use yii\base\Component;
use zaengle\neverstale\elements\NeverstaleSubmission;
use zaengle\neverstale\enums\SubmissionStatus;
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
                $submission->toApiData()->toArray(),
                [
                    'webhook' => [
                        'endpoint' => $submission->webhookUrl,
                    ],
                ]
            );

            $payload = $response->getBody()->getContents();

            $transaction = ApiTransaction::fromNeverstaleData(Json::decode($payload));
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
        $submission->isFailed = true;
        $submission->logTransaction($transaction);

        return Submission::save($submission);
    }
    public function onIngestSuccess(NeverstaleSubmission $submission, ApiTransaction $transaction): bool
    {
        $submission->isSent = true;
        $submission->isProcessed = false;
        $submission->neverstaleId = $transaction->neverstaleId;
        $submission->logTransaction($transaction);

        return Submission::save($submission);
    }

    public function onWebhook(NeverstaleSubmission $submission, ApiTransaction $transaction): bool
    {
//        @todo handle other webhook data
        $submission->logTransaction($transaction);
        switch ($transaction->submissionStatus) {
            case SubmissionStatus::Failed:
                $submission->isProcessed = false;
                $submission->flagCount = 0;
                $submission->isFailed = true;
                break;
            case SubmissionStatus::Flagged:
                $submission->flagCount = 1;
                $submission->isProcessed = true;
                break;
            case SubmissionStatus::Clean:
                $submission->flagCount = 0;
                $submission->isProcessed = true;
                break;
            default:
                Plugin::error("Unknown submission status: {$transaction->submissionStatus->value}");
                return false;
        }


        return Submission::save($submission);
    }
}
