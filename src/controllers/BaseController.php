<?php

namespace neverstale\craft\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;
use neverstale\craft\Plugin;

/**
 * Base controller
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 *
 * @property-read Plugin $plugin
 *
 */
class BaseController extends Controller
{
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;
    public function getPlugin(): Plugin
    {
        return Plugin::getInstance();
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
