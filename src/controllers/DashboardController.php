<?php

namespace neverstale\neverstale\controllers;

use Craft;
use neverstale\neverstale\elements\Content;
use neverstale\neverstale\Plugin;
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
            'settings' => Plugin::getInstance()->getSettings(),
            'contentStats' => $this->getContentEvaluationStats(),
        ]);
    }

    /**
     * Get content evaluation statistics for dashboard
     */
    private function getContentEvaluationStats(): array
    {
        $stats = [
            'total' => 0,
            'flagged' => 0,
            'synced' => 0,
            'pending' => 0,
            'ignored' => 0,
            'recentEntries' => [],
        ];

        // Get all content elements
        $contentElements = Content::find()
            ->with(['entry'])
            ->limit(1000)
            ->all();

        $stats['total'] = count($contentElements);

        foreach ($contentElements as $content) {
            $analysisStatus = $content->getAnalysisStatus();
            $flagCount = $content->getActiveFlagCount();

            // Count by analysis status
            switch ($analysisStatus->value) {
                case 'analyzed_flagged':
                case 'analyzed_clean':
                    $stats['synced']++;
                    break;
                case 'unsent':
                case 'pending_initial_analysis':
                case 'pending_reanalysis':
                case 'processing_initial_analysis':
                case 'processing_reanalysis':
                    $stats['pending']++;
                    break;
            }

            // Count flagged content
            if ($flagCount > 0) {
                $stats['flagged']++;
            }

            // Collect recent flagged entries for display
            $entry = $content->getEntry();
            if ($flagCount > 0 && $entry && count($stats['recentEntries']) < 5) {
                $stats['recentEntries'][] = [
                    'entry' => $entry,
                    'flagCount' => $flagCount,
                    'syncStatus' => $analysisStatus->value,
                    'ignored' => false, // TODO: Add ignored functionality if needed
                ];
            }
        }

        return $stats;
    }

    public function actionHealth(): Response
    {
        Craft::$app->getSession()->setNotice(
            Plugin::t('Refreshed Neverstale connection status')
        );

        $redirect = $this->request->getRequiredParam('redirect');

        Plugin::getInstance()->content->checkCanConnect(true);

        return $this->redirect($redirect);
    }
}
