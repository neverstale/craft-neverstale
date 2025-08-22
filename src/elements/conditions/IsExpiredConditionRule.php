<?php

namespace neverstale\neverstale\elements\conditions;

use craft\base\conditions\BaseLightswitchConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use DateTime;
use neverstale\neverstale\elements\Content;
use neverstale\neverstale\elements\db\ContentQuery;
use neverstale\neverstale\Plugin;

/**
 * Is Expired condition rule.
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   1.0.0
 */
class IsExpiredConditionRule extends BaseLightswitchConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Plugin::t('Is Expired');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['isExpired'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var ContentQuery $query */
        $query->isExpired($this->value);
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Content $element */
        if (! $element->dateExpired) {
            return ! $this->value; // Not expired if no expiration date
        }

        $isExpired = $element->dateExpired <= new DateTime();

        return $this->value ? $isExpired : ! $isExpired;
    }
}
