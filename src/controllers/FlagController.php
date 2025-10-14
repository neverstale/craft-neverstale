<?php

namespace neverstale\neverstale\controllers;

use Craft;
use DateTime;
use DateTimeZone;
use Exception;
use neverstale\neverstale\enums\AnalysisStatus;
use neverstale\neverstale\Plugin;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Flag controller
 */
class FlagController extends BaseController
{
    /**
     * Handle ignore flag slideout - both display (GET) and submission (POST)
     * Following pattern from: https://dev-diary.newism.com.au/posts/creating-craft-cms-slideouts.html
     *
     * @return Response
     */
    public function actionIgnoreSlideout(): Response
    {
        $this->requireCpRequest();
        
        $flagId = $this->request->getParam('flagId');
        $customId = $this->request->getParam('customId');
        
        // Validate required parameters
        if (!$flagId || !$customId) {
            if ($this->request->getIsPost()) {
                return $this->asFailure(Plugin::t('Missing required parameters: flagId and customId'));
            }
            throw new NotFoundHttpException(Plugin::t('Missing required parameters: flagId and customId'));
        }
        
        $content = Plugin::getInstance()->content->getByCustomId($customId);
        
        if (!$content) {
            if ($this->request->getIsPost()) {
                return $this->asFailure(Plugin::t("Content #{id} not found", ['id' => $customId]));
            }
            throw new NotFoundHttpException(Plugin::t("Content #{id} not found", ['id' => $customId]));
        }
        
        // Handle POST submission
        if ($this->request->getIsPost()) {
            return $this->processIgnoreSubmission($content, $flagId);
        }
        
        // Handle GET request - show slideout content
        // Get the specific flag information
        $flag = null;
        foreach ($content->flags as $f) {
            // Compare against flagId (Neverstale API ID) not id (Craft element ID)
            if ($f->flagId == $flagId || (string)$f->flagId === (string)$flagId) {
                $flag = $f;
                break;
            }
        }
        
        return $this->asCpScreen()
            ->title(Plugin::t('Ignore Flag'))
            ->action('neverstale/flag/ignore-slideout')
            ->contentTemplate('neverstale/_slideouts/ignore-flag', [
                'flagId' => $flagId,
                'customId' => $customId,
                'content' => $content,
                'flag' => $flag,
            ])
            ->submitButtonLabel(Plugin::t('Ignore Flag'));
    }

    /**
     * Process the ignore flag form submission using Craft's standard patterns
     *
     * @param $content
     * @param $flagId
     * @return Response
     */
    private function processIgnoreSubmission($content, $flagId): Response
    {
        $ignoreOption = $this->request->getRequiredBodyParam('ignoreOption');
        
        try {
            if ($ignoreOption === 'forever') {
                // Only "forever" uses the ignore endpoint (permanently ignores the flag)
                Plugin::getInstance()->flag->ignore($content, $flagId);
                $message = Plugin::t('Flag ignored permanently');
            } elseif ($ignoreOption === 'custom') {
                // Custom date uses reschedule endpoint
                $customDate = $this->request->getBodyParam('customDate');
                if ($customDate && is_array($customDate) && !empty($customDate['date'])) {
                    $expiredAt = new DateTime($customDate['date']);
                    $expiredAt = $expiredAt->setTimezone(new DateTimeZone('UTC'));
                    Plugin::getInstance()->flag->reschedule($content, $flagId, $expiredAt);
                    $message = Plugin::t("Flag ignored until {expiredAt}", [
                        'expiredAt' => $expiredAt->format('Y-m-d'),
                    ]);
                } else {
                    return $this->asFailure(Plugin::t('Custom date is required when selecting custom option'));
                }
            } else {
                // All predefined durations (1-day, 1-week, 1-month, 3-months) use reschedule endpoint
                $expiredAt = $this->calculateExpiredAtFromOption($ignoreOption);
                Plugin::getInstance()->flag->reschedule($content, $flagId, $expiredAt);
                $message = Plugin::t("Flag ignored until {expiredAt}", [
                    'expiredAt' => $expiredAt->format('Y-m-d'),
                ]);
            }
            
            $content->setAnalysisStatus(AnalysisStatus::ANALYZED_FLAGGED);
            Craft::$app->getElements()->saveElement($content);

            // Use Craft's standard success response pattern for slideout
            return $this->asSuccess($message, [
                'message' => $message,
                'flagId' => $flagId,
                'customId' => $content->customId
            ]);
            
        } catch (Exception $e) {
            return $this->asFailure(Plugin::t('Could not ignore flag, check the logs for details'));
        }
    }

