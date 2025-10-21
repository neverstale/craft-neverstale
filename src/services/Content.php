<?php

namespace neverstale\neverstale\services;

use Craft;
use craft\elements\Entry;
use craft\helpers\App;
use craft\helpers\ElementHelper;
use craft\helpers\Queue;
use DateTime;
use Exception;
use neverstale\api\Client;
use neverstale\api\Client as ApiClient;
use neverstale\api\exceptions\ApiException;
use neverstale\api\models\Content as ContentModel;
use neverstale\api\models\TransactionResult;
use neverstale\neverstale\elements\Content as ContentElement;
use neverstale\neverstale\enums\AnalysisStatus;
use neverstale\neverstale\jobs\IngestContentJob;
use neverstale\neverstale\models\CustomId;
use neverstale\neverstale\models\TransactionLogItem;
use neverstale\neverstale\Plugin;
use yii\base\Component;

/**
 * Content Service
 *
 * Handles API integration, content ingestion, webhook processing,
 * and content lifecycle management for the Neverstale plugin.
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   2.0.0
 * @see     https://github.com/neverstale/craft-neverstale
 */
class Content extends Component
{
    protected const HEALTH_CACHE_KEY = 'neverstale:health';
    public ApiClient $client;
    public string $hashAlgorithm = 'sha256';
    public int $cacheHealthDuration = 60;

