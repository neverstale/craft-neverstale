<?php

namespace neverstale\craft\services;

use Craft;
use yii\base\Component;
use neverstale\craft\elements\NeverstaleContent;
use neverstale\craft\models\IngestContent;
use neverstale\craft\Plugin;

/**
 * Neverstale Format service
 *
 * Handles the formatting of Entry data for ingest to the Neverstale API
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
class Format extends Component
{
    /**
     * Format Content for ingest to the Neverstale API
     *
     * @param NeverstaleContent $content
     * @return IngestContent
     */
    public function forIngest(NeverstaleContent $content): IngestContent
    {
        $apiData = IngestContent::fromContent($content);

        // Apply any user supplied transformations to the data before ingest
        if ($transformer = $this->getCustomTransformer()) {
            return $transformer($apiData);
        }

        return $apiData;
    }
    /**
     * Get the custom data transformer from the plugin config, if one exists
     * @return callable|null
     */
    public function getCustomTransformer(): ?callable
    {
        $transformer = Plugin::getInstance()->config->get('transformer');

        if (is_callable($transformer)) {
            return $transformer;
        }
        return null;
    }
    public function entryContent(\craft\elements\Entry $entry): string
    {
        return trim(Craft::$app->view->renderTemplate('neverstale/format/_entry', [
            'entry' => $entry,
        ]));
    }
}
