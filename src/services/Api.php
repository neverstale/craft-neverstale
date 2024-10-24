<?php

namespace zaengle\neverstale\services;

use Craft;
use yii\base\Component;
use zaengle\neverstale\elements\NeverstaleSubmission;
use zaengle\neverstale\support\ApiClient;

/**
 * Neverstale API Service
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
class Api extends Component
{
    public ApiClient $client;

    public function upsert(NeverstaleSubmission $submission): void
    {
//        try {
            $apiSubmission = $submission->formatForApi();

            $response = $this->client->upsert(
                $apiSubmission->getApiId(),
                $apiSubmission->getApiData()
            );
//        } catch (\Exception $e) {
//
//        }
    }
}
