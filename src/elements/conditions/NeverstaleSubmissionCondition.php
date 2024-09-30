<?php

namespace zaengle\neverstale\elements\conditions;

use Craft;
use craft\elements\conditions\ElementCondition;

/**
 * Submission condition
 */
class NeverstaleSubmissionCondition extends ElementCondition
{
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::conditionRuleTypes(), [
            // ...
        ]);
    }
}
