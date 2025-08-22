<?php

namespace neverstale\neverstale\widgets;

use Craft;
use craft\base\Widget;
use neverstale\neverstale\elements\Content;

class FlaggedContentWidget extends Widget
{
    public int $limit = 10;
    public string $sections = '';  // Comma-separated section handles
    public string $syncStatus = ''; // 'flagged', 'synced', 'pending', or empty for all
    public bool $showStats = true;

    public static function displayName(): string
    {
        return Craft::t('neverstale', 'Neverstale Content');
    }

    public static function icon(): ?string
    {
        return Craft::getAlias('@neverstale/neverstale/resources/icon.svg');
    }

    public function getTitle(): ?string
    {
        $title = Craft::t('neverstale', 'Neverstale Content');
        if ($this->syncStatus) {
            $statusLabel = match ($this->syncStatus) {
                'flagged' => Craft::t('neverstale', 'Flagged'),
                'synced' => Craft::t('neverstale', 'Synced'),
                'pending' => Craft::t('neverstale', 'Pending'),
                default => ''
            };
            if ($statusLabel) {
                $title .= ' - '.$statusLabel;
            }
        }

        return $title;
    }

    public function getBodyHtml(): ?string
    {
        $allEntries = [];

        // First, get overall statistics (not limited by display limit or sync status filter)
        $statsQuery = Content::find();

        // Get section IDs for filtering (used by both stats and display queries)
        $sectionIds = [];
        if ($this->sections) {
            $sectionHandles = array_map('trim', explode(',', $this->sections));
            foreach ($sectionHandles as $handle) {
                $section = Craft::$app->getEntries()->getSectionByHandle($handle);
                if ($section) {
                    $sectionIds[] = $section->id;
                }
            }
            if (! empty($sectionIds)) {
                // Join with entries table to filter by section
                $statsQuery->innerJoin('{{%entries}} entries', '[[neverstale_content.entryId]] = [[entries.id]]')
                    ->andWhere(['in', 'entries.sectionId', $sectionIds]);
            }
        }

        // Calculate statistics from ALL matching content (not just displayed items)
        $stats = [
            'total' => 0,
            'flagged' => 0,
            'synced' => 0,
            'pending' => 0,
            'ignored' => 0,
        ];

        $allContentForStats = $statsQuery->all();
        foreach ($allContentForStats as $content) {
            $stats['total']++;

            $flagCount = $content->getActiveFlagCount();
            $analysisStatus = $content->getAnalysisStatus();

            if ($flagCount > 0) {
                $stats['flagged']++;
            }

            switch ($analysisStatus->value) {
                case 'analyzed-clean':
                case 'analyzed-flagged':
                    $stats['synced']++;
                    break;
                case 'unsent':
                case 'pending-initial-analysis':
                case 'pending-reanalysis':
                case 'processing-initial-analysis':
                case 'processing-reanalysis':
                    $stats['pending']++;
                    break;
            }

            // Ignored count would go here when implemented
        }

        // Now query for display items with filters applied
        $displayQuery = Content::find()
            ->with(['entry'])
            ->limit(100);

        // Apply section filtering if specified
        if (! empty($sectionIds)) {
            $displayQuery->innerJoin('{{%entries}} entries', '[[neverstale_content.entryId]] = [[entries.id]]')
                ->andWhere(['in', 'entries.sectionId', $sectionIds]);
        }

        $contentElements = $displayQuery->all();

        foreach ($contentElements as $contentElement) {
            // Get the associated entry
            $entry = $contentElement->getEntry();

            if (! $entry || ! $entry->getEnabledForSite()) {
                continue;
            }

            // Get real data from the content element
            $flagCount = $contentElement->getActiveFlagCount();
            $analysisStatus = $contentElement->getAnalysisStatus();

            // Map analysis status to sync status for widget display
            $syncStatus = match ($analysisStatus->value) {
                'analyzed-clean' => 'synced',
                'analyzed-flagged' => 'flagged',
                'unsent', 'pending-initial-analysis', 'pending-reanalysis' => 'pending',
                default => 'pending'
            };

            // Check if any flags are ignored (not implemented yet)
            $ignored = false;

            // Filter by sync status if specified
            if ($this->syncStatus && $syncStatus !== $this->syncStatus) {
                continue;
            }

            // For flagged filter, only show entries with flags
            if ($this->syncStatus === 'flagged' && $flagCount === 0) {
                continue;
            }

            $allEntries[] = [
                'entry' => $entry,
                'flagCount' => $flagCount,
                'syncStatus' => $syncStatus,
                'ignored' => $ignored,
                'assignees' => [], // Assignees not implemented yet
                'lastAnalyzed' => $contentElement->dateAnalyzed,
            ];

            if (count($allEntries) >= $this->limit) {
                break;
            }
        }

        // Sort by priority: flagged entries first, then by date
        usort($allEntries, function ($a, $b) {
            if ($a['flagCount'] > 0 && $b['flagCount'] === 0) {
                return -1;
            }
            if ($a['flagCount'] === 0 && $b['flagCount'] > 0) {
                return 1;
            }
            if ($a['flagCount'] !== $b['flagCount']) {
                return $b['flagCount'] - $a['flagCount'];
            }

            return $b['entry']->dateUpdated <=> $a['entry']->dateUpdated;
        });

        return Craft::$app->getView()->renderTemplate('neverstale/_widgets/flagged-content', [
            'entries' => $allEntries,
            'stats' => $stats,
            'showStats' => $this->showStats,
            'widget' => $this,
        ]);
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('neverstale/_widgets/flagged-content-settings', [
            'widget' => $this,
        ]);
    }

    protected function defineSettings(): array
    {
        return array_merge(parent::defineSettings(), [
            'limit' => [
                'type' => 'integer',
                'default' => 10,
                'min' => 1,
                'max' => 50,
            ],
            'sections' => [
                'type' => 'string',
                'default' => '',
            ],
            'syncStatus' => [
                'type' => 'string',
                'default' => '',
            ],
            'showStats' => [
                'type' => 'boolean',
                'default' => true,
            ],
        ]);
    }
}
