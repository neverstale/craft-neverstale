<?php

namespace zaengle\neverstale\models;

use Craft;
use craft\base\ElementInterface;
use craft\base\Model;
use zaengle\neverstale\Plugin;

/**
 * Element Context model
 *
 * @property-read ElementInterface $this->element
 */
class ElementContext extends Model
{
    public int $elementId;
    private ?ElementInterface $element;
    public int $siteId;

    /**
     * @inheritDoc
     * @return array<array>
     */
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['elementId', 'siteId'], 'required'],
            [['elementId', 'siteId'], 'number', 'integerOnly' => true],
        ]);
    }

    public function getElement(): ?ElementInterface
    {
        if ($this->element === null) {
            $this->element = Craft::$app->getElements()->getElementById(
                $this->elementId,
                null,
                $this->siteId);
        }
        return $this->element;
    }

    /**
     * @return array<string, mixed>
     */
    public function forApi(): array
    {
        return [
            'custom_id' => (string) $this->element->uid,
            'title' => $this->element->title,
            'author' => $this->element->author?->email ?? 'Unknown',
            'channel_id' => $this->element?->type->handle ?? 'Content',
            'url' => $this->element->getCpEditUrl(),
            'data' => Plugin::getInstance()->format->forApi($this->element),
        ];
    }
}
