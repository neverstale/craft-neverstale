<?php

namespace zaengle\neverstale\services;

use craft\base\ElementInterface;
use craft\helpers\ElementHelper;
use yii\base\Component;
use zaengle\neverstale\Plugin;

/**
 * Element service
 */
class Element extends Component
{
    /**
     * Should this Element be submitted?
     */
    public function isSubmittable(ElementInterface $element): bool
    {
        if (
            !Plugin::getInstance()->getSettings()->enable
            ||
            ElementHelper::isDraftOrRevision($element)
            ||
            !$element->getIsCanonical()
        ) {
            return false;
        }
        return $this->hasSubmissionEnabled($element);
    }

    /**
     * Is submission enabled for this Element?
     */
    public function hasSubmissionEnabled(ElementInterface $element): bool
    {
        // @todo per-section + per element checks here
        return true;
    }
}
