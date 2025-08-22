<?php

namespace neverstale\neverstale\elements\conditions;

use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use neverstale\neverstale\elements\Content;
use neverstale\neverstale\elements\db\ContentQuery;
use neverstale\neverstale\enums\AnalysisStatus;
use neverstale\neverstale\Plugin;

/**
 * Analysis Status condition rule.
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   1.0.0
 */
class AnalysisStatusConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Plugin::t('Analysis Status');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['analysisStatus'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var ContentQuery $query */
        $query->analysisStatus($this->paramValue());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Content $element */
        return $this->matchValue($element->getStatus());
    }

    protected function options(): array
    {
        $options = [];
        foreach (AnalysisStatus::cases() as $status) {
            $options[] = [
                'value' => $status->value,
                'label' => $status->label(),
            ];
        }

        return $options;
    }
}
