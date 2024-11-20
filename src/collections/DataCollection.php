<?php

namespace zaengle\neverstale\collections;

use zaengle\neverstale\models\ApiSubmission;

class DataCollection extends TypedCollection
{
    protected array $types = ['string', ApiSubmission::class];
}
