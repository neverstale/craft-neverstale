<?php

namespace neverstale\neverstale\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Queue;
use neverstale\neverstale\elements\Content;
use neverstale\neverstale\enums\Permission;
use neverstale\neverstale\jobs\BulkIngestOrchestrator;
use neverstale\neverstale\Plugin;
use Throwable;

/**
 * Batch Ingest Element Action
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   1.0.0
 */
class BatchIngest extends ElementAction
{
    public static function displayName(): string
    {
        return Plugin::t('Submit to Neverstale');
    }

    public function getTriggerLabel(): string
    {
        return Plugin::t('Submit to Neverstale');
    }

    public function canUse(ElementQueryInterface $query): bool
    {
        return Craft::$app->getUser()->checkPermission(Permission::Ingest->value);
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        /** @var Content[] $elements */
        $elements = $query->all();

        Plugin::info("BatchIngest action started with ".count($elements)." elements");

        if (empty($elements)) {
            $this->setMessage(Plugin::t('No content selected for submission.'));

            return false;
        }

        // For large selections, use the bulk orchestrator
        if (count($elements) > 100) {
            return $this->performBulkIngest($elements);
        }

        // Check if content service exists
        $plugin = Plugin::getInstance();
        Plugin::info("Plugin instance: ".($plugin ? 'exists' : 'null'));
        Plugin::info("Content service: ".($plugin && $plugin->content ? 'exists' : 'null'));
        Plugin::info("Has batchIngest method: ".($plugin && $plugin->content && method_exists($plugin->content, 'batchIngest') ? 'yes' : 'no'));

        if (! $plugin || ! $plugin->content || ! method_exists($plugin->content, 'batchIngest')) {
            Plugin::info("Falling back to individual processing - plugin or content service not available");

            // Fallback to individual processing if batch service not available
            return $this->performIndividualIngest($elements);
        }

        // Use batch API for more efficient processing
        try {
            Plugin::info("Calling batchIngest with ".count($elements)." elements");
            $result = $plugin->content->batchIngest($elements);
            Plugin::info("batchIngest returned: ".json_encode($result));
            $successCount = $result['successCount'] ?? 0;
            $errorCount = $result['errorCount'] ?? 0;
            $errors = $result['errors'] ?? [];

            if ($errorCount === 0) {
                $this->setMessage(Plugin::t('Successfully submitted {count} content items to Neverstale.', [
                    'count' => $successCount,
                ]));

                return true;
            } elseif ($successCount === 0) {
                $errorMessage = empty($errors)
                    ? Plugin::t('Failed to submit all content items to Neverstale.')
                    : Plugin::t('Failed to submit all content items to Neverstale. Errors: {errors}', [
                        'errors' => implode(', ', array_slice($errors, 0, 3)).(count($errors) > 3 ? '...' : ''),
                    ]);
                $this->setMessage($errorMessage);

                return false;
            } else {
                // Partial success
                $message = Plugin::t('Submitted {successCount} of {total} content items. {errorCount} failed.', [
                    'successCount' => $successCount,
                    'total' => count($elements),
                    'errorCount' => $errorCount,
                ]);

                if (! empty($errors) && count($errors) <= 3) {
                    $message .= ' '.Plugin::t('Errors: {errors}', [
                            'errors' => implode(', ', $errors),
                        ]);
                }

                $this->setMessage($message);

                return true; // Partial success is still considered success
            }

        } catch (Throwable $e) {
            // Log the full error for debugging
            Plugin::error('Batch ingest failed: '.$e->getMessage(), __METHOD__);

            $this->setMessage(Plugin::t('Failed to submit content to Neverstale: {error}', [
                'error' => $e->getMessage(),
            ]));

            return false;
        }
    }

