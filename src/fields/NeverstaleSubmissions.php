<?php

namespace zaengle\neverstale\fields;

use craft\base\ElementInterface;
use craft\base\SortableFieldInterface;
use craft\helpers\Html;
use craft\fields\BaseRelationField;

use zaengle\neverstale\elements\NeverstaleSubmission;
use zaengle\neverstale\Plugin;

/**
 * Neverstale Related Submissions Field Type
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 *
 * @property-read array $elementValidationRules
 * @property-read null|string $settingsHtml
 * @property-read null|string|array $elementConditionRuleType
 */
class NeverstaleSubmissions extends BaseRelationField implements SortableFieldInterface
{
    public static function displayName(): string
    {
        return Plugin::t('Neverstale Submissions');
    }

    public static function icon(): string
    {
        return 'i-cursor';
    }

    public static function phpType(): string
    {
        return 'mixed';
    }
    /**
     * @inheritdoc
     * @return array<string, string>
     */
    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            // ...
        ]);
    }

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // ...
        ]);
    }

    public function getSettingsHtml(): ?string
    {
        return null;
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element): mixed
    {
        return $value;
    }

    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        return Html::textarea($this->handle, $value);
    }

    public function getElementValidationRules(): array
    {
        return [];
    }

//    protected function searchKeywords(mixed $value, ElementInterface $element): string
//    {
//        return StringHelper::toString($value, ' ');
//    }

    public function getElementConditionRuleType(): array|string|null
    {
        return null;
    }


    public static function elementType(): string
    {
        return NeverstaleSubmission::class;
    }
}
