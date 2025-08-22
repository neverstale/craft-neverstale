<?php

namespace neverstale\neverstale\elements\conditions;

use craft\base\conditions\BaseLightswitchConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use neverstale\neverstale\elements\Content;
use neverstale\neverstale\elements\db\ContentQuery;
use neverstale\neverstale\Plugin;

/**
 * Has Flags condition rule.
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   1.0.0
 */
class HasFlagsConditionRule extends BaseLightswitchConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Plugin::t('Has Flags');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['hasFlags'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var ContentQuery $query */
        $query->hasFlags($this->value);
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Content $element */
        $hasFlags = $element->flagCount > 0;

        return $this->value ? $hasFlags : ! $hasFlags;
    }
}
