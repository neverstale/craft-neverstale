<?php

namespace neverstale\neverstale\registrars;

use craft\web\twig\variables\CraftVariable;
use neverstale\neverstale\variables\NeverstaleVariable;
use yii\base\Event;

/**
 * Handles registration of Twig variables and extensions
 */
class TwigRegistrar implements RegistrarInterface
{
    public function register(): void
    {
        $this->registerTwigVariable();
    }

    /**
     * Register Twig variables
     */
    private function registerTwigVariable(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                $variable = $event->sender;
                $variable->set('neverstale', NeverstaleVariable::class);
            },
        );
    }
}
