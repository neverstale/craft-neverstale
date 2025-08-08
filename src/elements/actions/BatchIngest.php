<?php

namespace neverstale\craft\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;
use neverstale\craft\elements\NeverstaleContent;
use neverstale\craft\enums\Permission;
use neverstale\craft\Plugin;

/**
 * Batch Ingest Element Action
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
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
        /** @var NeverstaleContent[] $elements */
        $elements = $query->all();

        if (empty($elements)) {
            $this->setMessage(Plugin::t('No content items selected'));
            return false;
        }

        // Use batch API for more efficient processing
        $result = Plugin::getInstance()->content->batchIngest($elements);

        $successCount = $result['successCount'];
        $errorCount = $result['errorCount'];
        $errors = $result['errors'];

        if ($errorCount === 0) {
            $this->setMessage(Plugin::t('Successfully submitted {count} content items to Neverstale', ['count' => $successCount]));
            return true;
        } elseif ($successCount === 0) {
            $this->setMessage(Plugin::t('Failed to submit all content items to Neverstale'));
            return false;
        } else {
            $this->setMessage(Plugin::t('Submitted {successCount} of {total} content items. {errorCount} failed.', [
                'successCount' => $successCount,
                'total' => count($elements),
                'errorCount' => $errorCount
            ]));
            return true; // Partial success is still considered success
        }
    }
}