    /**
     * Perform bulk ingest for large selections using orchestrator
     *
     * @param  Content[]  $elements
     * @return bool
     */
    private function performBulkIngest(array $elements): bool
    {
        try {
            // Extract content IDs from elements
            $contentIds = [];
            foreach ($elements as $element) {
                $contentIds[] = $element->id;
            }

            if (empty($contentIds)) {
                $this->setMessage(Plugin::t('No valid content items found for bulk processing.'));

                return false;
            }

            // Generate operation ID
            $operationId = 'bulk_action_'.uniqid().'_'.time();

            // Get current user ID
            $userId = Craft::$app->getUser()->getId();

            // Create and queue orchestrator job
            $job = new BulkIngestOrchestrator([
                'operationId' => $operationId,
                'contentIds' => $contentIds,
                'batchSize' => 100,
                'userId' => $userId,
                'metadata' => [
                    'source' => 'element_action',
                    'initiatedBy' => 'user',
                    'selectionCount' => count($contentIds),
                ],
            ]);

            $jobId = Queue::push($job);

            if ($jobId) {
                $this->setMessage(Plugin::t('Started bulk processing of {count} items. Operation ID: {operationId}', [
                    'count' => count($contentIds),
                    'operationId' => $operationId,
                ]));

                Plugin::info("Started bulk ingest operation {$operationId} via element action for ".count($contentIds)." content items");

                return true;
            } else {
                $this->setMessage(Plugin::t('Failed to start bulk processing operation.'));

                return false;
            }

        } catch (Throwable $e) {
            Plugin::error('Bulk ingest element action failed: '.$e->getMessage(), __METHOD__);

            $this->setMessage(Plugin::t('Failed to start bulk processing: {error}', [
                'error' => $e->getMessage(),
            ]));

            return false;
        }
    }

    /**
     * Fallback method for individual element processing when batch service is not available
     *
     * @param  Content[]  $elements
     * @return bool
     */
    private function performIndividualIngest(array $elements): bool
    {
        Plugin::info("performIndividualIngest called with ".count($elements)." elements");

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        $plugin = Plugin::getInstance();
        if (! $plugin || ! $plugin->content) {
            Plugin::error("Plugin or content service not available for individual ingest");
            $this->setMessage(Plugin::t('Failed to access content service.'));

            return false;
        }

        foreach ($elements as $element) {
            try {
                // Use the ingest method to actually submit to API
                Plugin::info("Calling ingest for content #{$element->id}");
                if ($plugin->content->ingest($element)) {
                    $successCount++;
                } else {
                    $errorCount++;
                    $errors[] = Plugin::t("Failed to ingest content #{id}", ['id' => $element->id]);
                }
            } catch (Throwable $e) {
                $errorCount++;
                $errors[] = Plugin::t("Failed to ingest content #{id}: {error}", [
                    'id' => $element->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($errorCount === 0) {
            $this->setMessage(Plugin::t('Successfully submitted {count} content items to Neverstale.', [
                'count' => $successCount,
            ]));

            return true;
        } elseif ($successCount === 0) {
            $errorMessage = empty($errors)
                ? Plugin::t('Failed to submit all content items to Neverstale.')
                : Plugin::t('Failed to submit all content items to Neverstale. Errors: {errors}', [
                    'errors' => implode(', ', array_slice($errors, 0, 3)).(count($errors) > 3 ? '...' : ''),
                ]);
            $this->setMessage($errorMessage);

            return false;
        } else {
            // Partial success
            $message = Plugin::t('Submitted {successCount} of {total} content items. {errorCount} failed.', [
                'successCount' => $successCount,
                'total' => count($elements),
                'errorCount' => $errorCount,
            ]);

            if (! empty($errors) && count($errors) <= 3) {
                $message .= ' '.Plugin::t('Errors: {errors}', [
                        'errors' => implode(', ', $errors),
                    ]);
            }

            $this->setMessage($message);

            return true; // Partial success is still considered success
        }
    }

    public function getConfirmationMessage(): ?string
    {
        return Plugin::t('Are you sure you want to submit the selected content to Neverstale for analysis?');
    }
}
