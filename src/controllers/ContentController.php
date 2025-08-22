<?php

namespace neverstale\neverstale\controllers;

use Craft;
use neverstale\neverstale\elements\Content;
use neverstale\neverstale\enums\Permission;
use neverstale\neverstale\Plugin;
use Throwable;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Neverstale Content Controller
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   1.0.0
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
     * @param  Content|null  $content
     * @param  int|null      $contentId
     * @return Response
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     * @throws InvalidConfigException
     */
    public function actionShow(?Content $content, ?int $contentId = null): Response
    {
        $this->requirePermission(Permission::View->value);

        if ($content === null) {
            $content = Content::find()->id($contentId)->siteId('*')->one();

            if (! $content) {
                throw new NotFoundHttpException('Content not found');
            }
        }

        // Eager-load the transaction logs
        if ($content->id && $content->getRecord()) {
            $transactionLogs = $content->getRecord()->getTransactionLogs()->all();
        } else {
            $transactionLogs = [];
        }

        return $this->renderTemplate('neverstale/content/_show', [
            'content' => $content,
            'title' => $content->getUiLabel(),
            'transactionLogs' => $transactionLogs,
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

        $content = Plugin::getInstance()->content->retrieveByCustomId($customId);

        if (! $content) {
            return $this->asFailure('Could not fetch content for customId: '.$customId);
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

        $content = Content::findOne($contentId);

        if (! $content) {
            return $this->respondWithError(Plugin::t('Content not found'));
        }
        if (! Plugin::getInstance()->content->refresh($content)) {
            return $this->respondWithError(Plugin::t('Could not refresh content'));
        }

        return $this->respondWithSuccess(Plugin::t('Content refreshed'));
    }

    /**
     * Delete a content item in Craft + the Neverstale API
     *
     * @see https://neverstale.io/docs/content.html#deleting-content
     * @see Content::beforeDelete()
     *
     * @throws ForbiddenHttpException
     * @throws MethodNotAllowedHttpException
     * @throws Throwable
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Permission::Delete->value);

        $contentId = $this->request->getParam('contentId');

        if (! Craft::$app->getElements()->deleteElementById($contentId)) {
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
        $content = Content::findOne(['id' => $contentId]);

        if (! $content) {
            return $this->respondWithError(Plugin::t("Content #{id} not found", ['id' => $contentId]));
        }

        if (! Plugin::getInstance()->content->ingest($content)) {
            return $this->respondWithError(Plugin::t("Could not ingest content #{id}", ['id' => $contentId]));
        }

        return $this->respondWithSuccess(Plugin::t("Content #{id} was ingested", ['id' => $contentId]));
    }

    /**
     * Batch ingest multiple content items to the Neverstale API
     *
     * @throws MethodNotAllowedHttpException
     * @throws ForbiddenHttpException
     */
    public function actionBatchIngest(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Permission::Ingest->value);

        $contentIds = $this->request->getBodyParam('contentIds', []);

        if (empty($contentIds)) {
            return $this->respondWithError(Plugin::t('No content items selected'));
        }

        $contents = Content::find()
            ->id($contentIds)
            ->all();

        if (empty($contents)) {
            return $this->respondWithError(Plugin::t('No valid content items found'));
        }

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($contents as $content) {
            if (Plugin::getInstance()->content->ingest($content)) {
                $successCount++;
            } else {
                $errorCount++;
                $errors[] = Plugin::t("Failed to ingest content #{id}", ['id' => $content->id]);
            }
        }

        if ($errorCount === 0) {
            return $this->respondWithSuccess(Plugin::t('Successfully ingested {count} content items', ['count' => $successCount]));
        } elseif ($successCount === 0) {
            return $this->respondWithError(Plugin::t('Failed to ingest all content items').': '.implode(', ', $errors));
        } else {
            return $this->asJson([
                'success' => true,
                'message' => Plugin::t('Ingested {successCount} of {total} content items. {errorCount} failed.', [
                    'successCount' => $successCount,
                    'total' => count($contents),
                    'errorCount' => $errorCount,
                ]),
                'errors' => $errors,
            ]);
        }
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
        $content = Content::findOne(['id' => $contentId]);

        if (! $content) {
            return $this->respondWithError(Plugin::t("Content #{id} not found", ['id' => $contentId]));
        }

        if (! Plugin::getInstance()->transactionLog->deleteFor($content)) {
            return $this->respondWithError(Plugin::t("Could reset transaction logs for content #{id}", ['id' => $contentId]));
        }

        return $this->respondWithSuccess(Plugin::t("Reset transaction logs for Content #{id}", ['id' => $contentId]));
    }
}
