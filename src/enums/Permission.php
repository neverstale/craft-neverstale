<?php

namespace zaengle\neverstale\enums;

/**
 * Neverstale Permission Enum
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
enum Permission: string
{
    case Scan = 'neverstale:scan';
    case View = 'neverstale:view';
    case Delete = 'neverstale:delete';
    case Ingest = 'neverstale:ingest';
}
