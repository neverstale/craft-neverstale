<?php

namespace zaengle\neverstale\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\PreviewableFieldInterface;
use craft\base\SortableFieldInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use yii\db\ExpressionInterface;
use yii\db\Schema;


use craft\fields\BaseRelationField;
use zaengle\neverstale\elements\NeverstaleSubmission;
/**
 * Neverstale Submissions field type
 *
 * @property-read array $elementValidationRules
 * @property-read null|string $settingsHtml
 * @property-read null|string|array $elementConditionRuleType
 */
class NeverstaleSubmissions extends BaseRelationField implements PreviewableFieldInterface, SortableFieldInterface
{
    public static function displayName(): string
    {
        return Craft::t('neverstale', 'Neverstale Submissions');
    }

    public static function icon(): string
    {
        return 'i-cursor';
    }

    public static function phpType(): string
    {
        return 'mixed';
    }

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
