<?php

namespace neverstale\neverstale\behaviors;

use craft\elements\Entry;
use neverstale\neverstale\elements\Content;
use yii\base\Behavior;
use yii\base\InvalidConfigException;

/**
 * Has Neverstale Content Behavior
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   1.0.0
 *
 * @property-read Content|null $neverstaleContent
 * @property Entry             $owner
 */
class HasNeverstaleContentBehavior extends Behavior
{
    /**
     * Because this adds a property to the owner,
     * we want to be specific about the  name of the property
     * to avoid conflicts with other behaviors or properties.
     * @return Content|null
     * @throws InvalidConfigException
     */
    public function getNeverstaleContent(): ?Content
    {
        return Content::find()
            ->entryId($this->owner->canonicalId ?? $this->owner->id)
            ->siteId($this->owner->siteId)
            ->one();
    }
}
