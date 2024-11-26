<?php

namespace zaengle\neverstale\models;

use craft\base\Model;
use craft\elements\Entry;
use Illuminate\Contracts\Support\Arrayable;
use zaengle\neverstale\elements\NeverstaleSubmission;
use zaengle\neverstale\Plugin;

/**
 * Neverstale ApiData model
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
 * @property-read string $customId
 * @property-read array<string,mixed> $apiData
 * @property-read NeverstaleSubmission $submission
 */
class ContentSubmission extends Model implements Arrayable
{
    public ?string $editUrl = null;
    public ?string $url = null;
    public string $channelId = 'default';
    public ?string $title = null;
    public ?string $author = null;
    public string $data = '';
    private string $customId;
    private NeverstaleSubmission $submission;

    /**
     * @inheritDoc
     */
    public function defineRules(): array
    {
        return [
            [['submission'], 'required'],
            [['url', 'editUrl'], 'url'],
        ];
    }
    public function setAuthor(string $author): self
    {
        $this->author = $author;
        return $this;
    }
    /*
     * Getters / Setters
     */
    public function setChannelId(string $channelId): self
    {
        $this->channelId = $channelId;
        return $this;
    }
    protected function setCustomId(string|int $customId): void
    {
        $this->customId = (string) $customId;
    }
    public function getCustomId(): string
    {
        return $this->customId;
    }
    public function setData(string $data): self
    {
        $this->data = $data;
        return $this;
    }
    public function appendData(string $value): self
    {
        $this->data .= $value;
        return $this;
    }
    public function setEditUrl(string $url): self
    {
        $this->editUrl = $url;
        return $this;
    }
    public function getEntry(): ?Entry
    {
        return $this->submission->getEntry();
    }
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }
    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }
    public function getSubmission(): NeverstaleSubmission
    {
        return $this->submission;
    }
    protected function setSubmission(NeverstaleSubmission $submission): void
    {
        $this->submission = $submission;
    }
    /**
     * @param array $fields
     * @param array $expand
     * @param true $recursive
     * @return array<string,mixed>
     */
    public function toArray(array $fields = [], array $expand = [], $recursive = true): array
    {
        $result = collect([
            'channel_id' => $this->channelId,
            'custom_id' => $this->customId,
            'data' => $this->data,
            'edit_url' => $this->editUrl,
            'title' => $this->title,
            'url' => $this->url,
            'author' => $this->author,
        ]);

        return $result->filter(fn ($value) => $value !== null)->toArray();
    }
    public static function fromSubmission(NeverstaleSubmission $submission): self
    {
        /** @var Entry $entry  */
        $entry = $submission->getEntry();
        return new self(array_merge(
            self::metaFromEntry($entry),
            [
                'submission' => $submission,
                'customId' => $entry->uid,
                'data'  => Plugin::getInstance()->format->entryContent($entry),
            ]));
    }
    public static function metaFromEntry(Entry $entry): array
    {
        return [
            'author' => $entry->author?->name,
            'title' => $entry->title,
            'url' => $entry->url,
            'editUrl' => $entry->cpEditUrl,
            'channelId' => $entry->getSection()?->handle ?? 'default',
        ];
    }
}
