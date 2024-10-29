<?php

namespace zaengle\neverstale\enums;

use craft\enums\Color;
use zaengle\neverstale\Plugin;

/**
 * Neverstale Submission Status Enum
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
enum SubmissionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Flagged = 'flagged';
    case Clean = 'clean';
    case Failed = 'failed';
    case Archived = 'archived';
    case Unknown = 'unknown';

    // get the color of the status
    public function color(): Color
    {
        return match ($this) {
            self::Pending => Color::Orange,
            self::Processing => Color::Purple,
            self::Clean => Color::Teal,
            self::Flagged => Color::Red,
            self::Failed => Color::Red,
            self::Archived => Color::Gray,
            default => Color::Gray,
        };
    }

    // get the label of the status
    public function label(): string
    {
        return match ($this) {
            self::Pending => Plugin::t('Pending'),
            self::Processing => Plugin::t('Processing'),
            self::Clean => Plugin::t('Clean'),
            self::Flagged => Plugin::t('Flagged'),
            self::Failed => Plugin::t('Failed'),
            self::Archived => Plugin::t('Archived'),
            default => Plugin::t('Unknown'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'clock',
            self::Processing => 'hammer',
            self::Clean => 'check',
            self::Flagged => 'flag',
            self::Failed => 'triangle-exclamation',
            self::Archived => 'file',
            default => 'question',
        };
    }
}
