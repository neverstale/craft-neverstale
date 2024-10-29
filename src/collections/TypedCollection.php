<?php

namespace zaengle\neverstale\collections;

use Illuminate\Support\Collection;
use InvalidArgumentException;

class TypedCollection extends Collection
{
    /**
     * @var array<int,string>
     */
    protected array $types;

    /**
     * @param null|string|array<string> $types
     * @param array<int,mixed> $items
     */
    public function __construct(array $items = [], string|array|null $types = null)
    {
        if ($types) {
            $this->types = is_array($types) ? $types : [$types];
        }
        parent::__construct($items);
    }

    /**
     * @return Collection<string>
     */
    public function getTypes(): Collection
    {
        return collect($this->types);
    }

    /**
     * @inheritDoc
     */
    public function offsetSet($key, $value): void
    {
        if (!$this->isValidType($value)) {
            throw new InvalidArgumentException("Value must be of type " . $this->getTypes()->implode(', '));
        }
        parent::offsetSet($key, $value);
    }

    /**
     * @inheritDoc
     */
    public function push(...$values): self
    {
        foreach ($values as $value) {
            if (!$this->isValidType($value)) {
                throw new InvalidArgumentException("Value must be of type " . $this->getTypes()->implode(', '));
            }
        }
        return parent::push(...$values);
    }


    /**
     * Check if the value is a valid type for the collection
     *
     * @param mixed $value
     * @return bool
     */
    public function isValidType($value): bool
    {
        return $this->getTypes()->contains(function($type) use ($value) {
            if (class_exists($type)) {
                return $value instanceof $type;
            }
            return gettype($value) === $type;
        });
    }

    public static function from(array|Collection $items): self
    {
        if ($items instanceof Collection) {
            $items = $items->all();
        }
        return new static($items);
    }
}

