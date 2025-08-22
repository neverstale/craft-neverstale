<?php

namespace neverstale\neverstale\registrars;

use craft\events\RegisterComponentTypesEvent;
use craft\services\Elements;
use neverstale\neverstale\elements\Content;
use neverstale\neverstale\elements\Flag;
use yii\base\Event;

/**
 * Handles registration of custom element types
 */
class ElementRegistrar implements RegistrarInterface
{
    public function register(): void
    {
        $this->registerElementTypes();
    }

    /**
     * Register custom element types
     */
    private function registerElementTypes(): void
    {
        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = Content::class;
                $event->types[] = Flag::class;
            }
        );
    }
}
