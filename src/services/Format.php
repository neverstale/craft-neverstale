<?php

namespace zaengle\neverstale\services;

use yii\base\Component;
use zaengle\neverstale\elements\NeverstaleSubmission;
use zaengle\neverstale\models\ApiSubmission;

/**
 * Format service
 */
class Format extends Component
{
    public function forApi(NeverstaleSubmission $submission): ApiSubmission
    {
        // @todo handle custom transforms here
        return ApiSubmission::fromSubmission($submission);
    }
}
