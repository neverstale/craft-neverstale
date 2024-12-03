<?php

namespace zaengle\neverstale\controllers;

use Craft;
use craft\helpers\Json;
use craft\web\Controller;
use yii\web\Response;
use zaengle\neverstale\elements\NeverstaleContent;
use zaengle\neverstale\models\ApiTransaction;
use zaengle\neverstale\Plugin;

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
             $data = Json::decode($this->request->getRawBody());
         } catch (\Exception $e) {
             Plugin::error('Could not decode webhook data: ' . $e->getMessage());
             return $this->asFailure('Could not decode webhook body');
         }
         // Update the content item based on the webhook data
         try {
             $transaction = ApiTransaction::fromWebhookPayload($data);
             // Look for our content item
             $content = Plugin::getInstance()->content->findOrCreateByCustomId($transaction->customId);

             $this->plugin->content->onWebhook($content, $transaction);


         } catch (\Exception $e) {

             Plugin::error('Could not process webhook: ' . $e->getMessage());
             return $this->asFailure('Could not process webhook');
         }

         return $this->asSuccess('Webhook processed');
     }
}
