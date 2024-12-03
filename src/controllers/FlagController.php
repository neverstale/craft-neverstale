<?php

namespace zaengle\neverstale\controllers;

use Craft;
use craft\helpers\DateTimeHelper;
use craft\web\Controller;
use yii\web\Response;
use zaengle\neverstale\elements\NeverstaleContent;
use zaengle\neverstale\Plugin;

/**
 * Flag controller
 */
class FlagController extends BaseController
{
    public function actionIgnore(): Response
    {
        $this->requirePostRequest();
        $flagId = $this->request->getRequiredBodyParam('flagId');
        $contentId = $this->request->getRequiredBodyParam('contentId');

        $content = NeverstaleContent::findOne($contentId);

        if (!$content) {
            return $this->respondWithError(Plugin::t("Content #{id} not found", ['id' => $contentId]));
        }
        try {
            $this->plugin->flag->ignore($content, $flagId);
            return $this->respondWithSuccess(Plugin::t('Flag ignored'));
        } catch (\Exception $e) {
            return $this->respondWithError(Plugin::t('Could not ignore flag, check the logs for details'));
        }
    }

    public function actionReschedule(): Response
    {
        $this->requirePostRequest();
        $flagId = $this->request->getRequiredBodyParam('flagId');
        $contentId = $this->request->getRequiredBodyParam('contentId');
        $expiredAt = $this->request->getRequiredBodyParam('expiredAt');

        if (empty($expiredAt)) {
            return $this->respondWithError(Plugin::t('An expired at date is required'));
        }

        $expiredAt = new \DateTime($expiredAt);

        $content = NeverstaleContent::findOne($contentId);

        if (!$content) {
            return $this->respondWithError(Plugin::t("Content #{id} not found", ['id' => $contentId]));
        }

        try {
            $expiredAt = $expiredAt->setTimezone(new \DateTimeZone('UTC'));
            $this->plugin->flag->reschedule($content, $flagId, $expiredAt);

            return $this->respondWithSuccess(Plugin::t("Flag rescheduled to {expiredAt}", [
                'expiredAt' => $expiredAt->format('Y-m-d')
            ]));
        } catch (\Exception $e) {
            return $this->respondWithError(Plugin::t('Could not reschedule flag, check the logs for details'));
        }
    }

    protected function respondWithError($message): Response
    {
        if ($this->request->getAcceptsJson()) {
            return $this->asJson([
                'success' => false,
                'error' => $message,
            ]);
        }

        Craft::$app->getSession()->setError($message);

        return $this->redirectToPostedUrl();
    }

    protected function respondWithSuccess($message): Response
    {
        if ($this->request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'message' => $message,
            ]);
        }

        Craft::$app->getSession()->setNotice($message);

        return $this->redirectToPostedUrl();
    }
}
