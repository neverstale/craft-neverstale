<?php

namespace neverstale\neverstale\services;

use Craft;
use DateTime;
use Exception;
use neverstale\neverstale\elements\Content;
use neverstale\neverstale\elements\Flag;
use neverstale\neverstale\Plugin;
use yii\base\Component;

/**
 * Flag Manager Service
 *
 * Manages Flag elements - creation, updates, and synchronization with API data.
 * Handles the relationship between Content and Flag elements.
 */
class FlagManager extends Component
{
    /**
     * Sync flags for content from API data
     *
     * @param  Content  $content
     * @param  array    $apiFlags  Array of flag data from API
     * @return bool Success status
     */
    public function syncFlagsForContent(Content $content, array $apiFlags): bool
    {
        Plugin::debug("FlagManager: Syncing ".count($apiFlags)." flags for content #{$content->id}");

        try {
            // Get existing flags for this content
            $existingFlags = Flag::find()
                ->contentId($content->id)
                ->all();

            // Index by flagId manually
            $indexedFlags = [];
            foreach ($existingFlags as $flag) {
                $indexedFlags[$flag->flagId] = $flag;
            }
            $existingFlags = $indexedFlags;

            Plugin::debug("FlagManager: Found ".count($existingFlags)." existing flags for content #{$content->id}");

            $processedFlagIds = [];
            $successCount = 0;

            // Process each API flag
            foreach ($apiFlags as $apiFlag) {
                // Handle both array and object formats
                if (is_array($apiFlag)) {
                    $flagId = $apiFlag['id'] ?? null;
                } else {
                    $flagId = $apiFlag->id ?? null;
                }

                if (! $flagId) {
                    Plugin::warning("FlagManager: API flag missing ID, skipping: ".json_encode($apiFlag));
                    continue;
                }

                $processedFlagIds[] = $flagId;

                // Check if flag already exists
                $flag = $existingFlags[$flagId] ?? null;

                if ($flag) {
                    // Update existing flag
                    if ($this->updateFlagFromApiData($flag, $apiFlag)) {
                        $successCount++;
                    }
                } else {
                    // Create new flag
                    if ($this->createFlagFromApiData($content, $apiFlag)) {
                        $successCount++;
                    }
                }
            }

            // Remove flags that are no longer in the API response
            $flagsToRemove = array_diff(array_keys($existingFlags), $processedFlagIds);
            foreach ($flagsToRemove as $flagIdToRemove) {
                $flagToRemove = $existingFlags[$flagIdToRemove];
                if (Craft::$app->getElements()->deleteElement($flagToRemove)) {
                    Plugin::debug("FlagManager: Removed obsolete flag {$flagIdToRemove}");
                }
            }

            Plugin::info("FlagManager: Successfully synced {$successCount} flags for content #{$content->id}");

            return true;

        } catch (Exception $e) {
            Plugin::error("FlagManager: Error syncing flags for content #{$content->id}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Update an existing Flag element from API data
     *
     * @param  Flag                         $flag
     * @param  \neverstale\api\models\Flag  $apiFlag
     * @return bool Success status
     */
    public function updateFlagFromApiData(Flag $flag, $apiFlag): bool
    {
        try {
            $hasChanges = false;

            // Check for changes and update if necessary
            if ($flag->flag !== $apiFlag->flag) {
                $flag->flag = $apiFlag->flag;
                $hasChanges = true;
            }

            if ($flag->reason !== ($apiFlag->reason ?? null)) {
                $flag->reason = $apiFlag->reason ?? null;
                $hasChanges = true;
            }

            if ($flag->snippet !== ($apiFlag->snippet ?? null)) {
                $flag->snippet = $apiFlag->snippet ?? null;
                $hasChanges = true;
            }

            $apiLastAnalyzed = $apiFlag->last_analyzed_at ? $apiFlag->last_analyzed_at->format('Y-m-d H:i:s') : null;
            $currentLastAnalyzed = $flag->lastAnalyzedAt ? $flag->lastAnalyzedAt->format('Y-m-d H:i:s') : null;
            if ($currentLastAnalyzed !== $apiLastAnalyzed) {
                $flag->lastAnalyzedAt = $apiFlag->last_analyzed_at ?? null;
                $hasChanges = true;
            }

            $apiExpiredAt = $apiFlag->expired_at ? $apiFlag->expired_at->format('Y-m-d H:i:s') : null;
            $currentExpiredAt = $flag->expiredAt ? $flag->expiredAt->format('Y-m-d H:i:s') : null;
            if ($currentExpiredAt !== $apiExpiredAt) {
                $flag->expiredAt = $apiFlag->expired_at ?? null;
                $hasChanges = true;
            }

            $apiIgnoredAt = $apiFlag->ignored_at ? $apiFlag->ignored_at->format('Y-m-d H:i:s') : null;
            $currentIgnoredAt = $flag->ignoredAt ? $flag->ignoredAt->format('Y-m-d H:i:s') : null;
            if ($currentIgnoredAt !== $apiIgnoredAt) {
                $flag->ignoredAt = $apiFlag->ignored_at ?? null;
                $hasChanges = true;
            }

            if ($hasChanges) {
                if (Craft::$app->getElements()->saveElement($flag)) {
                    Plugin::debug("FlagManager: Updated flag {$apiFlag->id}");

                    return true;
                } else {
                    Plugin::error("FlagManager: Failed to save updated flag {$apiFlag->id}: ".json_encode($flag->getErrors()));

                    return false;
                }
            } else {
                Plugin::debug("FlagManager: No changes for flag {$apiFlag->id}");

                return true;
            }

        } catch (Exception $e) {
            Plugin::error("FlagManager: Error updating flag {$apiFlag->id}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Create a new Flag element from API data
     *
     * @param  Content                      $content
     * @param  \neverstale\api\models\Flag  $apiFlag
     * @return bool Success status
     */
    public function createFlagFromApiData(Content $content, $apiFlag): bool
    {
        try {
            Plugin::debug("FlagManager: Creating flag from API data: ".json_encode($apiFlag));

            // Handle both array and object formats
            if (is_array($apiFlag)) {
                $flagId = $apiFlag['id'] ?? null;
                $flagType = $apiFlag['flag'] ?? 'unknown';
                $reason = $apiFlag['reason'] ?? null;
                $snippet = $apiFlag['snippet'] ?? null;
                $lastAnalyzedAt = isset($apiFlag['last_analyzed_at']) ? new DateTime($apiFlag['last_analyzed_at']) : null;
                $expiredAt = isset($apiFlag['expired_at']) ? new DateTime($apiFlag['expired_at']) : null;
                $ignoredAt = isset($apiFlag['ignored_at']) ? new DateTime($apiFlag['ignored_at']) : null;
            } else {
                $flagId = $apiFlag->id ?? null;
                $flagType = $apiFlag->flag ?? 'unknown';
                $reason = $apiFlag->reason ?? null;
                $snippet = $apiFlag->snippet ?? null;
                $lastAnalyzedAt = $apiFlag->last_analyzed_at ?? null;
                $expiredAt = $apiFlag->expired_at ?? null;
                $ignoredAt = $apiFlag->ignored_at ?? null;
            }

            $flag = new Flag();
            $flag->contentId = $content->id;
            $flag->flagId = $flagId;
            $flag->flag = $flagType;
            $flag->reason = $reason;
            $flag->snippet = $snippet;
            $flag->lastAnalyzedAt = $lastAnalyzedAt;
            $flag->expiredAt = $expiredAt;
            $flag->ignoredAt = $ignoredAt;

            if (Craft::$app->getElements()->saveElement($flag)) {
                Plugin::info("FlagManager: Created flag {$flag->flagId} for content #{$content->id}");

                return true;
            } else {
                Plugin::error("FlagManager: Failed to save new flag {$flag->flagId}: ".json_encode($flag->getErrors()));

                return false;
            }

        } catch (Exception $e) {
            Plugin::error("FlagManager: Error creating flag: ".$e->getMessage());
            Plugin::debug("FlagManager: Exception trace: ".$e->getTraceAsString());

            return false;
        }
    }

    /**
     * Get active flags for content
     *
     * @param  Content  $content
     * @return Flag[]
     */
    public function getActiveFlagsForContent(Content $content): array
    {
        return Flag::find()
            ->contentId($content->id)
            ->active(true)
            ->all();
    }

    /**
     * Get flag count for content
     *
     * @param  Content  $content
     * @return int
     */
    public function getFlagCountForContent(Content $content): int
    {
        return Flag::find()
            ->contentId($content->id)
            ->active(true)
            ->count();
    }

    /**
     * Mark a flag as ignored
     *
     * @param  Flag  $flag
     * @return bool Success status
     */
    public function ignoreFlag(Flag $flag): bool
    {
        try {
            // Call API to ignore the flag
            $content = $flag->getContent();
            if (! $content) {
                Plugin::error("FlagManager: Cannot ignore flag {$flag->flagId} - no associated content");

                return false;
            }

            Plugin::getInstance()->flag->ignore($content, $flag->flagId);

            // Update local flag
            return $flag->markAsIgnored();

        } catch (Exception $e) {
            Plugin::error("FlagManager: Error ignoring flag {$flag->flagId}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Reschedule a flag's expiration
     *
     * @param  Flag      $flag
     * @param  DateTime  $newExpiredAt
     * @return bool Success status
     */
    public function rescheduleFlag(Flag $flag, DateTime $newExpiredAt): bool
    {
        try {
            // Call API to reschedule the flag
            $content = $flag->getContent();
            if (! $content) {
                Plugin::error("FlagManager: Cannot reschedule flag {$flag->flagId} - no associated content");

                return false;
            }

            Plugin::getInstance()->flag->reschedule($content, $flag->flagId, $newExpiredAt);

            // Update local flag
            return $flag->updateExpiration($newExpiredAt);

        } catch (Exception $e) {
            Plugin::error("FlagManager: Error rescheduling flag {$flag->flagId}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Clean up orphaned flags (flags whose content no longer exists)
     *
     * @return int Number of flags cleaned up
     */
    public function cleanupOrphanedFlags(): int
    {
        try {
            $orphanedFlags = Flag::find()
                ->leftJoin('{{%neverstale_content}}', '{{%neverstale_content}}.id = {{%neverstale_flags}}.contentId')
                ->where(['{{%neverstale_content}}.id' => null])
                ->all();

            $cleanedCount = 0;
            foreach ($orphanedFlags as $flag) {
                if (Craft::$app->getElements()->deleteElement($flag)) {
                    $cleanedCount++;
                }
            }

            if ($cleanedCount > 0) {
                Plugin::info("FlagManager: Cleaned up {$cleanedCount} orphaned flags");
            }

            return $cleanedCount;

        } catch (Exception $e) {
            Plugin::error("FlagManager: Error cleaning up orphaned flags: ".$e->getMessage());

            return 0;
        }
    }
}
