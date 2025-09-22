<?php

namespace neverstale\neverstale\registrars;

use Craft;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\web\twig\variables\Cp;
use craft\web\UrlManager;
use neverstale\neverstale\Plugin;
use yii\base\Event;

/**
 * Handles registration of CP routes and navigation items
 */
class RouteRegistrar implements RegistrarInterface
{
    public function register(): void
    {
        $this->registerCPRoutes();
        $this->registerSiteRoutes();
        $this->registerNavigation();
    }

    /**
     * Register Control Panel URL routes
     */
    private function registerCPRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules = array_merge([
                    'settings/plugins/neverstale' => 'neverstale/settings/edit',
                    'neverstale' => 'neverstale/dashboard/index',
                    'neverstale/refresh-connection-health' => 'neverstale/dashboard/health',
                    'neverstale/content' => ['template' => 'neverstale/content/_index'],
                    'neverstale/content/<contentId:\\d+>' => 'neverstale/content/show',
                    'neverstale/content/batch-ingest' => 'neverstale/content/batch-ingest',
                    'neverstale/flags' => ['template' => 'neverstale/flags/_index'],
                    'neverstale/flags/<flagId:\\d+>' => 'neverstale/flags/show',
                    'neverstale/bulk-ingest' => 'neverstale/bulk-ingest/index',
                    'neverstale/bulk-ingest/start' => 'neverstale/bulk-ingest/start-bulk-ingest',
                    'neverstale/flag/ignore-slideout' => 'neverstale/flag/ignore-slideout',
                    'neverstale/flag/ignore' => 'neverstale/flag/ignore',
                    'neverstale/flag/reschedule' => 'neverstale/flag/reschedule',
                    'neverstale/settings' => 'neverstale/settings/edit',
                ], $event->rules);
            }
        );
    }

    /**
     * Register Site URL routes (for webhooks)
     */
    private function registerSiteRoutes(): void
    {
        // Note: Removed custom route registration as Craft should handle
        // actions/neverstale/webhooks automatically via controller naming
    }

    /**
     * Register CP navigation items
     */
    private function registerNavigation(): void
    {
        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function (RegisterCpNavItemsEvent $event) {
                $navItems = [
                    'dashboard' => [
                        'label' => Plugin::t('Dashboard'),
                        'url' => 'neverstale',
                    ],
                    'content' => [
                        'label' => Plugin::t('Content'),
                        'url' => 'neverstale/content',
                    ],
                    'bulk-ingest' => [
                        'label' => Plugin::t('Bulk Ingest'),
                        'url' => 'neverstale/bulk-ingest',
                    ],
                ];

                if (
                    Craft::$app->getUser()->getIsAdmin() &&
                    Craft::$app->getConfig()->getGeneral()->allowAdminChanges
                ) {
                    $navItems['settings'] = [
                        'label' => Plugin::t('Settings'),
                        'url' => 'neverstale/settings',
                    ];
                }

                $event->navItems[] = [
                    'url' => 'neverstale',
                    'label' => Plugin::t('Neverstale'),
                    'icon' => '@neverstale/neverstale/resources/icon.svg',
                    'subnav' => $navItems,
                ];
            }
        );
    }
}
