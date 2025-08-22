<?php

namespace neverstale\neverstale\elements\conditions;

use craft\base\conditions\BaseNumberConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use neverstale\neverstale\elements\Content;
use neverstale\neverstale\elements\db\ContentQuery;
use neverstale\neverstale\Plugin;

/**
 * Flag Count condition rule.
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   1.0.0
 */
class FlagCountConditionRule extends BaseNumberConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Plugin::t('Flag Count');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['flagCount'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var ContentQuery $query */
        $query->flagCount($this->paramValue());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Content $element */
        return $this->matchValue($element->flagCount);
    }
}
