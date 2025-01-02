<?php

namespace zaengle\neverstale\controllers;

use yii\web\Response;
use zaengle\neverstale\elements\NeverstaleContent;
use zaengle\neverstale\enums\AnalysisStatus;
use zaengle\neverstale\Plugin;

/**
 * Flag controller
 */
class FlagController extends BaseController
{
    /**
     * Ignore a flag on a content item in the NS API
     *
     * - there is no undo, so this should be used with caution / be confirmed by the user
     * - requires a POST request
     * - requires a CP request
     * - requires a flagId and contentId in the request body
     *
     * @return Response
     */
    public function actionIgnore(): Response
    {
        $this->requirePostRequest();
        $this->requireCpRequest();
        $flagId = $this->request->getRequiredBodyParam('flagId');
        $contentId = $this->request->getRequiredBodyParam('contentId');

        $content = NeverstaleContent::findOne($contentId);

        if (!$content) {
            return $this->respondWithError(Plugin::t("Content #{id} not found", ['id' => $contentId]));
        }
        try {
            $this->plugin->flag->ignore($content, $flagId);
            $content->setAnalysisStatus(AnalysisStatus::STALE);
            $content->save();

            return $this->respondWithSuccess(Plugin::t('Flag ignored'));
        } catch (\Exception $e) {
            return $this->respondWithError(Plugin::t('Could not ignore flag, check the logs for details'));
        }
    }

    /**
     * Reschedule a flag on a content item in the NS API
     *
     * - requires a POST request
     * - requires a CP request
     * - requires a flagId, contentId, and expiredAt in the request body
     *
     * @return Response
     */
    public function actionReschedule(): Response
    {
        $this->requirePostRequest();
        $this->requireCpRequest();
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

            $content->setAnalysisStatus(AnalysisStatus::STALE);
            $content->save();

            return $this->respondWithSuccess(Plugin::t("Flag rescheduled to {expiredAt}", [
                'expiredAt' => $expiredAt->format('Y-m-d'),
            ]));
        } catch (\Exception $e) {
            return $this->respondWithError(Plugin::t('Could not reschedule flag, check the logs for details'));
        }
    }
}
