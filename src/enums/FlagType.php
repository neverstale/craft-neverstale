<?php

namespace zaengle\neverstale\enums;

/**
 * Neverstale Flag Type Enum
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
enum FlagType: string
{
    case Irrelevant = 'irrelevant';
    case Expired = 'expired';
    case TimeSensitive = 'timeSensitive';
    case Other = 'other';
}
