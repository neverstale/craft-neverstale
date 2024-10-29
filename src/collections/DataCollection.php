<?php

namespace zaengle\neverstale\collections;

use zaengle\neverstale\models\ApiData;

class DataCollection extends TypedCollection
{
    protected array $types = ['string', ApiData::class];
}