    /**
     * Calculate expired date from predefined option (1-day, 1-week, etc.)
     */
    private function calculateExpiredAtFromOption(string $option): DateTime
    {
        $expiredAt = new DateTime();
        
        switch ($option) {
            case '1-day':
                $expiredAt->add(new \DateInterval('P1D'));
                break;
            case '1-week':
                $expiredAt->add(new \DateInterval('P7D'));
                break;
            case '1-month':
                $expiredAt->add(new \DateInterval('P30D'));
                break;
            case '3-months':
                $expiredAt->add(new \DateInterval('P90D'));
                break;
            default:
                $expiredAt->add(new \DateInterval('P1D')); // Default to 1 day
        }
        
        return $expiredAt->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * Calculate the expired date based on ignore option (legacy method)
     */
    private function calculateExpiredAt(string $ignoreOption): DateTime
    {
        $expiredAt = new DateTime();
        
        if ($ignoreOption === 'custom') {
            $durationType = $this->request->getBodyParam('durationType', 'days');
            
            switch ($durationType) {
                case 'days':
                    $duration = (int) $this->request->getBodyParam('ignoreDuration', 1);
                    $expiredAt->add(new \DateInterval("P{$duration}D"));
                    break;
                case 'weeks':
                    $duration = (int) $this->request->getBodyParam('ignoreDuration', 1);
                    $expiredAt->add(new \DateInterval("P{$duration}W"));
                    break;
                case 'months':
                    $duration = (int) $this->request->getBodyParam('ignoreDuration', 1);
                    $expiredAt->add(new \DateInterval("P{$duration}M"));
                    break;
                case 'date':
                    $customDate = $this->request->getBodyParam('ignoreUntilDate');
                    if ($customDate) {
                        $expiredAt = new DateTime($customDate);
                    }
                    break;
            }
        }
        
        return $expiredAt->setTimezone(new \DateTimeZone('UTC'));
    }

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
        $customId = $this->request->getRequiredBodyParam('customId');

        $content = Plugin::getInstance()->content->getByCustomId($customId);

        if (! $content) {
            $errorMessage = Plugin::t("Content #{id} not found", ['id' => $customId]);
            if ($this->request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'error' => $errorMessage
                ]);
            }
            return $this->respondWithError($errorMessage);
        }
        try {
            Plugin::getInstance()->flag->ignore($content, $flagId);
            $content->setAnalysisStatus(AnalysisStatus::ANALYZED_FLAGGED);
            Craft::$app->getElements()->saveElement($content);

            if ($this->request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'message' => Plugin::t('Flag ignored')
                ]);
            }
            return $this->respondWithSuccess(Plugin::t('Flag ignored'));
        } catch (Exception $e) {
            if ($this->request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'error' => Plugin::t('Could not ignore flag, check the logs for details')
                ]);
            }
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
        $customId = $this->request->getRequiredBodyParam('customId');
        $expiredAt = $this->request->getRequiredBodyParam('expiredAt');

        if (empty($expiredAt)) {
            $errorMessage = Plugin::t('An expired at date is required');
            if ($this->request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'error' => $errorMessage
                ]);
            }
            return $this->respondWithError($errorMessage);
        }

        $content = Plugin::getInstance()->content->getByCustomId($customId);

        if (! $content) {
            $errorMessage = Plugin::t("Content #{id} not found", ['id' => $customId]);
            if ($this->request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'error' => $errorMessage
                ]);
            }
            return $this->respondWithError($errorMessage);
        }

        try {
            $expiredAt = new DateTime($expiredAt);

            $expiredAt = $expiredAt->setTimezone(new DateTimeZone('UTC'));
            Plugin::getInstance()->flag->reschedule($content, $flagId, $expiredAt);

            $content->setAnalysisStatus(AnalysisStatus::ANALYZED_FLAGGED);
            Craft::$app->getElements()->saveElement($content);

            if ($this->request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'message' => Plugin::t("Flag rescheduled to {expiredAt}", [
                        'expiredAt' => $expiredAt->format('Y-m-d'),
                    ])
                ]);
            }
            return $this->respondWithSuccess(Plugin::t("Flag rescheduled to {expiredAt}", [
                'expiredAt' => $expiredAt->format('Y-m-d'),
            ]));
        } catch (Exception $e) {
            if ($this->request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'error' => Plugin::t('Could not reschedule flag, check the logs for details')
                ]);
            }
            return $this->respondWithError(Plugin::t('Could not reschedule flag, check the logs for details'));
        }
    }
}
