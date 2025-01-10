<?php

namespace zaengle\neverstale\controllers;

use Craft;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
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
    public function beforeAction($action): bool
    {
        $this->requireCpRequest();

        return parent::beforeAction($action);
    }

    /**
     * Render the Twig template for showing a content item
     *
     * @param NeverstaleContent|null $content
     * @param int|null $contentId
     * @return Response
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionShow(?NeverstaleContent $content, ?int $contentId = null): Response
    {
        $this->requirePermission(Permission::View->value);

        if ($content === null) {
            $content = NeverstaleContent::find()->id($contentId)->siteId('*')->one();

            if (!$content) {
                throw new NotFoundHttpException('Content not found');
            }
        }

        // @todo remove this once Twig rendering is removed
        try {
            $flagData = $this->plugin->content->fetchByCustomId($content->customId)['data'];
        } catch (\Exception $e) {
            Plugin::error($e->getMessage());
            $flagData = null;
        }

        $this->view->registerAssetBundle(NeverstaleAsset::class);

        return $this->renderTemplate('neverstale/content/_show', [
            'content' => $content,
            'flagData' => $flagData, // @todo remove this once Twig rendering is removed
            'title' => $content->title,
        ]);
    }

    /**
     * Fetch a content item direct from the Neverstale API by its customId
     *
     * - intended for use by the FE widget
     * - requires a customId param in the request
     * - requires a CP request
     * - must accept application/json
     * - does not include any additional data from Craft
     *
     * @return Response
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     */
    public function actionFetch(): Response
    {
        $this->requirePermission(Permission::View->value);
        $this->requireAcceptsJson();

        $customId = $this->request->getRequiredParam('customId');

        try {
            $content = $this->plugin->content->fetchByCustomId($customId);

            return $this->asJson(array_merge($content, [
                'success' => true,
            ]));
        } catch (\Exception $e) {
            return $this->asFailure($e->getMessage());
        }
    }

    /**
     * Refreshes Craft's data about a content item with the latest from the Neverstale API
     *
     * @return Response
     * @throws BadRequestHttpException
     * @throws MethodNotAllowedHttpException
     */
    public function actionRefresh(): Response
    {
        $this->requirePostRequest();
        $contentId = $this->request->getRequiredBodyParam('contentId');

        $content = NeverstaleContent::findOne($contentId);

        if (!$content) {
            return $this->respondWithError(Plugin::t('Content not found'));
        }

        $this->plugin->content->refresh($content);

        return $this->respondWithSuccess(Plugin::t('Content refreshed'));
    }

    /**
     * Delete a content item in Craft + the Neverstale API
     * @return Response
     * @throws ForbiddenHttpException
     * @throws MethodNotAllowedHttpException
     * @throws \Throwable
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Permission::Delete->value);

        $contentId = $this->request->getParam('contentId');

        // @todo delete the item in NS

        if (!Craft::$app->getElements()->deleteElementById($contentId)) {
            return $this->respondWithError(Plugin::t('Could not delete content'));
        }

        return $this->respondWithSuccess(Plugin::t('Content was deleted'));
    }

    /**
     * (Re-)ingest content to the Neverstale API
     *
     * @return Response|null
     * @throws MethodNotAllowedHttpException
     * @throws ForbiddenHttpException
     */
    public function actionIngest()
    {
        $this->requirePostRequest();
        $this->requirePermission(Permission::Ingest->value);

        $contentId = $this->request->getParam('contentId');
        $content = NeverstaleContent::findOne(['id' => $contentId]);

        if (!$content) {
            return $this->respondWithError(Plugin::t("Content #{id} not found", ['id' => $contentId]));
        }

        if (!Plugin::getInstance()->content->ingest($content)) {
            return $this->respondWithError(Plugin::t("Could not ingest content #{id}", ['id' => $contentId]));
        }

        return $this->respondWithSuccess(Plugin::t("Content #{id} was ingested", ['id' => $contentId]));
    }

    /**
     * @throws ForbiddenHttpException
     * @throws MethodNotAllowedHttpException
     */
    public function actionResetLogs()
    {
        $this->requirePostRequest();
        $this->requirePermission(Permission::ClearLogs->value);

        $contentId = $this->request->getParam('contentId');
        $content = NeverstaleContent::findOne(['id' => $contentId]);

        if (!$content) {
            return $this->respondWithError(Plugin::t("Content #{id} not found", ['id' => $contentId]));
        }

        if (!Plugin::getInstance()->transactionLog->deleteFor($content)) {
            return $this->respondWithError(Plugin::t("Could reset transaction logs for content #{id}", ['id' => $contentId]));
        }

        return $this->respondWithSuccess(Plugin::t("Reset transaction logs for Content #{id}", ['id' => $contentId]));
    }
}
