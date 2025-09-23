<?php

namespace neverstale\neverstale\controllers;

use Craft;
use craft\elements\Entry;
use craft\helpers\Queue;
use craft\web\Controller;
use craft\web\Response;
use neverstale\neverstale\jobs\BulkIngestOrchestrator;
use neverstale\neverstale\Plugin;
use Throwable;

/**
 * Bulk Ingest Controller
 *
 * Handles bulk ingestion operations from the Control Panel.
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   2.1.0
 */
class BulkIngestController extends Controller
{
    /**
     * @var bool Allow anonymous access for webhooks
     */
    protected array|bool|int $allowAnonymous = false;

    /**
     * Display bulk ingestion interface
     */
    public function actionIndex(): Response
    {
        // Get only sections that are enabled for Neverstale
        $settings = Plugin::getInstance()->getSettings();
        $enabledSections = $settings->getEnabledSections();
        $sectionOptions = [];

        foreach ($enabledSections as $section) {
            $sectionOptions[] = [
                'label' => $section->name,
                'value' => $section->id,
            ];
        }

        // Get available sites
        $sites = Craft::$app->getSites()->getAllSites();

        $siteOptions = [['label' => 'All Sites', 'value' => '']];

        foreach ($sites as $site) {
            $siteOptions[] = [
                'label' => $site->name,
                'value' => $site->id,
            ];
        }

        return $this->renderTemplate('neverstale/bulk-ingest/index', [
            'sectionOptions' => $sectionOptions,
            'siteOptions' => $siteOptions,
        ]);
    }

    public function actionStartBulkIngest(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $params = [
            'sections' => $request->getBodyParam('sections', []),
            'siteId' => $request->getBodyParam('siteId'),
            'batchSize' => (int) $request->getBodyParam('batchSize', 100),
        ];

        $result = $this->startBulkIngestOperation($params);

        if ($result['success']) {
            Craft::$app->getSession()->setNotice(
                Plugin::t('Bulk ingestion started successfully. Processing {count} items.', [
                    'count' => $result['itemCount'],
                ])
            );
        } else {
            Craft::$app->getSession()->setError(
                implode(' ', $result['errors'] ?? [Plugin::t('Unknown error occurred')])
            );
        }

        return $this->redirect('neverstale/bulk-ingest');
    }

    /**
     * Start the bulk ingestion operation
     */
    protected function startBulkIngestOperation(array $params): array
    {
        try {
            $operationId = uniqid('bulk_ingest_', true);

            $entryIds = $this->getEntriesToProcess($params);

            if (empty($entryIds)) {
                return [
                    'success' => false,
                    'errors' => [Plugin::t('No entries found matching the specified criteria.')],
                ];
            }

            // Limit check
            $maxItems = Plugin::getInstance()->getSettings()->getBulkIngestMaxItems();

            if (count($entryIds) > $maxItems) {
                return [
                    'success' => false,
                    'errors' => [Plugin::t('Too many entries selected. Maximum allowed: {max}', ['max' => $maxItems])],
                ];
            }

            // Convert entry IDs to content IDs
            $contentIds = [];
            foreach ($entryIds as $entryId) {
                $entry = Entry::findOne($entryId);

                if ($entry) {
                    $content = Plugin::getInstance()->content->find($entry);

                    if (! $content) {
                        $content = Plugin::getInstance()->content->create($entry);

                        Plugin::getInstance()->content->save($content);
                    }

                    if ($content && $content->id) {
                        $contentIds[] = $content->id;
                    }
                }
            }

            if (empty($contentIds)) {
                return [
                    'success' => false,
                    'errors' => [Plugin::t('No valid content items could be created.')],
                ];
            }

            $orchestratorJob = new BulkIngestOrchestrator([
                'operationId' => $operationId,
                'contentIds' => $contentIds,
                'batchSize' => $params['batchSize'] ?? 100,
                'userId' => Craft::$app->getUser()->getId(),
                'metadata' => [
                    'sections' => $params['sections'] ?? [],
                    'siteId' => $params['siteId'] ?? null,
                ],
            ]);

            Queue::push($orchestratorJob);

            Plugin::info("Started bulk ingest operation {$operationId} with ".count($contentIds)." items");

            return [
                'success' => true,
                'operationId' => $operationId,
                'itemCount' => count($contentIds),
                'message' => Plugin::t('Bulk ingestion started with {count} items', ['count' => count($contentIds)]),
            ];

        } catch (Throwable $e) {
            Plugin::error("Failed to start bulk ingest: {$e->getMessage()}");

            return [
                'success' => false,
                'errors' => [Plugin::t('Failed to start bulk ingestion: {error}', ['error' => $e->getMessage()])],
            ];
        }
    }

    protected function getEntriesToProcess(array $params): array
    {
        $query = Entry::find();

        if (! empty($params['sections'])) {
            // Validate sections are enabled for Neverstale
            $settings = Plugin::getInstance()->getSettings();
            $enabledSectionIds = array_map(fn($section) => $section->id, $settings->getEnabledSections());
            $requestedSections = array_intersect($params['sections'], $enabledSectionIds);

            if (empty($requestedSections)) {
                return []; // No valid sections selected
            }

            $query->sectionId($requestedSections);
        }

        if (! empty($params['siteId'])) {
            $query->siteId($params['siteId']);
        }

        $query->status('live');

        return $query->ids();
    }
}
