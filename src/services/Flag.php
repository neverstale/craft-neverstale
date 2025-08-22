<?php

namespace neverstale\neverstale\services;

use craft\helpers\App;
use DateTime;
use Exception;
use neverstale\api\Client;
use neverstale\api\exceptions\ApiException;
use neverstale\neverstale\elements\Content;
use neverstale\neverstale\Plugin;
use yii\base\Component;

/**
 * Neverstale Flag Service
 *
 * Handles flag management operations including ignoring flags,
 * rescheduling expiration dates, and retrieving flag details
 * from the Neverstale API.
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   2.0.0
 * @see     https://github.com/neverstale/craft-neverstale
 */
class Flag extends Component
{
    public Client $client;

    /**
     * Initialize the service with API client
     */
    public function init(): void
    {
        parent::init();

        // Initialize API client with settings
        $settings = Plugin::getInstance()->getSettings();
        $this->client = new Client([
            'apiKey' => App::parseEnv($settings->apiKey),
            'baseUri' => App::parseEnv('$NEVERSTALE_API_BASE_URI') ?: 'https://api.neverstale.com',
        ]);
    }

    /**
     * Reschedule a flag's expiration date
     *
     * Updates the expiration date for a specific flag, effectively
     * postponing when the content will be considered stale.
     *
     * @param  Content   $content  Content element with the flag
     * @param  string    $flagId   Unique flag identifier
     * @param  DateTime  $newDate  New expiration date
     * @return bool Success status
     */
    public function reschedule(Content $content, string $flagId, DateTime $newDate): bool
    {
        Plugin::info("Rescheduling flag {$flagId} for content #{$content->id} to {$newDate->format('Y-m-d H:i:s')}");

        try {
            $this->client->rescheduleFlag($flagId, $newDate);

            // Update the local flag element to mark it as temporarily ignored with expiry
            $flag = \neverstale\neverstale\elements\Flag::find()
                ->contentId($content->id)
                ->flagId($flagId)
                ->one();
            
            if ($flag) {
                $flag->ignoredAt = new \DateTime();
                $flag->expiredAt = $newDate;
                \Craft::$app->getElements()->saveElement($flag);
                Plugin::info("Updated local flag {$flagId} with ignoredAt and expiredAt");
            } else {
                Plugin::warning("Could not find local flag {$flagId} to update");
            }

            // Update local content expiration if this is the earliest expiring flag
            if (! $content->dateExpired || $newDate < $content->dateExpired) {
                $content->dateExpired = $newDate;
            }

            $saved = \Craft::$app->getElements()->saveElement($content);

            if (! $saved) {
                Plugin::error("Failed to save content after rescheduling flag: ".print_r($content->getErrors(), true));

                return false;
            }

            Plugin::info("Successfully rescheduled flag {$flagId} for content #{$content->id}");

            return true;

        } catch (ApiException $e) {
            Plugin::error("API error rescheduling flag {$flagId}: {$e->getMessage()}");

            return false;
        } catch (Exception $e) {
            Plugin::error("Error rescheduling flag {$flagId}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Get flag statistics for dashboard display
     *
     * Provides summary statistics about flagged content
     * for monitoring and administrative purposes.
     *
     * @return array Flag statistics
     */
    public function getFlagStatistics(): array
    {
        $totalFlagged = Content::find()
            ->where(['>', 'flagCount', 0])
            ->count();

        $expiredContent = Content::find()
            ->where(['<', 'dateExpired', new DateTime()])
            ->andWhere(['>', 'flagCount', 0])
            ->count();

        $recentlyFlagged = Content::find()
            ->where(['>', 'flagCount', 0])
            ->andWhere(['>=', 'dateUpdated', new DateTime('-7 days')])
            ->count();

        // Get flag count distribution
        $flagCountDistribution = [];
        for ($i = 1; $i <= 10; $i++) {
            $count = Content::find()
                ->where(['flagCount' => $i])
                ->count();
            if ($count > 0) {
                $flagCountDistribution[$i] = $count;
            }
        }

        // Count with more than 10 flags
        $highFlagCount = Content::find()
            ->where(['>', 'flagCount', 10])
            ->count();
        if ($highFlagCount > 0) {
            $flagCountDistribution['10+'] = $highFlagCount;
        }

        return [
            'totalFlagged' => $totalFlagged,
            'expired' => $expiredContent,
            'recentlyFlagged' => $recentlyFlagged,
            'flagCountDistribution' => $flagCountDistribution,
        ];
    }

    /**
     * Bulk ignore flags by criteria
     *
     * Allows for batch flag management operations based on
     * content criteria such as date ranges or flag types.
     *
     * @param  array  $criteria      Selection criteria for content
     * @param  array  $flagCriteria  Criteria for which flags to ignore
     * @return array Operation results
     */
    public function bulkIgnoreFlags(array $criteria = [], array $flagCriteria = []): array
    {
        $query = Content::find()
            ->where(['>', 'flagCount', 0]);

        // Apply content criteria
        if (isset($criteria['sectionIds'])) {
            $query->andWhere(['sectionId' => $criteria['sectionIds']]);
        }

        if (isset($criteria['dateRange'])) {
            $query->andWhere(['>=', 'dateExpired', $criteria['dateRange']['start']])
                ->andWhere(['<=', 'dateExpired', $criteria['dateRange']['end']]);
        }

        $contents = $query->limit(100)->all(); // Limit for safety

        $results = [
            'processed' => 0,
            'successful' => 0,
            'errors' => 0,
            'messages' => [],
        ];

        foreach ($contents as $content) {
            $results['processed']++;

            try {
                if ($this->ignoreAllFlags($content)) {
                    $results['successful']++;
                } else {
                    $results['errors']++;
                    $results['messages'][] = "Failed to ignore flags for content #{$content->id}";
                }
            } catch (Exception $e) {
                $results['errors']++;
                $results['messages'][] = "Error processing content #{$content->id}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    /**
     * Ignore all flags for content
     *
     * Batch operation to ignore all active flags for a content item,
     * effectively marking the content as reviewed and acceptable.
     *
     * @param  Content  $content  Content element
     * @return bool Success status
     */
    public function ignoreAllFlags(Content $content): bool
    {
        $flags = $this->getFlagsFor($content);

        if (! $flags || empty($flags)) {
            Plugin::info("No flags to ignore for content #{$content->id}");

            return true;
        }

        $successCount = 0;
        $errorCount = 0;

        foreach ($flags as $flag) {
            $flagId = $flag['id'] ?? null;

            if (! $flagId) {
                Plugin::warning("Flag missing ID for content #{$content->id}");
                $errorCount++;
                continue;
            }

            try {
                if ($this->ignore($content, $flagId)) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            } catch (Exception $e) {
                Plugin::error("Failed to ignore flag {$flagId}: {$e->getMessage()}");
                $errorCount++;
            }
        }

        Plugin::info("Ignored {$successCount} flags for content #{$content->id}, {$errorCount} errors");

        return $errorCount === 0;
    }

    /**
     * Get detailed flag information for content
     *
     * Retrieves comprehensive flag data from the API for
     * display in admin interfaces or detailed analysis.
     *
     * @param  Content  $content  Content element
     * @return array|null Flag details or null on error
     */
    public function getFlagsFor(Content $content): ?array
    {
        if (! $content->neverstaleId) {
            Plugin::warning("Cannot get flags for content #{$content->id} - no Neverstale ID");

            return null;
        }

        try {
            $apiContent = $this->client->retrieve($content->customId);

            if (! $apiContent) {
                Plugin::warning("Content not found in Neverstale API: {$content->customId}");

                return null;
            }

            return $apiContent->flags ?? [];

        } catch (ApiException $e) {
            Plugin::error("Failed to retrieve flags for content #{$content->id}: {$e->getMessage()}");

            return null;
        } catch (Exception $e) {
            Plugin::error("Error retrieving flags for content #{$content->id}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Ignore a specific flag
     *
     * Marks a flag as ignored in the Neverstale system, removing it
     * from the content's active flag count and expiration date.
     *
     * @param  Content  $content  Content element with the flag
     * @param  string   $flagId   Unique flag identifier
     * @return bool Success status
     * @throws Exception on API errors
     */
    public function ignore(Content $content, string $flagId): bool
    {
        Plugin::info("Ignoring flag {$flagId} for content #{$content->id}");

        try {
            $this->client->ignoreFlag($flagId);

            // Update the local flag element to mark it as ignored
            $flag = \neverstale\neverstale\elements\Flag::find()
                ->contentId($content->id)
                ->flagId($flagId)
                ->one();
            
            if ($flag) {
                $flag->ignoredAt = new \DateTime();
                \Craft::$app->getElements()->saveElement($flag);
                Plugin::info("Updated local flag {$flagId} with ignoredAt timestamp");
            } else {
                Plugin::warning("Could not find local flag {$flagId} to update ignoredAt");
            }

            // Update local content state
            if ($content->flagCount > 0) {
                $content->flagCount -= 1;
            }

            // If no flags remain, clear expiration date
            if ($content->flagCount <= 0) {
                $content->dateExpired = null;
                $content->flagCount = 0;
            }

            $saved = \Craft::$app->getElements()->saveElement($content);

            if (! $saved) {
                Plugin::error("Failed to save content after ignoring flag: ".print_r($content->getErrors(), true));

                return false;
            }

            Plugin::info("Successfully ignored flag {$flagId} for content #{$content->id}");

            return true;

        } catch (ApiException $e) {
            Plugin::error("API error ignoring flag {$flagId}: {$e->getMessage()}");
            throw $e;
        } catch (Exception $e) {
            Plugin::error("Error ignoring flag {$flagId}: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Validate flag operations
     *
     * Checks if flag operations can be performed on content,
     * verifying API connectivity and content state.
     *
     * @param  Content  $content  Content to validate
     * @return array Validation results
     */
    public function validateFlagOperations(Content $content): array
    {
        $errors = [];

        if (! $content->neverstaleId) {
            $errors[] = 'Content has no Neverstale ID - cannot perform flag operations';
        }

        if ($content->flagCount <= 0) {
            $errors[] = 'Content has no active flags';
        }

        if (! Plugin::getInstance()->content->checkCanConnect()) {
            $errors[] = 'Cannot connect to Neverstale API';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