    /**
     * Initialize the service with API client
     */
    public function init(): void
    {
        parent::init();

        // Initialize API client with settings
        $settings = Plugin::getInstance()->getSettings();
        $apiKey = App::parseEnv($settings->apiKey);
        $envBaseUri = App::parseEnv('$NEVERSTALE_API_BASE_URI');
        $baseUri = $envBaseUri ?: 'https://api.neverstale.com';

        Plugin::debug("Environment NEVERSTALE_API_BASE_URI: " . ($envBaseUri ? $envBaseUri : 'NOT SET'));
        Plugin::debug("Content service initializing API client with baseUri: {$baseUri}");
        Plugin::debug("API key configured: " . (!empty($apiKey) ? 'YES' : 'NO'));

        if (empty($apiKey)) {
            Plugin::error("Neverstale API key is not configured. Please set the apiKey setting in the plugin configuration.");
        }

        try {
            Plugin::debug("Initializing API client with apiKey: " . (empty($apiKey) ? 'EMPTY' : substr($apiKey, 0, 10) . '...'));
            $this->client = new ApiClient([
                'apiKey' => $apiKey,
                'baseUri' => $baseUri,
            ]);
            Plugin::debug("API client initialized successfully");
        } catch (Exception $e) {
            Plugin::error("Failed to initialize Neverstale API client: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Ingest a single content item to the Neverstale API
     *
     * @param  ContentElement  $content  Content element to ingest
     * @return bool Success status
     */
    public function ingest(ContentElement $content): bool
    {
        Plugin::debug("Starting ingest for content #{$content->id}");

        try {
            // Validate API client is initialized
            if (!isset($this->client)) {
                Plugin::error("API client is not initialized for content #{$content->id}");
                $transaction = new TransactionLogItem([
                    'transactionStatus' => ApiClient::STATUS_ERROR,
                    'message' => 'API client not initialized',
                    'event' => 'api.client_error',
                ]);

                return $this->onIngestError($content, $transaction);
            }

            // Prepare API data
            $apiData = $content->forApi();
            Plugin::debug("API data prepared for content #{$content->id}: " . json_encode(array_keys($apiData)));

            $webhookConfig = [
                'webhook' => [
                    'endpoint' => $content->webhookUrl,
                ],
            ];

            Plugin::debug("Making API request for content #{$content->id}");
            $result = $this->client->ingest($apiData, $webhookConfig);

            $transaction = TransactionLogItem::fromContentResponse($result, 'api.ingest');

            Plugin::debug("Ingest for content #{$content->id}: status {$transaction->transactionStatus}");

            // update the content element based on the response
            switch ($transaction->transactionStatus) {
                case ApiClient::STATUS_SUCCESS:
                    Plugin::info("Successfully ingested content #{$content->id} to Neverstale API");

                    return $this->onIngestSuccess($content, $transaction);
                case ApiClient::STATUS_ERROR:
                    Plugin::error("API returned error for content #{$content->id}: {$transaction->message}");

                    return $this->onIngestError($content, $transaction);
                default:
                    Plugin::error("Unknown transaction status for content #{$content->id}: {$transaction->transactionStatus}");

                    return false;
            }
        } catch (ApiException $e) {
            Plugin::error("API exception during ingest for content #{$content->id}: {$e->getMessage()}");
            Plugin::debug("API exception details: " . $e->getTraceAsString());
            $transaction = TransactionLogItem::fromException($e, 'api.error');

            return $this->onIngestError($content, $transaction);
        } catch (Exception $e) {
            Plugin::error("General exception during ingest for content #{$content->id}: {$e->getMessage()}");
            Plugin::debug("Exception details: " . $e->getTraceAsString());

            // Create a transaction log for the general exception
            $transaction = new TransactionLogItem([
                'transactionStatus' => ApiClient::STATUS_ERROR,
                'message' => $e->getMessage(),
                'event' => 'api.exception',
            ]);

            return $this->onIngestError($content, $transaction);
        }
    }

    /**
     * Handle failed ingest response
     *
     * @param  ContentElement      $content      Content element
     * @param  TransactionLogItem  $transaction  Transaction log item
     * @return bool Success status
     */
    public function onIngestError(ContentElement $content, TransactionLogItem $transaction): bool
    {
        // Set analysis status if available, otherwise default to API_ERROR
        $analysisStatus = $transaction->getAnalysisStatus();
        if ($analysisStatus !== null) {
            $content->setAnalysisStatus($analysisStatus);
        } else {
            $content->setAnalysisStatus(AnalysisStatus::API_ERROR);
        }

        $content->logTransaction($transaction);

        return Plugin::getInstance()->content->save($content);
    }

    /**
     * Save content element
     *
     * @param  ContentElement  $content  Content element
     * @return bool Success status
     */
    public function save(ContentElement $content): bool
    {
        Plugin::debug("Content::save() attempting to save content: ID=" . ($content->id ?? 'null'));
        Plugin::debug("Content state: entryId={$content->entryId}, siteId={$content->siteId}");

        $saved = Craft::$app->getElements()->saveElement($content);

        Plugin::debug("Content::save() - Elements::saveElement() returned: " . ($saved ? 'true' : 'false'));

        if (!$saved) {
            $errors = $content->getErrors();
            Plugin::error("Failed to save content #{$content->id}. Errors: " . print_r($errors, true));

            // Log additional debugging info
            Plugin::debug("Content validation errors: " . json_encode($errors));
            Plugin::debug("Content attributes: " . json_encode($content->getAttributes()));
        } else {
            Plugin::debug("Content saved successfully with ID: {$content->id}");
        }

        return $saved;
    }

    /**
     * Handle successful ingest response
     *
     * @param  ContentElement      $content      Content element
     * @param  TransactionLogItem  $transaction  Transaction log item
     * @return bool Success status
     */
    public function onIngestSuccess(ContentElement $content, TransactionLogItem $transaction): bool
    {
        $content->neverstaleId = $transaction->neverstaleId;
        $content->setAnalysisStatus($transaction->getAnalysisStatus());
        $content->logTransaction($transaction);

        return Plugin::getInstance()->content->save($content);
    }

    /**
     * Batch ingest multiple content items using the batch API endpoint
     *
     * @param  ContentElement[]  $contents  Array of content items (max 100)
     * @return array ['successCount' => int, 'errorCount' => int, 'errors' => array]
     */
    public function batchIngest(array $contents): array
    {
        Plugin::info("Content::batchIngest() called with " . count($contents) . " content items");

        $batchData = [];
        $contentMap = [];
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        // Prepare batch data and create mapping
        foreach ($contents as $content) {
            $apiData = $content->forApi();
            $apiData['webhook'] = [
                'endpoint' => $content->webhookUrl,
            ];

            $batchData[] = $apiData;
            $contentMap[$content->getCustomId()] = $content;
        }

        try {
            $result = $this->client->batchIngest($batchData);
            Plugin::info("API response success: " . ($result->getWasSuccess() ? 'true' : 'false'));
            Plugin::info("API response data: " . json_encode($result->data));

            if ($result->getWasSuccess() && isset($result->data)) {
                // Process successful batch response
                // Handle both nested structure (data.data) and direct array structure
                $items = isset($result->data['data']) ? $result->data['data'] : $result->data;

                if (is_array($items)) {
                    Plugin::info("Processing " . count($items) . " items from API response");
                    foreach ($items as $item) {
                        $customId = $item->custom_id ?? null;

                        if ($customId && isset($contentMap[$customId])) {
                            $content = $contentMap[$customId];

                            // Create transaction log item for successful batch ingest
                            $transaction = new TransactionLogItem([
                                'transactionStatus' => ApiClient::STATUS_SUCCESS,
                                'content' => $item,
                                'message' => 'Batch ingest successful',
                            ]);

                            if ($this->onIngestSuccess($content, $transaction)) {
                                $successCount++;
                            } else {
                                $errorCount++;
                                $errors[] = Plugin::t("Failed to update content #{id} after successful API call", ['id' => $content->id]);
                            }
                        } else {
                            $errorCount++;
                            $errors[] = Plugin::t("Content not found for custom_id: {customId}", ['customId' => $customId]);
                        }
                    }
                }
            } else {
                // Handle API error response
                $errorMessage = $result->message ?? 'Unknown batch ingest error';
                Plugin::error("Batch ingest failed: {$errorMessage}");

                foreach ($contents as $content) {
                    $transaction = new TransactionLogItem([
                        'transactionStatus' => ApiClient::STATUS_ERROR,
                        'message' => $errorMessage,
                    ]);

                    $this->onIngestError($content, $transaction);
                    $errorCount++;
                    $errors[] = Plugin::t("Failed to ingest content #{id}: {message}", [
                        'id' => $content->id,
                        'message' => $errorMessage,
                    ]);
                }
            }

            Plugin::info("Batch ingest completed: {$successCount} successful, {$errorCount} failed");

            return [
                'successCount' => $successCount,
                'errorCount' => $errorCount,
                'errors' => $errors,
            ];
        } catch (ApiException $e) {
            Plugin::error("Batch ingest API error: {$e->getMessage()}");

            // Mark all content items as failed
            foreach ($contents as $content) {
                $transaction = TransactionLogItem::fromException($e, 'api.batchIngest.error');
                $this->onIngestError($content, $transaction);
            }

            return [
                'successCount' => 0,
                'errorCount' => count($contents),
                'errors' => [Plugin::t("Batch ingest failed: {message}", ['message' => $e->getMessage()])],
            ];
        } catch (Exception $e) {
            Plugin::error("Batch ingest unexpected error: {$e->getMessage()}");

            // Mark all content items as failed
            foreach ($contents as $content) {
                $transaction = new TransactionLogItem([
                    'transactionStatus' => ApiClient::STATUS_ERROR,
                    'message' => $e->getMessage(),
                ]);
                $this->onIngestError($content, $transaction);
            }

            return [
                'successCount' => 0,
                'errorCount' => count($contents),
                'errors' => [Plugin::t("Batch ingest failed: {message}", ['message' => $e->getMessage()])],
            ];
        }
    }

    /**
     * Refresh content data from the API
     *
     * @param  ContentElement  $content  Content to refresh
     * @return bool Success status
     */
    public function refresh(ContentElement $content): bool
    {
        Plugin::debug("Refreshing content #{$content->id} from Neverstale");

        try {
            if ($data = $this->retrieveByCustomId($content->customId)) {
                $transaction = TransactionLogItem::fromContentResponse(new TransactionResult([
                    'status' => Client::STATUS_SUCCESS,
                    'message' => Plugin::t('Content refreshed from Neverstale'),
                    'data' => $data,
                ]), 'api.refreshContent');

                $content->setAnalysisStatus($transaction->analysisStatus);
                $content->logTransaction($transaction);

                return Plugin::getInstance()->content->save($content);
            }
            Plugin::error("Failed to refresh content #{$content->id}");

            return false;
        } catch (ApiException $e) {
            Plugin::error("Failed to refresh content #{$content->id}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Retrieve content by custom ID from the API
     *
     * @param  string  $customId  Content custom ID
     * @return ContentModel|null
     * @throws Exception
     */
    public function retrieveByCustomId(string $customId): ?ContentModel
    {
        try {
            return $this->client->retrieve($customId);
        } catch (ApiException $e) {
            Plugin::error("Failed to fetch content by custom ID {$customId}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Validate webhook signature
     *
     * @param  string  $payload        Raw webhook payload
     * @param  string  $userSignature  Signature from webhook headers
     * @return bool Valid signature
     */
    public function validateSignature(string $payload, string $userSignature): bool
    {
        $expectedSignature = $this->sign($payload);
        $isValid = hash_equals($expectedSignature, $userSignature);

        Plugin::webhookDebug("Signature validation - Expected: $expectedSignature");
        Plugin::webhookDebug("Signature validation - Received: $userSignature");
        Plugin::webhookDebug("Signature validation - Result: " . ($isValid ? 'VALID' : 'INVALID'));

        return $isValid;
    }

    /**
     * Sign webhook payload with secret
     *
     * @param  string  $payload  Payload to sign
     * @return string HMAC signature
     */
    public function sign(string $payload): string
    {
        $secret = App::parseEnv(Plugin::getInstance()->settings->webhookSecret);

        return hash_hmac($this->hashAlgorithm, $payload, $secret);
    }

    /**
     * Handle webhook response
     *
     * @param  ContentElement      $content      Content element
     * @param  TransactionLogItem  $transaction  Transaction log item
     * @return bool Success status
     */
    public function onWebhook(ContentElement $content, TransactionLogItem $transaction): bool
    {
        // Check webhook version to prevent processing out-of-order webhooks
        $incomingVersion = $transaction->getWebhookVersion();

        // Backward compatibility: if no version metadata, process the webhook
        if ($incomingVersion === null) {
            Plugin::webhookInfo("Processing webhook without version metadata (backward compatibility) for content #{$content->id}");
        } else {
            // Validate incoming version - reject obviously invalid timestamps
            // Note: incomingVersion is in microseconds, need to convert to seconds for validation
            // Allow up to 1 hour in the future to account for minor clock skew
            $now = time();
            $maxFutureSkew = 3600; // 1 hour
            $incomingVersionSeconds = (int)($incomingVersion / 1000000);

            if ($incomingVersionSeconds > $now + $maxFutureSkew) {
                Plugin::webhookWarning(
                    "Rejecting webhook with future timestamp for content #{$content->id}. " .
                    "Incoming version: {$incomingVersion} ({$incomingVersionSeconds}s), Current time: {$now}, Difference: " . ($incomingVersionSeconds - $now) . " seconds"
                );
                return true; // Return success - we intentionally ignored it
            }

            // If stored version is in the future, reset it
            $storedVersionSeconds = (int)($content->lastWebhookVersion / 1000000);
            if ($storedVersionSeconds > $now + $maxFutureSkew) {
                Plugin::webhookWarning(
                    "Stored webhook version is in the future for content #{$content->id}. " .
                    "Resetting from {$content->lastWebhookVersion} ({$storedVersionSeconds}s) to 0"
                );
                $content->lastWebhookVersion = 0;
            }

            // Check if this webhook is stale
            if ($incomingVersion <= $content->lastWebhookVersion) {
                Plugin::webhookWarning(
                    "Ignoring stale webhook for content #{$content->id}. " .
                    "Incoming version: {$incomingVersion}, Stored version: {$content->lastWebhookVersion}"
                );
                return true; // Return success - we intentionally ignored it
            }

            Plugin::webhookInfo(
                "Processing webhook for content #{$content->id}. " .
                "Version: {$incomingVersion} (previous: {$content->lastWebhookVersion})"
            );

            // Update webhook version
            $content->lastWebhookVersion = $incomingVersion;
        }

        $content->setAnalysisStatus($transaction->getAnalysisStatus());
        $content->flagCount = $transaction->getFlagCount();

        if ($transaction->getDateAnalyzed()) {
            $content->dateAnalyzed = $transaction->getDateAnalyzed();
        }
        if ($transaction->getDateExpired()) {
            $content->dateExpired = $transaction->getDateExpired();
        }
        $content->logTransaction($transaction);

        // Sync flags - always call even if empty to remove obsolete flags
        $flags = $transaction->getFlags();
        Plugin::webhookDebug("Flags received: " . json_encode($flags));
        Plugin::webhookDebug("Flag count: " . count($flags));

        Plugin::webhookInfo("Syncing " . count($flags) . " flags for content #{$content->id}");
        $syncResult = Plugin::getInstance()->flagManager->syncFlagsForContent($content, $flags);
        Plugin::webhookInfo("Flag sync result: " . ($syncResult ? 'SUCCESS' : 'FAILED'));

        // Log final flag count for verification
        $finalFlagCount = Plugin::getInstance()->flagManager->getFlagCountForContent($content);
        Plugin::webhookInfo("Final flag count for content #{$content->id}: {$finalFlagCount}");

        // Warn if flag count doesn't match expected
        if ($finalFlagCount != count($flags)) {
            Plugin::webhookWarning("Flag count mismatch! Expected: " . count($flags) . ", Actual: {$finalFlagCount}. Check main neverstale log for details.");
        }

        return Plugin::getInstance()->content->save($content);
    }

    /**
     * Handle entry save event - main entry point for entry processing
     *
     * @param  Entry  $entry
     * @return void
     */
    public function handleEntrySave(Entry $entry): void
    {
        Plugin::debug("Handling entry save for ID: {$entry->id}, Title: {$entry->title}");
        Plugin::debug("Entry isCanonical: " . ($entry->getIsCanonical() ? 'true' : 'false') . ", isDraft: " . (ElementHelper::isDraft($entry) ? 'true' : 'false') . ", isRevision: " . (ElementHelper::isRevision($entry) ? 'true' : 'false'));

        try {
            // Only process canonical entries - skip drafts and revisions entirely
            if (ElementHelper::isDraftOrRevision($entry)) {
                Plugin::debug("Entry #{$entry->id} is a draft or revision - skipping analysis (only canonical entries trigger analysis)");
                return;
            }

            // At this point we know the entry being saved is canonical
            if (!$entry->getIsCanonical()) {
                Plugin::debug("Entry #{$entry->id} is not canonical - skipping");
                return;
            }

            Plugin::debug("Entry #{$entry->id} is canonical and will be processed");

            // Check if entry should be ingested (includes enabled/section checks)
            if (!Plugin::getInstance()->entry->shouldIngest($entry)) {
                Plugin::debug("Entry {$entry->id} did not pass shouldIngest check");

                return;
            }

            Plugin::debug("Entry {$entry->id} passed validation, finding or creating content");

            $content = $this->findOrCreateContentFor($entry);

            // Reset content to unsent status to trigger re-analysis
            // This ensures that editing a published entry re-analyzes it
            if (!$content->isUnsent()) {
                Plugin::debug("Content {$content->id} was previously analyzed (status: {$content->getAnalysisStatus()->value}), resetting to unsent for re-analysis");
                $content->setAnalysisStatus(AnalysisStatus::UNSENT);
                $this->save($content);
            }

            $this->queue($content);
        } catch (Exception $e) {
            Plugin::error("Failed to handle entry save for {$entry->id}: " . $e->getMessage());
        }
    }

    /**
     * Find or create content for entry
     *
     * @param  Entry  $entry  Entry element
     * @return ContentElement
     */
    public function findOrCreateContentFor(Entry $entry): ContentElement
    {
        Plugin::debug("Content::findOrCreateContentFor() called for Entry #{$entry->id}");
        Plugin::debug("Entry canonical ID: {$entry->canonicalId}, Site ID: {$entry->siteId}");

        $content = $this->find($entry);

        if (!$content) {
            Plugin::debug("No existing content found, creating new content");
            $content = $this->create($entry);
            Plugin::debug("Content object created in memory: " . get_class($content));
            Plugin::debug("Content before save: ID=" . ($content->id ?? 'null') . ", Entry ID={$content->entryId}, Site ID={$content->siteId}");

            $saveResult = $this->save($content);
            Plugin::debug("Save result: " . ($saveResult ? 'SUCCESS' : 'FAILED'));

            if ($saveResult) {
                Plugin::debug("Content after save: ID=" . ($content->id ?? 'null'));
                Plugin::info(Plugin::t("Created Content #{contentId} for Entry #{entryId}", [
                    'entryId' => $entry->id,
                    'contentId' => $content->id,
                ]));
            } else {
                Plugin::error("Failed to save new content - validation errors: " . print_r($content->getErrors(), true));
            }
        } else {
            Plugin::debug("Found existing content: ID={$content->id}");
        }

        return $content;
    }

    /**
     * Find existing content for entry
     *
     * @param  Entry  $entry  Entry element
     * @return ContentElement|null
     */
    public function find(Entry $entry): ?ContentElement
    {
        Plugin::debug("Content::find() searching for: entryId={$entry->canonicalId}, siteId={$entry->siteId}");

        $content = ContentElement::findOne([
            'entryId' => $entry->canonicalId,
            'siteId' => $entry->siteId,
        ]);

        Plugin::debug("Content::find() result: " . ($content ? "Found ID={$content->id}" : "Not found"));

        return $content;
    }

    /**
     * Create new content element for entry
     *
     * @param  Entry  $entry  Entry element
     * @return ContentElement
     */
    public function create(Entry $entry): ContentElement
    {
        Plugin::debug("Content::create() creating new Content for Entry #{$entry->id}");
        Plugin::debug("Using entryId={$entry->canonicalId}, siteId={$entry->siteId}");

        $content = new ContentElement([
            'entryId' => $entry->canonicalId,
            'siteId' => $entry->siteId,
        ]);

        Plugin::debug("Content::create() - Content object instantiated: " . get_class($content));
        Plugin::debug("Content flagCount initialized to: " . ($content->flagCount ?? 'null'));

        return $content;
    }

    /**
     * Queue content for processing
     *
     * @param  ContentElement  $content  Content to queue
     * @return void
     */
    public function queue(ContentElement $content): void
    {
        Plugin::debug("Content::queue() called for Content #{$content->id}");

        Plugin::debug("Pushing IngestContentJob to queue with contentId: {$content->id}");

        try {
            $jobId = Queue::push(new IngestContentJob([
                'contentId' => $content->id,
            ]));

            Plugin::debug("Job pushed to queue with ID: " . ($jobId ?? 'unknown'));
        } catch (Exception $e) {
            Plugin::error("Failed to push job to queue: " . $e->getMessage());

            $content->setAnalysisStatus(AnalysisStatus::API_ERROR);
            Craft::$app->getElements()->saveElement($content);
        }

        Plugin::debug("Content::queue() completed");
    }

    /**
     * Find or create content by custom ID string
     *
     * @param  string|null  $strId  Custom ID string
     * @return ContentElement|null
     */
    public function findOrCreateByCustomId(?string $strId): ?ContentElement
    {
        if ($content = $this->getByCustomId($strId)) {
            return $content;
        }

        $customId = CustomId::parse($strId);

        $entry = Entry::findOne([
            'id' => $customId->entryId,
            'siteId' => $customId->siteId,
        ]);

        if (!$entry) {
            Plugin::error("Entry {$customId->entryId} not found for custom ID: {$strId}");

            return null;
        }

        return $this->create($entry);
    }

    /**
     * Get content by custom ID
     *
     * @param  string|CustomId  $customId  Custom ID
     * @return ContentElement|null
     */
    public function getByCustomId(string|CustomId $customId): ?ContentElement
    {
        if (!$customId instanceof CustomId) {
            $customId = CustomId::parse($customId);
        }

        return ContentElement::findOne($customId->id);
    }

    /**
     * Check API connection health
     *
     * @param  bool  $forceFresh  Force fresh check bypassing cache
     * @return bool Connection health status
     */
    public function checkCanConnect($forceFresh = false): bool
    {
        if ($forceFresh || !Craft::$app->cache->exists(self::HEALTH_CACHE_KEY)) {
            Craft::$app->cache->set(
                self::HEALTH_CACHE_KEY,
                $this->client->health(),
                $this->cacheHealthDuration
            );
        }

        return Craft::$app->cache->get(self::HEALTH_CACHE_KEY);
    }

    /**
     * Clear connection status cache
     *
     * @return void
     */
    public function clearConnectionStatusCache(): void
    {
        Craft::$app->cache->delete(self::HEALTH_CACHE_KEY);
    }

    /**
     * Delete content from the API
     *
     * @param  ContentElement  $content  Content to delete
     * @return bool Success status
     */
    public function delete(ContentElement $content): bool
    {
        try {
            $result = $this->client->batchDelete([$content->customId]);

            if ($result->getWasError()) {
                Plugin::error("Failed to delete content #{$content->id}: {$result->message}");

                return false;
            }

            Plugin::info("Successfully deleted content #{$content->id} from Neverstale API");

            return true;
        } catch (ApiException $e) {
            Plugin::error("Failed to delete content #{$content->id}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Get last sync date
     *
     * @return DateTime|null
     */
    public function getLastSync(): ?DateTime
    {
        return ContentElement::find()
            ->select('dateUpdated')
            ->orderBy('dateUpdated DESC')
            ->one()
            ?->dateUpdated;
    }
}
