<?php

namespace neverstale\craft\controllers;

use Craft;
use yii\web\Response;

/**
 * Dashboard controller
 */
class DashboardController extends BaseController
{
    public $defaultAction = 'index';
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * neverstale/dashboard action
     */
    public function actionIndex(): Response
    {
        $this->requireCpRequest();

        return $this->renderTemplate('neverstale/_dashboard', [
            'settings' => $this->plugin->getSettings(),
        ]);
    }
}
