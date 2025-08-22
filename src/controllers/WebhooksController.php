<?php

namespace neverstale\neverstale\controllers;

use craft\helpers\Json;
use Exception;
use neverstale\neverstale\elements\Content;
use neverstale\neverstale\models\TransactionLogItem;
use neverstale\neverstale\Plugin;
use yii\web\Response;

/**
 * Webhooks controller
 */
class WebhooksController extends BaseController
{
    public $enableCsrfValidation = false;

    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE | self::ALLOW_ANONYMOUS_OFFLINE;

    /**
     * neverstale/webhooks action
     */
    public function actionIndex(): Response
    {
        $startTime = microtime(true);
        $requestId = uniqid('wh_');

        Plugin::webhookInfo("[$requestId] Webhook request received from IP: ".$this->request->getUserIP());

        $this->requirePostRequest();
        $this->requireAcceptsJson();

        // Get request details
        $payload = $this->request->getRawBody();
        $requestSignature = $this->request->getHeaders()->get('Signature');
        $contentType = $this->request->getHeaders()->get('Content-Type');
        $userAgent = $this->request->getHeaders()->get('User-Agent');

        Plugin::webhookInfo("[$requestId] Headers - Content-Type: $contentType, User-Agent: $userAgent");
        Plugin::webhookInfo("[$requestId] Payload size: ".strlen($payload)." bytes");
        Plugin::webhookDebug("[$requestId] Raw payload: ".$payload);

        // Confirm the webhook signature
        if (! $requestSignature) {
            Plugin::webhookError("[$requestId] No webhook signature provided");

            return $this->asFailure('No webhook signature provided');
        }

        Plugin::webhookDebug("[$requestId] Request signature: $requestSignature");

        if (! Plugin::getInstance()->content->validateSignature($payload, $requestSignature)) {
            Plugin::webhookError("[$requestId] Invalid webhook signature - expected vs received signature mismatch");

            return $this->asFailure('Invalid webhook signature');
        }

        Plugin::webhookInfo("[$requestId] Signature validation successful");

        // Decode the webhook data
        try {
            $decodedPayload = Json::decode($payload);
            Plugin::webhookInfo("[$requestId] JSON decoding successful");
            Plugin::webhookDebug("[$requestId] Decoded payload: ".json_encode($decodedPayload));
        } catch (Exception $e) {
            Plugin::webhookError("[$requestId] Could not decode webhook data: ".$e->getMessage());

            return $this->asFailure('Could not decode webhook body');
        }

        // Update the content item based on the webhook data
        try {
            Plugin::webhookInfo("[$requestId] Processing webhook payload...");

            $transaction = TransactionLogItem::fromWebhookPayload($decodedPayload);
            Plugin::webhookInfo("[$requestId] Transaction created - Event: ".($transaction->event ?? 'none').
                ", Status: ".($transaction->transactionStatus ?? 'unknown').
                ", CustomId: ".($transaction->customId ?? 'none'));

            /** @var Content $content */
            $content = Plugin::getInstance()->content->findOrCreateByCustomId($transaction->customId);

            if (! $content) {
                Plugin::webhookError("[$requestId] Could not find or create content for customId: ".$transaction->customId);

                return $this->asFailure('Content not found');
            }

            Plugin::webhookInfo("[$requestId] Found/created content ID: ".$content->id." for entry ID: ".$content->entryId);

            Plugin::getInstance()->content->onWebhook($content, $transaction);

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            Plugin::webhookInfo("[$requestId] Webhook processed successfully in {$processingTime}ms");

        } catch (Exception $e) {
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            Plugin::webhookError("[$requestId] Could not process webhook (after {$processingTime}ms): ".$e->getMessage());
            Plugin::webhookDebug("[$requestId] Exception trace: ".$e->getTraceAsString());

            return $this->asFailure('Could not process webhook');
        }

        return $this->asSuccess('Webhook processed');
    }
}
