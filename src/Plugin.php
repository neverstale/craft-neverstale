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
use craft\events\DefineHtmlEvent;
use craft\events\ElementIndexTableAttributeEvent;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\App;
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
use zaengle\neverstale\behaviors\HasNeverstaleContentBehavior;
use zaengle\neverstale\elements\NeverstaleContent;
use zaengle\neverstale\enums\AnalysisStatus;
use zaengle\neverstale\enums\Permission;
use zaengle\neverstale\models\Settings;
use zaengle\neverstale\services\Config;
use zaengle\neverstale\services\Content;
use zaengle\neverstale\services\Entry as EntryService;
use zaengle\neverstale\services\Flag;
use zaengle\neverstale\services\Format as FormatService;
use zaengle\neverstale\services\TransactionLog;
use zaengle\neverstale\support\ApiClient;
use zaengle\neverstale\utilities\PreviewContent;
use zaengle\neverstale\utilities\ScanUtility;
use zaengle\neverstale\web\twig\Neverstale;

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
 * @property-read EntryService $entry
 * @property-read FormatService $format
 * @property-read Settings $settings

 * @property-read Config $config
 * @property-read Content $content
 * @property-read TransactionLog $transactionLog
 * @property-read Flag $flag
 */
class Plugin extends BasePlugin
{
    /**
     * @var mixed|object|null
     */
    public mixed $flags;
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;
    public ApiClient $client;
    public const STATUS_ATTRIBUTE = 'neverstaleStatus';
    public const DATE_ANALYZED_ATTRIBUTE = 'neverstaleDateAnalyzed';
    public const DATE_EXPIRED_ATTRIBUTE = 'neverstaleDateExpired';
    public const FLAG_COUNT_ATTRIBUTE = 'neverstaleFlagCount';
    /**
     * @inheritDoc
     */
    public static function config(): array
    {
        return [];
    }
    public function init(): void
    {
        parent::init();

        $this->registerLogTarget();
        $this->attachEventHandlers();

        $this->client = new ApiClient([
            'baseUri' => App::env('NEVERSTALE_API_BASE_URI'),
            'apiKey' => $this->getSettings()->apiKey,
        ]);

        Craft::$app->onInit(function() {
            $this->setComponents([
                'config' => Config::class,
                'content' => [
                    'class' => Content::class,
                    'client' => $this->client,
                ],
                'entry' => EntryService::class,
                'format' => FormatService::class,
                'flag' => [
                    'class' => Flag::class,
                    'client' => $this->client,
                ],
                'transactionLog' => TransactionLog::class,
            ]);
        });

        Craft::$app->view->registerTwigExtension(new Neverstale());
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
    public static function t(): string
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
        $this->registerEntrySidebarHtml();

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
         * Attach the HasNeverstaleContentBehavior behavior to the Entry element, adding a neverstaleContent property that points
         * to the element that represents the content in the Neverstale API.
         */
        Event::on(Entry::class, Model::EVENT_DEFINE_BEHAVIORS, function(DefineBehaviorsEvent $event) {
            $event->behaviors['previewNeverstaleContent'] = HasNeverstaleContentBehavior::class;
        });

        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITIES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = PreviewContent::class;
        });
    }
    private function registerEntrySidebarHtml(): void
    {
        Event::on(
            Entry::class,
            Element::EVENT_DEFINE_SIDEBAR_HTML,
            static function(DefineHtmlEvent $event) {
                $entry = $event->sender;
                $content = $entry->getNeverstaleContent();

                if (!$content) {
                    return;
                }

                // @todo register asset bundle

                $event->html .= Craft::$app->view->renderTemplate('neverstale/entry/_sidebar', [
                    'content' => $content,
                    'customId' => Plugin::getInstance()->format->forIngest($content)->customId,
                ]);
            });
    }
    private function registerEntryTableAttributes(): void
    {
        Event::on(Entry::class, Entry::EVENT_REGISTER_TABLE_ATTRIBUTES, function(RegisterElementTableAttributesEvent $event) {
            $event->tableAttributes[self::STATUS_ATTRIBUTE] = [
                'label' => Plugin::t('Neverstale Status'),
            ];
            $event->tableAttributes[self::DATE_ANALYZED_ATTRIBUTE] = [
                'label' => Plugin::t('Content Analyzed Date'),
            ];
            $event->tableAttributes[self::DATE_EXPIRED_ATTRIBUTE] = [
                'label' => Plugin::t('Content Expired Date'),
            ];
            $event->tableAttributes[self::FLAG_COUNT_ATTRIBUTE] = [
                'label' => Plugin::t('Flag Count'),
            ];
        });

        Event::on(Entry::class, Entry::EVENT_PREP_QUERY_FOR_TABLE_ATTRIBUTE, function(ElementIndexTableAttributeEvent $event) {
            $attr = $event->attribute;
            if ($attr !== self::STATUS_ATTRIBUTE) {
                return;
            }
            $query = $event->query;
            // @todo make this work
        });
        Event::on(Entry::class, Element::EVENT_DEFINE_ATTRIBUTE_HTML, [$this, 'entryTableAttributeHtml']);
        Event::on(Entry::class, Element::EVENT_DEFINE_INLINE_ATTRIBUTE_INPUT_HTML, [$this, 'entryTableAttributeHtml']);
    }
    public function entryTableAttributeHtml(DefineAttributeHtmlEvent $event): void
    {
        /** @var Entry $entry */
        $entry = $event->sender;
        $event->html = match ($event->attribute) {
            self::STATUS_ATTRIBUTE => $this->getStatusAttributeHtml($entry),
            self::DATE_ANALYZED_ATTRIBUTE, self::DATE_EXPIRED_ATTRIBUTE => $this->getDateAttributeHtml($event->attribute, $entry),
            self::FLAG_COUNT_ATTRIBUTE => $entry->getNeverstaleContent()?->flagCount ?? 0,
            default => null,
        };
    }
    private function getStatusAttributeHtml(Entry $entry): string
    {
        $content = $entry->getNeverstaleContent();

        if (!$content) {
            return '';
        }

        $status = AnalysisStatus::from($content->status);

        return CpHelper::statusLabelHtml([
            'color' => $status->color(),
            'icon' => $status->icon(),
            'label' => $status->label(),
        ]);
    }

    private function getDateAttributeHtml(string $attribute, Entry $entry): string
    {
        $content = $entry->getNeverstaleContent();

        if (!$content) {
            return '';
        }
        $contentAttr = match ($attribute) {
            self::DATE_ANALYZED_ATTRIBUTE => 'dateAnalyzed',
            self::DATE_EXPIRED_ATTRIBUTE => 'dateExpired',
            default => '',
        };

        return $content->{$contentAttr} ? Craft::$app->formatter->asTimestamp($content->{$contentAttr}) : '--';
    }
    /**
     * @see \zaengle\neverstale\services\Entry
     */
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
                if ($this->entry->shouldIngest($entry)) {
                    $this->content->queue($entry);
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
                            'label' => self::t('Scan site content for stale entries'),
                        ],
                        Permission::View->value => [
                            'label' => self::t('View Neverstale Content'),
                        ],
                        Permission::Delete->value => [
                            'label' => self::t('Delete Neverstale Content'),
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
            $event->types[] = NeverstaleContent::class;
        });
    }
    private function registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, [
                'neverstale' => ['template' => 'neverstale/content/_index.twig'],
                'neverstale/content' => ['template' => 'neverstale/content/_index.twig'],
                'neverstale/content/<contentId:\\d+>' => 'neverstale/content/show',
            ]);
        });
    }
    private function registerCpNavItems(): void
    {
        Event::on(
            CpVariable::class,
            CpVariable::EVENT_REGISTER_CP_NAV_ITEMS,
            function(RegisterCpNavItemsEvent $event) {
                $event->navItems[] = [
                    'url' => 'neverstale',
                    'label' => self::t('Neverstale'),
                    'icon' => '@neverstale/resources/icon.svg',
                    'subnav' => [
                        'content' => [
                            'label' => self::t('Content'),
                            'url' => 'neverstale/content',
                        ],
                        'settings' => [
                            'label' => self::t('Settings'),
                            'url' => 'settings/plugins/neverstale',
                        ],
                    ],
                ];
            }
        );
    }
    /**
     * Write log messages to a custom log target
     */
    private function registerLogTarget(): void
    {
        Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
            'name' => self::getInstance()->getHandle(),
            'categories' => [self::getInstance()->getHandle()],
            'level' => LogLevel::INFO,
            'logContext' => false,
            'allowLineBreaks' => true,
            'formatter' => new LineFormatter(
                format: "%datetime% %message%\n",
                dateFormat: 'Y-m-d H:i:s',
            ),
        ]);
    }
}
