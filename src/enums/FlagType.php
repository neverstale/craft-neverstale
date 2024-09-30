<?php

namespace zaengle\neverstale\enums;

enum FlagType: string
{
    case Irrelevant = 'irrelevant';
    case Expired = 'expired';
    case TimeSensitive = 'timeSensitive';
    case Other = 'other';
}

