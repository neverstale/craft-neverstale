<?php

namespace neverstale\craft\controllers;

use Craft;
use neverstale\craft\Plugin;
use yii\web\Response;

/**
 * Dashboard controller
 */
class DashboardController extends BaseController
{
    public $defaultAction = 'index';
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;


    public function beforeAction($action): bool
    {
        $this->requireCpRequest();

        return parent::beforeAction($action);
    }
    /**
     * neverstale/dashboard action
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('neverstale/_dashboard', [
            'settings' => $this->plugin->getSettings(),
        ]);
    }

    public function actionHealth(): Response
    {
        Craft::$app->getSession()->setNotice(
            Plugin::t('Refreshed Neverstale connection status')
        );

        $redirect = $this->request->getRequiredParam('redirect');

        $this->plugin->content->checkCanConnect(true);
        return $this->redirect($redirect);
    }
}
