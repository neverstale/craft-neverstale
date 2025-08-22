<?php

namespace neverstale\neverstale\services;

use Craft;
use Exception;
use neverstale\neverstale\elements\Content;
use neverstale\neverstale\models\IngestContent;
use neverstale\neverstale\Plugin;
use yii\base\Component;

/**
 * Neverstale Format Service
 *
 * Handles the formatting of Entry data for ingest to the Neverstale API.
 * Provides data transformation capabilities and custom transformer support.
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   2.0.0
 * @see     https://github.com/neverstale/craft-neverstale
 */
class Format extends Component
{
    /**
     * Extract formatted content from an Entry for API submission
     *
     * Uses a Twig template to render the entry content in a format
     * suitable for analysis by the Neverstale API.
     *
     * @param  \craft\elements\Entry  $entry  Entry element to format
     * @return string Formatted entry content
     */
    public function entryContent(\craft\elements\Entry $entry): string
    {
        try {
            return trim(Craft::$app->view->renderTemplate('neverstale/format/_entry.twig', [
                'entry' => $entry,
            ]));
        } catch (Exception $e) {
            Plugin::error("Failed to format entry content for #{$entry->id}: {$e->getMessage()}");

            // Fallback to basic content extraction
            return $this->extractBasicContent($entry);
        }
    }

    /**
     * Extract basic content from an entry as fallback
     *
     * Provides a simple content extraction method when template
     * rendering fails, ensuring the API always receives some content.
     *
     * @param  \craft\elements\Entry  $entry  Entry element
     * @return string Basic extracted content
     */
    protected function extractBasicContent(\craft\elements\Entry $entry): string
    {
        $content = [];

        // Add title
        if ($entry->title) {
            $content[] = $entry->title;
        }

        // Add basic field content
        foreach ($entry->getFieldLayout()->getCustomFields() as $field) {
            $fieldValue = $entry->getFieldValue($field->handle);

            if ($fieldValue && is_string($fieldValue)) {
                $content[] = strip_tags($fieldValue);
            } elseif ($fieldValue && method_exists($fieldValue, '__toString')) {
                $content[] = strip_tags((string) $fieldValue);
            }
        }

        return implode("\n\n", array_filter($content));
    }

    /**
     * Sanitize content for API submission
     *
     * Removes potentially problematic characters and formatting
     * that might interfere with API processing.
     *
     * @param  string  $content  Raw content
     * @return string Sanitized content
     */
    public function sanitizeContent(string $content): string
    {
        // Remove excessive whitespace
        $content = preg_replace('/\s+/', ' ', $content);

        // Remove HTML tags if any remain
        $content = strip_tags($content);

        // Remove control characters except newlines and tabs
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);

        // Normalize line endings
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        // Trim and return
        return trim($content);
    }

    /**
     * Prepare batch data for API submission
     *
     * Formats multiple content items for batch API operations,
     * ensuring consistency and proper validation.
     *
     * @param  Content[]  $contents  Array of content elements
     * @return array Formatted batch data
     */
    public function prepareBatchData(array $contents): array
    {
        $batchData = [];

        foreach ($contents as $content) {
            try {
                $formatted = $this->forIngest($content);
                $validation = $this->validateContent($formatted);

                if ($validation['valid']) {
                    $batchData[] = $formatted->toArray();
                } else {
                    Plugin::error("Content #{$content->id} failed validation: ".implode(', ', $validation['errors']));
                }
            } catch (Exception $e) {
                Plugin::error("Failed to format content #{$content->id}: {$e->getMessage()}");
            }
        }

        return $batchData;
    }

    /**
     * Format Content for ingest to the Neverstale API
     *
     * Prepares content data for API submission, applying any custom
     * transformations defined in the plugin configuration.
     *
     * @param  Content  $content  Content element to format
     * @return IngestContent Formatted content ready for API submission
     */
    public function forIngest(Content $content): IngestContent
    {
        $apiData = IngestContent::fromContent($content);

        // Apply any user supplied transformations to the data before ingest
        if ($transformer = $this->getCustomTransformer()) {
            try {
                $transformedData = $transformer($apiData);

                // Ensure the transformer returns an IngestContent instance
                if ($transformedData instanceof IngestContent) {
                    return $transformedData;
                }

                Plugin::error('Custom transformer must return an IngestContent instance');

                return $apiData;
            } catch (Exception $e) {
                Plugin::error("Custom transformer failed: {$e->getMessage()}");

                return $apiData;
            }
        }

        return $apiData;
    }

    /**
     * Get the custom data transformer from the plugin config, if one exists
     *
     * The transformer should be a callable that accepts an IngestContent
     * instance and returns a modified IngestContent instance.
     *
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

    /**
     * Validate formatted content before API submission
     *
     * Ensures the content meets API requirements for length,
     * format, and other constraints.
     *
     * @param  IngestContent  $content  Formatted content
     * @return array Validation results ['valid' => bool, 'errors' => array]
     */
    public function validateContent(IngestContent $content): array
    {
        $errors = [];

        // Check required fields
        if (empty($content->customId)) {
            $errors[] = 'Custom ID is required';
        }

        if (empty($content->data)) {
            $errors[] = 'Content is required';
        }

        // Check content length constraints
        if (strlen($content->data) > 100000) { // 100KB limit
            $errors[] = 'Content exceeds maximum length of 100KB';
        }

        if (strlen($content->data) < 10) {
            $errors[] = 'Content is too short (minimum 10 characters)';
        }

        // Check title length if present
        if (! empty($content->title) && strlen($content->title) > 500) {
            $errors[] = 'Title exceeds maximum length of 500 characters';
        }

        // Check URL format if present
        if (! empty($content->url) && ! filter_var($content->url, FILTER_VALIDATE_URL)) {
            $errors[] = 'Invalid URL format';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
