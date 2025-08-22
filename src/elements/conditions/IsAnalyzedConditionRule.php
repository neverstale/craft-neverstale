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
 * Is Analyzed condition rule.
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   1.0.0
 */
class IsAnalyzedConditionRule extends BaseLightswitchConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Plugin::t('Is Analyzed');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['isAnalyzed'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var ContentQuery $query */
        $query->isAnalyzed($this->value);
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Content $element */
        $isAnalyzed = $element->isAnalyzed();

        return $this->value ? $isAnalyzed : ! $isAnalyzed;
    }
}
