<?php

namespace zaengle\neverstale;

use Craft;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LogLevel;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\elements\Entry;
use craft\events\DefineAttributeHtmlEvent;
use craft\events\DefineBehaviorsEvent;
use craft\events\ElementIndexTableAttributeEvent;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\Cp as CpHelper;
use craft\log\MonologTarget;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\web\UrlManager;
use craft\web\twig\variables\Cp as CpVariable;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;
use yii\base\InvalidConfigException;
use zaengle\neverstale\behaviors\PreviewNeverstaleSubmissionBehavior;
use zaengle\neverstale\elements\NeverstaleSubmission;
use zaengle\neverstale\enums\Permission;
use zaengle\neverstale\enums\SubmissionStatus;
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
 * Neverstale Craft Plugin
 *
 * Use the Neverstale API to find and manage stale content in your Craft CMS site.
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
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

    public static string $neverstaleStatusAttribute = 'neverstaleStatus';

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
        $this->registerLogTarget();
        $this->attachEventHandlers();

        Craft::$app->onInit(function() {
            $this->setComponents([
                'api' => [
                    'class' => Api::class,
                    'client' => new ApiClient([
                        'apiKey' => $this->getSettings()->apiKey,
                    ]),
                ],
            ]);
        });
    }
    /**
     * Logs an informational message to our custom log target.
     */
    public static function info(string|array $message): void
    {
        self::log($message);
    }
    /**
     * Logs an error message to our custom log target.
     */
    public static function error(string|array $message): void
    {
        self::log($message);
    }

    /**
     * Logs a message to our custom log target.
     *
     * @param string|array<mixed> $message
     * @param string $level
     * @see Logger::log()
     * @see registerLogTarget()
     */
    public static function log(string|array $message, string $level = LogLevel::INFO): void
    {
        Craft::getLogger()->log($message, $level, self::getInstance()->getHandle());
    }
    public static function t():string
    {
        return Craft::t('neverstale', ...func_get_args());
    }

    /**
     * @throws InvalidConfigException
     */
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
        $this->registerEntryTableAttributes();

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

        /**
         * Attach the NeverstaleSubmission behavior to the Entry element, adding a neverstaleSubmission property that points
         * to the most recent submission for the entry
         */
        Event::on(Entry::class, Model::EVENT_DEFINE_BEHAVIORS, function (DefineBehaviorsEvent $event) {
            /** @var Entry $entry */
            $entry = $event->sender;

            $event->behaviors['previewNeverstaleSubmission'] = PreviewNeverstaleSubmissionBehavior::class;
        });
    }

    private function registerEntryTableAttributes(): void
    {
        Event::on(Entry::class, Entry::EVENT_REGISTER_TABLE_ATTRIBUTES, function(RegisterElementTableAttributesEvent $event) {
            $event->tableAttributes[self::$neverstaleStatusAttribute] = [
                'label' => Plugin::t('Neverstale Status')
            ];
        });

        Event::on(Entry::class, Entry::EVENT_PREP_QUERY_FOR_TABLE_ATTRIBUTE, function (ElementIndexTableAttributeEvent $event) {
            $attr = $event->attribute;
            if ($attr !== self::$neverstaleStatusAttribute) {
                return;
            }
            $query = $event->query;
            // @todo make this work

//        dd($query);
//
//        $alias = 'neverstaleSubmissions';
//        $table = "{{%neverstale_submissions}}";
//
//        $joinTable = [$alias => $table];
//        $query->innerJoin($joinTable,"[[$alias.id]] = [[elements.id]]");
        });
        Event::on(Entry::class, Element::EVENT_DEFINE_ATTRIBUTE_HTML, [$this, 'entryTableAttributeHtml']);
        Event::on(Entry::class, Element::EVENT_DEFINE_INLINE_ATTRIBUTE_INPUT_HTML, [$this, 'entryTableAttributeHtml']);
    }
    public function entryTableAttributeHtml(DefineAttributeHtmlEvent $event): void
    {
        if ($event->attribute !== self::$neverstaleStatusAttribute) {
            return;
        }
        $submission = $event->sender->getNeverstaleSubmission();
        if (!$submission) {
            $event->html = '';
        } else {
            $status = SubmissionStatus::from($submission->status);

            $event->html = CpHelper::statusLabelHtml([
                'color' => $status->color(),
                'icon' => $status->icon(),
                'label' => $status->label(),
            ]);
        }
    }

    private function registerOnElementSaveHandler(): void
    {
        Event::on(
            Entry::class,
            Element::EVENT_AFTER_SAVE,
            function(ModelEvent $event) {
                /**
                 * @var Entry $entry
                 */
                $entry = $event->sender;
                if ($this->element->isSubmittable($entry)) {
                    $this->submission->queue($entry);
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
                        Permission::View->value => [
                            'label' => 'View Neverstale submissions',
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
                'neverstale' => ['template' => 'neverstale/submissions/_index.twig'],
                'neverstale/submissions' => ['template' => 'neverstale/submissions/_index.twig'],
                'neverstale/submissions/<submissionId:\\d+>' =>  'neverstale/submissions/show',
            ]);
        });
    }

    private function registerCpNavItems():void
    {
        Event::on(
            CpVariable::class,
            CpVariable::EVENT_REGISTER_CP_NAV_ITEMS,
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
    private function registerLogTarget(): void
    {
        Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
            'name' => self::getInstance()->getHandle(),
            'categories' => [self::getInstance()->getHandle()],
            'level' => LogLevel::INFO,
            'logContext' => false,
            'allowLineBreaks' => false,
            'formatter' => new LineFormatter(
                format: "%datetime% %message%\n",
                dateFormat: 'Y-m-d H:i:s',
            ),
        ]);
    }
}
