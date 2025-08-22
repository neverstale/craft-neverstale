<?php

namespace neverstale\neverstale\elements\conditions;

use craft\base\conditions\BaseElementSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use neverstale\neverstale\elements\Content;
use neverstale\neverstale\elements\db\ContentQuery;
use neverstale\neverstale\Plugin;

/**
 * Entry condition rule.
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   1.0.0
 */
class EntryConditionRule extends BaseElementSelectConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Plugin::t('Entry');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['entryId'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var ContentQuery $query */
        $query->entryId($this->paramValue());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Content $element */
        return $this->matchValue($element->entryId);
    }

    protected function elementType(): string
    {
        return Entry::class;
    }
}
