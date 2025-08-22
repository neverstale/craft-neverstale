<?php

namespace neverstale\neverstale\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseUiElement;
use craft\helpers\Html;

class NeverstaleTab extends BaseUiElement
{
    public function selectorLabel(): string
    {
        return Craft::t('neverstale', 'Neverstale');
    }
    
    protected function selectorIcon(): ?string
    {
        return 'clock';
    }

    public function formHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element || !$element->id) {
            return Html::tag('div', 
                Craft::t('neverstale', 'Neverstale data will be available after the entry is saved.'),
                ['class' => 'pane']
            );
        }

        return Craft::$app->getView()->renderTemplate(
            'neverstale/_components/entry-tab',
            [
                'element' => $element,
                'static' => $static,
            ]
        );
    }

}