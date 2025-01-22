<?php

namespace neverstale\craft\services;

use yii\base\Component;
use neverstale\craft\elements\NeverstaleContent;
use neverstale\craft\Plugin;
use neverstale\api\Client;

/**
 * Flag service
 */
class Flag extends Component
{
    public Client $client;

    public function ignore(NeverstaleContent $content, string $flagId): bool
    {
        Plugin::info("Ignoring flag $flagId for content #{$content->id}");
        try {
            $this->client->ignoreFlag($flagId);
//          @todo this may not be needed as we may get another webhook
            $content->flagCount -= 1;
//          @todo how to handle dateExpired?
            $content->save();
            return true;
        } catch (\Exception $e) {
            Plugin::error("Error ignoring flag $flagId" . $e->getMessage());

            throw $e;
        }
    }
    public function reschedule(NeverstaleContent $content, string $flagId, \DateTime $newDate): bool
    {
        $this->client->rescheduleFlag($flagId, $newDate);

        Plugin::info("Rescheduling flag {$flagId} for content #{$content->id} to {$newDate->format('Y-m-d H:i:s')}");
        try {
            $this->client->rescheduleFlag($flagId, $newDate);
            return true;
        } catch (\Exception $e) {
            Plugin::error($e->getMessage());
            return false;
        }
    }
}
