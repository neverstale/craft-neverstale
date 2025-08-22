<?php

namespace neverstale\neverstale\registrars;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\elements\db\ElementQuery;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use craft\events\DefineAttributeHtmlEvent;
use craft\events\DefineBehaviorsEvent;
use craft\events\DefineFieldLayoutElementsEvent;
use craft\events\DefineHtmlEvent;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterElementTableAttributesEvent;
use craft\helpers\ElementHelper;
use craft\models\FieldLayout;
use craft\services\Dashboard;
use neverstale\neverstale\behaviors\HasNeverstaleContentBehavior;
use neverstale\neverstale\elements\Content;
use neverstale\neverstale\fieldlayoutelements\NeverstaleTab;
use neverstale\neverstale\Plugin;
use neverstale\neverstale\widgets\FlaggedContentWidget;
use yii\base\Event;

/**
 * Handles registration of all event listeners
 */
class EventRegistrar implements RegistrarInterface
{
    public function register(): void
    {
        $this->attachFieldLayoutEvents();
        $this->attachEntryDisplayEvents();
        $this->attachDashboardEvents();
        $this->attachElementTableEvents();
        $this->attachEntryBehaviors();
        $this->attachElementSaveHandler();
        $this->attachElementDeleteHandler();
    }

    /**
     * Attach field layout events for adding Neverstale tabs to entry forms
     */
    private function attachFieldLayoutEvents(): void
    {
        Event::on(
            FieldLayout::class,
            FieldLayout::EVENT_DEFINE_UI_ELEMENTS,
            function (DefineFieldLayoutElementsEvent $event) {
                /** @var FieldLayout $fieldLayout */
                $fieldLayout = $event->sender;

                // Only add to Entry field layouts
                if ($fieldLayout->type !== Entry::class) {
                    return;
                }

                // Get the section from the field layout
                $section = null;

                // TODO - determine what to do with this. Appears unused
                if ($fieldLayout->id) {
                    foreach (Craft::$app->getEntries()->getAllSections() as $testSection) {
                        $entryType = $testSection->getEntryTypes()[0] ?? null;
                        if ($entryType && $entryType->fieldLayoutId === $fieldLayout->id) {
                            $section = $testSection;
                            break;
                        }
                    }
                }

                $event->elements[] = [
                    'class' => NeverstaleTab::class,
                ];
            }
        );
    }

    /**
     * Attach events for displaying Neverstale data in entry forms and meta sections
     */
    private function attachEntryDisplayEvents(): void
    {
        // Add flag indicator to entry meta sidebar
        Event::on(
            Entry::class,
            Entry::EVENT_DEFINE_META_FIELDS_HTML,
            function (DefineHtmlEvent $event) {
                /** @var Entry $entry */
                $entry = $event->sender;

                if (! $entry->id) {
                    return;
                }

                // Use the behavior relationship instead of calling the service
                $content = $entry->neverstaleContent;

                if (! $content) {
                    return;
                }

                $flagCount = $content->getActiveFlagCount();

                if ($flagCount > 0) {
                    $event->html .= Craft::$app->getView()->renderTemplate('neverstale/_includes/meta-sidebar', [
                        'flagCount' => $flagCount,
                        'entry' => $entry,
                    ]);
                }
            }
        );
    }

