<?php

namespace neverstale\neverstale\models;

use craft\base\Model;
use craft\elements\Entry;
use Illuminate\Contracts\Support\Arrayable;
use neverstale\neverstale\elements\Content;
use neverstale\neverstale\Plugin;

/**
 * Neverstale ApiData model
 *
 * Wraps the Craft data for submission to the NS API
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   1.0.0
 * @see     https://github.com/zaengle/craft-neverstale
 *
 * @property-read Entry               $entry
 * @property-read string              $apiId
 * @property-read string              $customId
 * @property-read array<string,mixed> $apiData
 * @property-read Content             $content
 */
class IngestContent extends Model implements Arrayable
{
    public ?string $editUrl = null;
    public ?string $url = null;
    public string $channel = 'default';
    public ?string $title = null;
    public ?string $author = null;
    public string $data = '';
    private string $customId;
    private Content $content;

    public static function fromContent(Content $content): self
    {
        /** @var Entry $entry */
        $entry = $content->getEntry();

        return new self(array_merge(
            self::metaFromEntry($entry),
            [
                'content' => $content,
                'customId' => $content->getCustomId(),
                'data' => Plugin::getInstance()->format->entryContent($entry),
            ]));
    }

    public function getEntry(): ?Entry
    {
        return $this->content->getEntry();
    }

    /*
     * Getters / Setters
     */

    public static function metaFromEntry(Entry $entry): array
    {
        return [
            'author' => $entry->author?->name,
            'title' => $entry->title,
            'url' => $entry->url,
            'editUrl' => $entry->cpEditUrl,
            'channel' => $entry->getSection()?->handle ?? 'default',
        ];
    }

    public function getCustomId(): string
    {
        return $this->customId;
    }

    protected function setCustomId(string|int $customId): void
    {
        $this->customId = (string) $customId;
    }

    /**
     * @inheritDoc
     */
    public function defineRules(): array
    {
        return [
            [['data'], 'required'],
            [['url', 'editUrl'], 'url'],
        ];
    }

    public function setAuthor(string $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function setChannel(string $channel): self
    {
        $this->channel = $channel;

        return $this;
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

    public function getContent(): Content
    {
        return $this->content;
    }

    protected function setContent(Content $content): void
    {
        $this->content = $content;
    }

    /**
     * @param  array  $fields
     * @param  array  $expand
     * @param  true   $recursive
     * @return array<string,mixed>
     */
    public function toArray(array $fields = [], array $expand = [], $recursive = true): array
    {
        $result = collect([
            'channel' => $this->channel,
            'custom_id' => $this->customId,
            'data' => $this->data,
            'edit_url' => $this->editUrl,
            'title' => $this->title,
            'url' => $this->url,
            'author' => $this->author,
        ]);

        return $result->filter(fn($value) => $value !== null)->toArray();
    }
}
