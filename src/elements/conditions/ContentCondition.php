<?php

namespace neverstale\neverstale\elements\conditions;

use craft\elements\conditions\ElementCondition;

/**
 * Neverstale Content Condition
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   1.0.0
 * @see     https://github.com/zaengle/craft-neverstale
 */
class ContentCondition extends ElementCondition
{
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::selectableConditionRules(), [
            AnalysisStatusConditionRule::class,
            EntryConditionRule::class,
            FlagCountConditionRule::class,
            HasFlagsConditionRule::class,
            IsAnalyzedConditionRule::class,
            IsExpiredConditionRule::class,
            DateAnalyzedConditionRule::class,
            DateExpiredConditionRule::class,
        ]);
    }
}
