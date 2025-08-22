<?php

namespace neverstale\neverstale\controllers;

use Craft;
use craft\web\Controller;
use neverstale\neverstale\Plugin;
use yii\web\Response;

/**
 * Base controller for Neverstale plugin
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   1.0.0
 *
 * @property-read Plugin $plugin
 */
class BaseController extends Controller
{
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
