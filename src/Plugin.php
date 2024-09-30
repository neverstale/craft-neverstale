<?php

namespace zaengle\neverstale;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\web\UrlManager;
use craft\web\twig\variables\Cp;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;
use zaengle\neverstale\elements\NeverstaleSubmission;
use zaengle\neverstale\enums\Permission;
use zaengle\neverstale\fields\NeverstaleSubmissions;
use zaengle\neverstale\models\Settings;
use zaengle\neverstale\services\Api;
use zaengle\neverstale\services\Config;
use zaengle\neverstale\services\Element as ElementService;
use zaengle\neverstale\services\Format as FormatService;
use zaengle\neverstale\services\Submission as SubmissionService;
use zaengle\neverstale\support\ApiClient;
use zaengle\neverstale\utilities\ScanUtility;

/**
 * NeverStale plugin
 *
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 *
 * @property-read ElementService $element
 * @property-read FormatService $format
 * @property-read Settings $settings
 * @property-read SubmissionService $submission
 * @property-read Config $config
 * @property-read Api $api
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    /**
     * @inheritDoc
     */
    public static function config(): array
    {
        return [
            'components' => [
                'element' => ElementService::class,
                'format' => FormatService::class,
                'submission' => SubmissionService::class,
                'config' => Config::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->attachEventHandlers();

        Craft::$app->onInit(function() {
            $this->setComponents([
                'api' => [
                    'class' => Api::class,
                    'client' => new ApiClient([
                        'apiKey' => $this->getSettings()->apiKey,
                        'apiSecret' => $this->getSettings()->apiSecret,
                    ]),
                ],
            ]);
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('neverstale/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    /**
     * Copy example config to project's config folder
     */
    protected function afterInstall(): void
    {
        $configSource = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config.example.php';
        $configTarget = Craft::$app->getConfig()->configDir . DIRECTORY_SEPARATOR . 'neverstale.php';

        if (!file_exists($configTarget)) {
            copy($configSource, $configTarget);
        }
    }

    private function attachEventHandlers(): void
    {
        $this->registerOnElementSaveHandler();
        $this->registerUtilities();
        $this->registerUserPermissions();
        $this->registerElementTypes();
        $this->registerCpRoutes();
        $this->registerCpNavItems();

        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = NeverstaleSubmissions::class;
        });

        /**
         * Expose the plugin on the craft variable
         */
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $e) {
                /** @var CraftVariable $variable */
                $variable = $e->sender;
                $variable->set('neverstale', $this);
            }
        );
    }


    private function registerOnElementSaveHandler(): void
    {
        Event::on(
            Element::class,
            Element::EVENT_AFTER_SAVE,
            function(ModelEvent $event) {
                /**
                 * @var Element $element
                 */
                $element = $event->sender;
                if ($this->element->isSubmittable($element)) {
                    $this->submission->queue($element);
                }
            }
        );
    }
    private function registerUserPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            static function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => 'Neverstale',
                    'permissions' => [
                        Permission::Scan->value => [
                            'label' => 'Scan site content for stale entries',
                        ],
                    ],
                ];
            }
        );
    }
    private function registerUtilities(): void
    {
        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITIES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = ScanUtility::class;
        });
    }

    private function registerElementTypes(): void
    {
        Event::on(Elements::class, Elements::EVENT_REGISTER_ELEMENT_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = NeverstaleSubmission::class;
        });
    }

    private function registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, [
                'neverstale' => ['template' => 'neverstale/_index.twig'],
                'neverstale/submissions' => ['template' => 'neverstale/submissions/_index.twig'],
                'neverstale/submissions/<elementId:\\d+>' =>  'elements/edit',
            ]);
        });
    }

    private function registerCpNavItems():void
    {
        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function(RegisterCpNavItemsEvent $event) {
                $event->navItems[] = [
                    'url' => 'neverstale',
                    'label' => 'Neverstale',
                    'icon' => '@neverstale/resources/icon.svg',
                    'subnav' => [
                        'submissions' => [
                            'label' => 'Submissions',
                            'url' => 'neverstale/submissions',
                        ],
                        'settings' => [
                            'label' => 'Settings',
                            'url' => 'settings/plugins/neverstale',
                        ],
                    ],
                ];
            }
        );

    }
}
