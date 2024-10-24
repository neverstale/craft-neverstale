<?php

namespace zaengle\neverstale\services;

use yii\base\Component;
use zaengle\neverstale\elements\NeverstaleSubmission;
use zaengle\neverstale\models\ApiSubmission;

/**
 * Neverstale Format service
 *
 * Handles the formatting of Entry data for submission to the Neverstale API
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
class Format extends Component
{
    public function forApi(NeverstaleSubmission $submission): ApiSubmission
    {
        // @todo handle custom transforms here
        return ApiSubmission::fromSubmission($submission);
    }
}
