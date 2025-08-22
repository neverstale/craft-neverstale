<?php

namespace neverstale\neverstale\models;

use Craft;
use craft\base\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use neverstale\neverstale\elements\Content;

/**
 * Custom Id model
 */
class CustomId extends Model
{
    public const KEY_SEPARATOR = ':';
    public const PART_DELIMITER = '|';
    public const ATTR_MAP = [
        'entryId' => 'el',
        'siteId' => 'si',
        'id' => 'co',
    ];
    public const ENV_KEY = 'env';
    public string $entryId;
    public string $siteId;
    public string $id;
    public string $env;

    public static function fromContent(Content $content): self
    {
        if (! $content->entryId) {
            throw new InvalidArgumentException('Content must have an entry ID');
        }

        $contentKeys = collect(self::ATTR_MAP)->keys();

        /** @var Collection $attrs */
        $attrs = $contentKeys->reduce(function (Collection $carry, string $attr) use ($content) {
            $carry->put($attr, $content->$attr);

            return $carry;
        }, collect());

        $attrs->put(self::ENV_KEY, Craft::$app->env);

        return new self($attrs->toArray());
    }

    public static function parse(string $customId): self
    {
        $map = collect(self::ATTR_MAP)->flip()->put(self::ENV_KEY, self::ENV_KEY);

        $attrs = collect(
            explode(self::PART_DELIMITER, $customId)
        )
            ->reduce(function ($carry, $part) use ($map) {
                [$key, $value] = explode(self::KEY_SEPARATOR, $part);

                $carry[$map->get($key) ?? $key] = $value;

                return $carry;
            }, []);

        return new self($attrs);
    }

    public function toString(): string
    {
        return collect(self::ATTR_MAP)
            ->map(fn($strKey, $attr): string => $strKey.self::KEY_SEPARATOR.$this->$attr)
            ->push(self::ENV_KEY.self::KEY_SEPARATOR.$this->env)
            ->implode(self::PART_DELIMITER);
    }
}
