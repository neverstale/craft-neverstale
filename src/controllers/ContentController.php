<?php

namespace zaengle\neverstale\controllers;

use Craft;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use zaengle\neverstale\elements\NeverstaleContent;
use zaengle\neverstale\enums\Permission;
use zaengle\neverstale\Plugin;
use zaengle\neverstale\web\assets\neverstale\NeverstaleAsset;

/**
 * Neverstale Content Controller
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 *
 * @property-read Plugin $module
 */
class ContentController extends BaseController
{
    /**
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     */
    public function actionShow(?NeverstaleContent $content, ?int $contentId = null): Response
    {
        $this->requireCpRequest();

        if ($content === null) {
            $content = NeverstaleContent::find()->id($contentId)->siteId('*')->one();

            if (!$content) {
                throw new NotFoundHttpException('Content not found');
            }
        }

        try {
            $flagData = $this->plugin->content->fetchByCustomId($content->customId)['data'];
        } catch (\Exception $e) {
            Plugin::error($e->getMessage());
            $flagData = null;
        }

        $this->view->registerAssetBundle(NeverstaleAsset::class);

        return $this->renderTemplate('neverstale/content/_show', [
            'content' => $content,
            'flagData' => $flagData,
            'title' => $content->title,
        ]);
    }

    public function actionRefresh(): Response
    {
        $this->requireCpRequest();
        $this->requirePostRequest();
        $contentId = $this->request->getRequiredBodyParam('contentId');

        $content = NeverstaleContent::findOne($contentId);

        if (!$content) {
            throw new NotFoundHttpException('Content not found');
        }

        $this->plugin->content->refresh($content);

        return $this->redirectToPostedUrl();
    }

    public function actionDelete(): ?Response
    {
        $this->requireCpRequest();
        $this->requirePostRequest();

        $session = Craft::$app->getSession();

        $contentId = $this->request->getParam('contentId');

        if (!Craft::$app->getElements()->deleteElementById($contentId)) {
            $session->setError(Plugin::t('Could not delete content'));

            return null;
        }

        $session->setNotice(Plugin::t('Content was deleted.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * (Re-)ingest content to the Neverstale API
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

        $contentId = $this->request->getParam('contentId');
        $content = NeverstaleContent::findOne(['id' => $contentId]);

        if (!$content) {
            $session->setError(Plugin::t("Content #{id} not found", ['id' => $contentId]));

            return null;
        }

        if (!Plugin::getInstance()->content->ingest($content)) {
            $session->setError(Plugin::t("Could not ingest content #{id}", ['id' => $contentId]));

            return null;
        }

        $session->setNotice(Plugin::t("Content #{id} was ingested", ['id' => $contentId]));


        return $this->redirectToPostedUrl();
    }
    public function actionResetLogs()
    {
        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requirePermission(Permission::Ingest->value);
        $session = Craft::$app->getSession();
        $contentId = $this->request->getParam('contentId');
        $content = NeverstaleContent::findOne(['id' => $contentId]);

        if (!$content) {
            $session->setError(Plugin::t("Content #{id} not found", ['id' => $contentId]));

            return null;
        }

        if (!Plugin::getInstance()->transactionLog->deleteFor($content)) {
            $session->setError(Plugin::t("Could reset transaction logs for content #{id}", ['id' => $contentId]));

            return null;
        }

        $session->setNotice(Plugin::t("Reset transaction logs for Content #{id}", ['id' => $contentId]));

        return $this->redirectToPostedUrl();
    }
}
