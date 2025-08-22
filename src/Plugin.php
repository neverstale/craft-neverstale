<?php

namespace neverstale\neverstale;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use neverstale\neverstale\models\Settings;
use neverstale\neverstale\registrars\ElementRegistrar;
use neverstale\neverstale\registrars\EventRegistrar;
use neverstale\neverstale\registrars\PermissionRegistrar;
use neverstale\neverstale\registrars\RegistrarInterface;
use neverstale\neverstale\registrars\RouteRegistrar;
use neverstale\neverstale\registrars\TwigRegistrar;
use neverstale\neverstale\services\Config;
use neverstale\neverstale\services\Content;
use neverstale\neverstale\services\Flag;
use neverstale\neverstale\services\FlagManager;
use neverstale\neverstale\services\Format;
use neverstale\neverstale\services\TransactionLog;
use neverstale\neverstale\traits\HasOwnLogFile;
use neverstale\neverstale\traits\WebhookLogger;

/**
 * Neverstale plugin
 *
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 * @author    Zaengle <jesse@zaengle.com>
 * @copyright Zaengle
 * @license   MIT
 */
class Plugin extends BasePlugin
{
    use HasOwnLogFile;
    use WebhookLogger;

    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;
    public bool $hasReadOnlyCpSettings = true;

    public static function t(): string
    {
        return Craft::t('neverstale', ...func_get_args());
    }

    public function init(): void
    {
        parent::init();

        $this->registerLogTarget();

        // Use registrar pattern for modular initialization
        $this->loadRegistrars([
            RouteRegistrar::class,
            EventRegistrar::class,
            ElementRegistrar::class,
            PermissionRegistrar::class,
            TwigRegistrar::class,
        ]);

        // Any code that creates an element query or loads Twig should be deferred until
        // after Craft is fully initialized, to avoid conflicts with other plugins/modules
        Craft::$app->onInit(function () {
            $this->setComponents([
                'config' => Config::class,
                'content' => Content::class,
                'format' => Format::class,
                'entry' => services\Entry::class,
                'transactionLog' => TransactionLog::class,
                'flag' => Flag::class,
                'flagManager' => FlagManager::class,
            ]);
        });
    }

    /**
     * Dynamically load and register plugin registrars
     *
     * @param  array<class-string<RegistrarInterface>>  $registrarClasses
     */
    private function loadRegistrars(array $registrarClasses): void
    {
        foreach ($registrarClasses as $registrarClass) {
            $registrar = new $registrarClass();

            $registrar->register();
        }
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        // This method is now bypassed by the custom route
        return null;
    }

}
