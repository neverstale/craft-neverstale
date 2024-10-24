<?php

namespace zaengle\neverstale\models;

use Craft;
use craft\base\Model;
use craft\elements\Entry;
use zaengle\neverstale\elements\NeverstaleSubmission;

/**
 * Neverstale ApiSubmission model
 *
 * Wraps the Craft data for submission to the NS API
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 *
 * @property-read Entry $entry
 * @property-read string $apiId
 * @property-read array<string,mixed> $apiData
 * @property-read NeverstaleSubmission $submission
 */
class ApiSubmission extends Model
{
    public string|int $customId;
    public string $channel;
    public ?string $url;
    private NeverstaleSubmission $submission;
    /** @var array<string,mixed> */
    public array $data;

    public static string $defaultChannel = 'default';

    /**
     * @inheritDoc
     */
    public function defineRules(): array
    {
        return [
            [['customId', 'data'], 'required'],
            [['url'], 'url'],
        ];
    }
    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }
    public function setChannel(string $channel): self
    {
        $this->channel = $channel;
        return $this;
    }
    /**
     * @param array<string,mixed> $data
     */
    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }
    public function getSubmission(): NeverstaleSubmission
    {
        return $this->submission;
    }
    public function getEntry(): Entry
    {
        return $this->submission->getEntry();
    }
    public function getApiId(): string
    {
        return (string) $this->customId;
    }
    /**
     * @return array<string,mixed>
     */
    public function getApiData(): array
    {
        return [
            'channel' => $this->channel,
            'url' => $this->url,
            'data' => $this->data,
        ];
    }

    protected function setSubmission(NeverstaleSubmission $submission): void
    {
        $this->submission = $submission;
    }
    public static function fromSubmission(NeverstaleSubmission $submission): self
    {
        return new self([
            'submission' => $submission,
            'url' => $submission->getEntry()?->cpEditUrl ?? null,
            'customId' => $submission->uid,
            'channel' => $submission->getEntry()?->getSection()?->handle ?? self::$defaultChannel,
            // @todo handle default serialization of data
            'data' => [],
        ]);
    }
}
