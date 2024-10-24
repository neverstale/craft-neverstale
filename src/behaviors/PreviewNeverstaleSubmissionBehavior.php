<?php

namespace zaengle\neverstale\behaviors;

use craft\base\ElementInterface;
use craft\elements\Entry;
use yii\base\Behavior;
use zaengle\neverstale\elements\db\NeverstaleSubmissionQuery;
use zaengle\neverstale\elements\NeverstaleSubmission;

/**
 * Neverstale Submission Preview Behavior
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 *
 * @property-read NeverstaleSubmission|null $neverstaleSubmission
 * @property Entry $owner
 */
class PreviewNeverstaleSubmissionBehavior extends Behavior
{
    public function getNeverstaleSubmission(): NeverstaleSubmission|ElementInterface|null
    {
        /** @var NeverstaleSubmissionQuery $query */
        $query = NeverstaleSubmission::find();

        return $query->entryId($this->owner->id)
            ->orderBy(['dateUpdated' => SORT_DESC])
            ->one();
    }
}
