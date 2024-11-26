<?php

namespace zaengle\neverstale\behaviors;

use craft\base\ElementInterface;
use craft\elements\Entry;
use yii\base\Behavior;
use zaengle\neverstale\elements\db\NeverstaleContentQuery;
use zaengle\neverstale\elements\NeverstaleContent;

/**
 * Has Neverstale Content Behavior
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 *
 * @property-read NeverstaleContent|null $neverstaleContent
 * @property Entry $owner
 */
class HasNeverstaleContentBehavior extends Behavior
{
    public function getNeverstaleContent(): NeverstaleContent|ElementInterface|null
    {
        /** @var NeverstaleContentQuery $query */
        $query = NeverstaleContent::find();

        return $query->entryId($this->owner->id)
            ->orderBy(['dateUpdated' => SORT_DESC])
            ->one();
    }
}
