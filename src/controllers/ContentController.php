<?php

namespace neverstale\craft\controllers;

use Craft;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use neverstale\craft\elements\NeverstaleContent;
use neverstale\craft\enums\Permission;
use neverstale\craft\Plugin;
use neverstale\craft\web\assets\neverstale\NeverstaleAsset;

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

        $this->view->registerAssetBundle(NeverstaleAsset::class);

        return $this->renderTemplate('neverstale/content/_show', [
            'content' => $content,
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

        $content = $this->plugin->content->retrieveByCustomId($customId);

        if (!$content) {
            return $this->asFailure('Could not fetch content for customId: ' . $customId);
        }

        return $this->asJson([
            'success' => true,
            'data' => $content->toArray(),
        ]);
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
        if (!$this->plugin->content->refresh($content)) {
            return $this->respondWithError(Plugin::t('Could not refresh content'));
        }

        return $this->respondWithSuccess(Plugin::t('Content refreshed'));
    }

    /**
     * Delete a content item in Craft + the Neverstale API
     *
     * @see https://neverstale.io/docs/content.html#deleting-content
     * @see NeverstaleContent::beforeDelete()
     *
     * @throws ForbiddenHttpException
     * @throws MethodNotAllowedHttpException
     * @throws \Throwable
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Permission::Delete->value);

        $contentId = $this->request->getParam('contentId');

        if (!Craft::$app->getElements()->deleteElementById($contentId)) {
            return $this->respondWithError(Plugin::t('Could not delete content'));
        }

        return $this->respondWithSuccess(Plugin::t('Content was deleted'));
    }

    /**
     * (Re-)ingest content to the Neverstale API
     *
     * @throws MethodNotAllowedHttpException
     * @throws ForbiddenHttpException
     */
    public function actionIngest(): Response
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
    public function actionResetLogs(): Response
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
