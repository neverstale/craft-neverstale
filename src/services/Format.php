<?php

namespace zaengle\neverstale\services;

use Craft;
use yii\base\Component;
use zaengle\neverstale\elements\NeverstaleSubmission;
use zaengle\neverstale\models\ContentSubmission;
use zaengle\neverstale\Plugin;

/**
 * Neverstale Format service
 *
 * Handles the formatting of Entry data for submission to the Neverstale API
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
class Format extends Component
{
    /**
     * Format a Submission for sending to the Neverstale API
     *
     * @param NeverstaleSubmission $submission
     * @return ContentSubmission
     */
    public function forApi(NeverstaleSubmission $submission): ContentSubmission
    {
        $apiData = ContentSubmission::fromSubmission($submission);

        // Apply any user supplied transformations to the data before submission
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
        return trim(Craft::$app->view->renderTemplate('neverstale/format/_submission', [
            'entry' => $entry,
        ]));
    }
}
