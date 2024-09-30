<?php

namespace zaengle\neverstale\records;

use Craft;
use craft\db\ActiveRecord;

/**
 * Submission record
 */
class Submission extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%neverstale_submissions}}';
    }
}
