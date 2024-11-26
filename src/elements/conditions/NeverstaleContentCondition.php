<?php

namespace zaengle\neverstale\elements\conditions;

use Craft;
use craft\elements\conditions\ElementCondition;

/**
 * Neverstale Content Condition
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
class NeverstaleContentCondition extends ElementCondition
{
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::selectableConditionRules(), [
            // ...
        ]);
    }
}
