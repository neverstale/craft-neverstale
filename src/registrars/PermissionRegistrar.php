<?php

namespace neverstale\neverstale\registrars;

use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;
use neverstale\neverstale\enums\Permission;
use yii\base\Event;

/**
 * Handles registration of user permissions
 */
class PermissionRegistrar implements RegistrarInterface
{
    public function register(): void
    {
        $this->registerUserPermissions();
    }

    /**
     * Register user permissions for the plugin
     */
    private function registerUserPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            static function (RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => 'Neverstale',
                    'permissions' => [
                        Permission::Scan->value => [
                            'label' => Plugin::t('Scan site content for stale entries'),
                        ],
                        Permission::View->value => [
                            'label' => Plugin::t('View Neverstale Content'),
                        ],
                        Permission::Delete->value => [
                            'label' => Plugin::t('Delete Neverstale Content'),
                        ],
                        Permission::Ingest->value => [
                            'label' => Plugin::t('Ingest Content to Neverstale'),
                        ],
                        Permission::BulkIngest->value => [
                            'label' => Plugin::t('Bulk Ingest Content to Neverstale'),
                            'info' => Plugin::t('Allows bulk processing of multiple content items at once'),
                        ],
                        Permission::CancelBulkIngest->value => [
                            'label' => Plugin::t('Cancel Bulk Ingest Operations'),
                            'info' => Plugin::t('Allows cancelling running bulk ingest operations'),
                        ],
                        Permission::ClearLogs->value => [
                            'label' => Plugin::t('Clear Transaction Logs'),
                        ],
                    ],
                ];
            }
        );
    }
}