    /**
     * Attach dashboard-related events for widgets and dashboard functionality
     */
    private function attachDashboardEvents(): void
    {
        // Register dashboard widget
        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = FlaggedContentWidget::class;
            }
        );
    }

    /**
     * Attach events for displaying Neverstale data in element tables and lists
     */
    private function attachElementTableEvents(): void
    {
        // Register Neverstale column in entries table
        Event::on(
            Entry::class,
            Entry::EVENT_REGISTER_TABLE_ATTRIBUTES,
            function (RegisterElementTableAttributesEvent $event) {
                $event->tableAttributes['neverstale'] = [
                    'label' => Craft::t('neverstale', 'Neverstale'),
                ];
            }
        );

        // Set the HTML for the Neverstale column using EVENT_DEFINE_ATTRIBUTE_HTML
        Event::on(
            Element::class,
            Element::EVENT_DEFINE_ATTRIBUTE_HTML,
            function (DefineAttributeHtmlEvent $event) {
                if ($event->attribute === 'neverstale') {
                    /** @var Entry $entry */
                    $entry = $event->sender;

                    if (! $entry->id || ! $entry instanceof Entry) {
                        $event->html = '<span class="light">—</span>';

                        return;
                    }

                    // Check if we have the joined data from the query (avoids N+1)
                    if (isset($entry->getAttributes()['analysisStatus']) && isset($entry->getAttributes()['flagCount'])) {
                        $statusValue = $entry->getAttribute('analysisStatus');
                        $flagCount = (int) $entry->getAttribute('flagCount');
                    } else {
                        // Fallback to relationship (single queries)
                        $content = $entry->neverstaleContent;
                        if (! $content) {
                            $event->html = '<span class="light">—</span>';

                            return;
                        }
                        $statusValue = $content->getAnalysisStatus()->value;
                        $flagCount = $content->getActiveFlagCount();
                    }

                    $ignored = false; // TODO: Add ignored functionality if needed

                    if ($ignored) {
                        $html = '<span class="status-label gray"><span class="status gray"></span><span class="status-label-text">'.Craft::t('neverstale', 'Ignored').'</span></span>';
                    } else {
                        // Build status HTML with semantic colors using real analysis status
                        $statusClass = match ($statusValue) {
                            'analyzed-clean' => 'green',   // Successfully analyzed, no issues
                            'analyzed-flagged' => 'red',   // Analyzed with flags
                            'unsent', 'pending-initial-analysis', 'pending-reanalysis', 'processing-initial-analysis', 'processing-reanalysis' => 'blue', // Pending/processing
                            'api-error', 'stale', 'analyzed-error' => 'orange', // Errors/needs attention
                            'archived' => 'gray', // Archived content
                            default => 'gray'
                        };

                        $statusLabel = match ($statusValue) {
                            'analyzed-clean' => Craft::t('neverstale', 'Clean'),
                            'analyzed-flagged' => Craft::t('neverstale', 'Flagged'),
                            'unsent' => Craft::t('neverstale', 'Not Sent'),
                            'pending-initial-analysis', 'pending-reanalysis' => Craft::t('neverstale', 'Pending'),
                            'processing-initial-analysis', 'processing-reanalysis' => Craft::t('neverstale', 'Processing'),
                            'api-error' => Craft::t('neverstale', 'API Error'),
                            'analyzed-error' => Craft::t('neverstale', 'Analysis Error'),
                            'stale' => Craft::t('neverstale', 'Stale'),
                            'archived' => Craft::t('neverstale', 'Archived'),
                            'unknown' => Craft::t('neverstale', 'Unknown'),
                            default => Craft::t('neverstale', 'Unknown')." ({$statusValue})" // Show the actual value for debugging
                        };

                        $statusLabelText = $statusLabel;

                        if ($flagCount > 0) {
                            $statusLabelText .= ' – '.$flagCount;
                        }

                        $html = '<span class="status-label '.$statusClass.'" style="white-space: nowrap;"><span class="status '.$statusClass.'"></span><span class="status-label-text">'.$statusLabelText.'</span></span>';
                    }

                    $event->html = $html;
                }
            }
        );

        // Add eager loading for Neverstale column
        Event::on(
            EntryQuery::class,
            ElementQuery::EVENT_BEFORE_PREPARE,
            function (Event $event) {
                /** @var EntryQuery $query */
                $query = $event->sender;

                // Check if we're in the entries table context by looking for the neverstale attribute
                $request = Craft::$app->getRequest();

                if ($request->isConsoleRequest || ! $request->getIsGet()) {
                    return;
                }

                // Check if we're displaying the neverstale column in entries table
                $tableAttributes = $request->getParam('tableAttributes');
                if (is_array($tableAttributes) && in_array('neverstale', $tableAttributes, true)) {
                    // Add a join to prevent N+1 queries when displaying the neverstale column
                    // This is a more direct approach than trying to use with() for behavior relationships
                    $query->leftJoin('{{%neverstale_content}} nc', '[[elements.id]] = [[nc.entryId]] AND [[elements.siteId]] = [[nc.siteId]]');
                    $query->addSelect(['nc.analysisStatus', 'nc.flagCount']);
                }
            }
        );
    }

    /**
     * Attach Content behaviors to the Entry model
     */
    private function attachEntryBehaviors(): void
    {
        Event::on(
            Entry::class,
            Model::EVENT_DEFINE_BEHAVIORS,
            function (DefineBehaviorsEvent $event) {
                $event->behaviors['neverstaleContent'] = HasNeverstaleContentBehavior::class;
            }
        );
    }

    /**
     * Attach element save event handlers
     */
    private function attachElementSaveHandler(): void
    {
        Event::on(
            Entry::class,
            Element::EVENT_AFTER_SAVE,
            function (ModelEvent $event) {
                /** @var Entry $entry */
                $entry = $event->sender;

                Plugin::getInstance()->content->handleEntrySave($entry);
            }
        );
    }

    /**
     * Attach element delete event handlers
     */
    private function attachElementDeleteHandler(): void
    {
        Event::on(
            Entry::class,
            Element::EVENT_AFTER_DELETE,
            function (Event $event) {
                /** @var Entry $entry */
                $entry = $event->sender;

                // Ignore drafts
                if (ElementHelper::isDraftOrRevision($entry)) {
                    return;
                }

                // Ignore changes to non-root, non-canonical entries
                if (ElementHelper::rootElementIfCanonical($entry) !== $entry) {
                    return;
                }

                $content = Content::find()
                    ->entryId($entry->canonicalId)
                    ->siteId($entry->siteId)
                    ->trashed(null)
                    ->one();

                if ($content) {
                    if (! Craft::$app->elements->deleteElement($content)) {
                        Plugin::error("Failed to delete content #{$content->id} from Craft");
                    }
                }
            }
        );
    }
}
