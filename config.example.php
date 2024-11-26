<?php

/**
 * Neverstale Settings
 *
 * Values here will override those set via the CraftCMS Control Panel
 *
 * neverstale.php
 */

use zaengle\neverstale\models\ContentSubmission;

return [
    '*' => [
        /**
         * $apiKey string
         *
         * You can find your API key in the Neverstale dashboard under the content source
         * for this project
         */
//        'apiKey' => \craft\helpers\App::env('NEVERSTALE_API_KEY'),
        /**
         * $enable bool|callable
         *
         * Enable or disable submission to Neverstale
         *
         * This can be a boolean or a callable that receives the current Entry as its
         * only parameter and returns a boolean
         *
         * If set to a static boolean, Neverstale will use the `sections` setting
         * below to determine which entries to submit to Neverstale.
         *
         * If set to a callable, the sections setting will not be used, and the callable
         * must carry out all checks necessary to determine if the entry should be submitted
         */
//        'enable' => true,
//        'enable' => static function (\craft\elements\Entry $entry): bool {
//            // do per-entry checks here
//            return !$entry->excludeFromNeverstale;
//        },
        /**
         * $sections array
         *
         * An array of section handles to submit to Neverstale
         *
         * - Ignored if the `enable` setting is a callable
         * - Will override the `sections` setting from the Neverstale settings in the
         * Control Panel`
         */
//        'sections' => [
//            'homepage',
//            'pages',
//        ],
        /**
         * $transformer callable
         *
         * Transform a submission before sending it to the Neverstale API
         */
//        'transformer' => static function (ContentSubmission $contentSubmission): ContentSubmission {
//            return $contentSubmission;
//        },
    ],
];
