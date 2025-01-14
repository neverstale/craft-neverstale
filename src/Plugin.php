<?php

namespace zaengle\neverstale;

use Craft;
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
use craft\services\Elements;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\web\twig\variables\Cp as CpVariable;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;

use nystudio107\pluginvite\services\VitePluginService;

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
use zaengle\neverstale\services\Setup;
use zaengle\neverstale\services\Template;
use zaengle\neverstale\services\TransactionLog;
use zaengle\neverstale\support\ApiClient;
use zaengle\neverstale\traits\HasPluginLogfile;
use zaengle\neverstale\utilities\PreviewContent;
use zaengle\neverstale\utilities\ScanUtility;
use zaengle\neverstale\variables\NeverstaleVariable;
use zaengle\neverstale\web\assets\neverstale\NeverstaleAsset;
use zaengle\neverstale\web\twig\Neverstale as NeverstaleTwigExtension;

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
 * @property-read Config $config
 * @property-read Content $content
 * @property-read EntryService $entry
 * @property-read Flag $flag
 * @property-read FormatService $format
 * @property-read Settings $settings
 * @property-read Setup $setup
 * @property-read TransactionLog $transactionLog
 * @property-read VitePluginService $vite
 */
class Plugin extends BasePlugin
{
    use HasPluginLogfile;

    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;
    public ApiClient $client;
    public const STATUS_ATTRIBUTE = 'neverstaleStatus';
    public const DATE_ANALYZED_ATTRIBUTE = 'neverstaleDateAnalyzed';
    public const DATE_EXPIRED_ATTRIBUTE = 'neverstaleDateExpired';
    public const FLAG_COUNT_ATTRIBUTE = 'neverstaleFlagCount';

    public function init(): void
    {
        parent::init();

        $this->registerLogTarget();
        $this->exposeTwigVariable();
        $this->registerElementTypes();
        $this->registerOnElementSaveHandler();
        $this->registerUtilities();
        $this->registerUserPermissions();
        $this->registerEntryBehaviors();
        $this->registerCpRoutes();
        $this->registerCpNavItems();
        $this->registerEntryTableAttributes();
        $this->registerEntrySidebarHtml();

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
                'flag' => [
                    'class' => Flag::class,
                    'client' => $this->client,
                ],
                'format' => FormatService::class,
                'setup' => Setup::class,
                'transactionLog' => TransactionLog::class,
                'template' => Template::class,
                'vite' => [
                    'class' => VitePluginService::class,
                    'assetClass' => NeverstaleAsset::class,
                    'checkDevServer' => true,
                    'useDevServer' => true,
                    'devServerPublic' => App::env('PRIMARY_SITE_URL') . ':3333',
                    'serverPublic' => App::env('PRIMARY_SITE_URL'),
                    'errorEntry' => 'src/js/Neverstale.js',
                    'devServerInternal' => App::env('PRIMARY_SITE_URL') . ':3333',
                ],
            ]);

            Craft::$app->view->registerTwigExtension(new NeverstaleTwigExtension());
        });
    }
    public static function t(): string
    {
        return Craft::t('neverstale', ...func_get_args());
    }
    protected function registerCpNavItems(): void
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
                        'dashboard' => [
                            'label' => self::t('Dashboard'),
                            'url' => 'neverstale',
                        ],
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
     * @return void
     */
    protected function exposeTwigVariable(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $e) {
                /** @var CraftVariable $variable */
                $variable = $e->sender;
                $variable->set('neverstale', [
                    'class' => NeverstaleVariable::class,
                    'config' => $this->config,
                    'format' => $this->format,
                    'settings' => $this->getSettings(),
                    'setup' => $this->setup,
                    'template' => $this->template,
                    'viteService' => $this->vite,
                ]);
            }
        );
    }
    /**
     * @return void
     */
    public function registerEntryBehaviors(): void
    {
        Event::on(Entry::class, Model::EVENT_DEFINE_BEHAVIORS, function(DefineBehaviorsEvent $event) {
            $event->behaviors['previewNeverstaleContent'] = HasNeverstaleContentBehavior::class;
        });
    }
    /**
     * @throws InvalidConfigException
     */
    public function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }
    public function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('neverstale/_settings', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }
    /**
     * Copy example config to project's config folder
     */
    public function afterInstall(): void
    {
        $configSource = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config.example.php';
        $configTarget = Craft::$app->getConfig()->configDir . DIRECTORY_SEPARATOR . 'neverstale.php';

        if (!file_exists($configTarget)) {
            copy($configSource, $configTarget);
        }
    }
    protected function registerEntrySidebarHtml(): void
    {
        Event::on(
            Entry::class,
            Element::EVENT_DEFINE_SIDEBAR_HTML,
            static function(DefineHtmlEvent $event) {
                $event->html .= Craft::$app->view->renderTemplate('neverstale/entry/_sidebar', [
                    'content' => $event->sender->getNeverstaleContent(),
                ]);
            });
    }
    protected function registerEntryTableAttributes(): void
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
    public function getStatusAttributeHtml(Entry $entry): string
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

    public function getDateAttributeHtml(string $attribute, Entry $entry): string
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
    protected function registerOnElementSaveHandler(): void
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
    protected function registerUserPermissions(): void
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
    protected function registerUtilities(): void
    {
        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITIES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = PreviewContent::class;
            $event->types[] = ScanUtility::class;
        });
        ;
    }
    protected function registerElementTypes(): void
    {
        Event::on(Elements::class, Elements::EVENT_REGISTER_ELEMENT_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = NeverstaleContent::class;
        });
    }
    protected function registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, [
                'neverstale' => ['template' => 'neverstale/_dashboard'],
                'neverstale/content' => ['template' => 'neverstale/content/_index'],
                'neverstale/content/<contentId:\\d+>' => 'neverstale/content/show',
            ]);
        });
    }
}
