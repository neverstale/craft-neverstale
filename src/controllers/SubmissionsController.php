<?php

namespace zaengle\neverstale\controllers;

use Craft;
use craft\web\Controller;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use zaengle\neverstale\elements\NeverstaleSubmission;
use zaengle\neverstale\Plugin;

/**
 * Neverstale Submissions Controller
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
class SubmissionsController extends Controller
{
    public $defaultAction = 'index';
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * @throws BadRequestHttpException
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
}
