<?php

namespace zaengle\neverstale\controllers;

use Craft;
use craft\helpers\Json;
use phpDocumentor\Reflection\Types\Self_;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use zaengle\neverstale\elements\NeverstaleSubmission;
use zaengle\neverstale\enums\Permission;
use zaengle\neverstale\models\ApiTransaction;
use zaengle\neverstale\Plugin;

/**
 * Neverstale Submissions Controller
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 *
 * @property-read Plugin $module
 */
class SubmissionsController extends BaseController
{
    public $defaultAction = 'index';
//    protected array|int|bool $allowAnonymous = [
//        'webhook' => self::ALLOW_ANONYMOUS_LIVE,
//    ];
    protected array|int|bool $allowAnonymous = 1;

    /**
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     */
    public function actionShow(?NeverstaleSubmission $submission, ?int $submissionId = null): Response
    {
        $this->requireCpRequest();

        if ($submission === null) {
            $submission = NeverstaleSubmission::find()->id($submissionId)->siteId('*')->one();

            if (!$submission) {
                throw new NotFoundHttpException('Submission not found');
            }
        }
        return $this->renderTemplate('neverstale/submissions/_show', [
            'submission' => $submission,
            'title' => $submission->title,
        ]);
    }

    public function actionDelete(): ?Response
    {
        $this->requireCpRequest();
        $this->requirePostRequest();

        $session = Craft::$app->getSession();

        $submissionId = $this->request->getParam('submissionId');

        if (!Craft::$app->getElements()->deleteElementById($submissionId)) {
            $session->setError(Plugin::t('Could not delete submission.'));

            return null;
        }

        $session->setNotice(Plugin::t('Submission was deleted.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * (Re-)Send a submission to the Neverstale API
     * 
     * @return Response|null
     * @throws BadRequestHttpException
     * @throws \craft\errors\MissingComponentException
     * @throws \yii\web\MethodNotAllowedHttpException|\yii\web\ForbiddenHttpException
     */
    public function actionIngest()
    {
        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requirePermission(Permission::Ingest->value);
        $session = Craft::$app->getSession();

        $submissionId = $this->request->getParam('submissionId');
        $submission = NeverstaleSubmission::findOne(['id' => $submissionId]);

        if(!$submission) {
            $session->setError(Plugin::t("Submission #{id} not found", ['id' => $submissionId]));

            return null;
        }

        if (!Plugin::getInstance()->api->ingest($submission)) {
            $session->setError(Plugin::t("Could not ingest submission #{id}", ['id' => $submissionId]));

            return null;
        }

        $session->setNotice(Plugin::t("Submission #{id} was ingested", ['id' => $submissionId]));


        return $this->redirectToPostedUrl();
    }

    public function actionWebhook()
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

        if (!$this->module->api->validateSignature($payload, $requestSignature)) {
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
        // Update the submission based on the webhook data
        try {
            $transaction = ApiTransaction::fromNeverstaleData($data);
            // Look for our submission
            $submission = NeverstaleSubmission::findOne(['uid' => $transaction->customId]);

            if (!$submission) {
                Plugin::error('Submission not found for webhook');
                return $this->asFailure('Submission not found');
            }

            $this->module->api->onWebhook($submission, $transaction);


        } catch (\Exception $e) {
            dd($e);
            Plugin::error('Could not process webhook: ' . $e->getMessage());
            return $this->asFailure('Could not process webhook');
        }

        return $this->asSuccess('Webhook processed');
    }
}
