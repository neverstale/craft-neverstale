<?php

namespace neverstale\craft\controllers;

use craft\helpers\Json;
use neverstale\api\models\Content;
use neverstale\api\models\TransactionResult;
use yii\web\Response;
use neverstale\craft\elements\NeverstaleContent;
use neverstale\craft\models\TransactionLogItem;
use neverstale\craft\Plugin;

/**
 * Webhooks controller
 */
class WebhooksController extends BaseController
{
    public $defaultAction = 'index';
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE | self::ALLOW_ANONYMOUS_OFFLINE;
    public $enableCsrfValidation = false;
    /**
     * neverstale/webhooks action
     */
    public function actionIndex(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        // Confirm the webhook signature
        $payload = $this->request->getRawBody();
        $requestSignature = $this->request->getHeaders()->get('Signature');
        if (!$requestSignature) {
            Plugin::error('No webhook signature provided');
            return $this->asFailure('No webhook signature provided');
        }

        if (!$this->getPlugin()->content->validateSignature($payload, $requestSignature)) {
            Plugin::error('Invalid webhook signature');
            return $this->asFailure('Invalid webhook signature');
        }
        // Decode the webhook data
        try {
            $payload = Json::decode($this->request->getRawBody());
        } catch (\Exception $e) {
            Plugin::error('Could not decode webhook data: ' . $e->getMessage());
            return $this->asFailure('Could not decode webhook body');
        }
        // Update the content item based on the webhook data
        try {

            $transaction = TransactionLogItem::fromWebhookPayload($payload);
            /** @var NeverstaleContent $content */
            $content = Plugin::getInstance()->content->findOrCreateByCustomId($transaction->customId);

            $this->plugin->content->onWebhook($content, $transaction);
        } catch (\Exception $e) {
            Plugin::error('Could not process webhook: ' . $e->getMessage());
            return $this->asFailure('Could not process webhook');
        }

        return $this->asSuccess('Webhook processed');
    }
}
