<?php

namespace zaengle\neverstale\services;

use Craft;
use craft\elements\Entry;
use craft\errors\ElementNotFoundException;
use craft\helpers\Queue;
use yii\base\Component;
use yii\base\Exception;
use zaengle\neverstale\elements\NeverstaleContent;
use zaengle\neverstale\jobs\CreateNeverstaleContentJob;
use zaengle\neverstale\Plugin;

/**
 * Neverstale Content service
 *
 * Handles the management of NeverstaleContents Elements
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
class Submission extends Component
{
    public function queue(Entry $entry): ?string
    {
        return Queue::push(new CreateNeverstaleContentJob([
            'entryId' => $entry->id,
        ]));
    }
    public function findOrCreate(Entry $entry): ?NeverstaleContent
    {
        $content = $this->find($entry);

        if (!$content) {
            $content = $this->create($entry);
            $this->save($content);

            Plugin::log(Plugin::t("Created NeverstaleContent #{contentId} for Entry #{entryId}", [
                'entryId' => $entry->id,
                'contentId' => $content->id,
            ]));
        }

        return $content;
    }
    public function find(Entry $entry): ?NeverstaleContent
    {
        return NeverstaleContent::findOne([
            'entryId' => $entry->canonicalId,
            'siteId' => $entry->siteId,
        ]);
    }
    public function create(Entry $entry): NeverstaleContent
    {
        return new NeverstaleContent([
            'entryId' => $entry->canonicalId,
            'siteId' => $entry->siteId,
        ]);
    }
    /**
     * @throws \Throwable
     * @throws Exception
     * @throws ElementNotFoundException
     */
    public function save(NeverstaleContent $content): bool
    {
        $saved = Craft::$app->getElements()->saveElement($content);

        if (!$saved) {
            Plugin::error("Failed to save content #{$content->id}" . print_r($content->getErrors(), true));
        }

        return $saved;
    }
}
