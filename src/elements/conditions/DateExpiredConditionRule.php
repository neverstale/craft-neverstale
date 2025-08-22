<?php

namespace neverstale\neverstale\elements\conditions;

use craft\base\conditions\BaseDateTimeConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use neverstale\neverstale\elements\Content;
use neverstale\neverstale\elements\db\ContentQuery;
use neverstale\neverstale\Plugin;

/**
 * Date Expired condition rule.
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   1.0.0
 */
class DateExpiredConditionRule extends BaseDateTimeConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Plugin::t('Date Expired');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['dateExpired'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var ContentQuery $query */
        $query->dateExpired($this->paramValue());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Content $element */
        return $this->matchValue($element->dateExpired);
    }
}
