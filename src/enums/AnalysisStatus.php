<?php

namespace zaengle\neverstale\enums;

use zaengle\neverstale\Plugin;
use craft\enums\Color;
/**
 * Neverstale Analysis Status Enum
 *
 * Represents the status of content analysis.
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
enum AnalysisStatus: string
{
    case UNSENT = 'unsent';

    case STALE = 'stale';
    case PENDING_INITIAL_ANALYSIS = 'pending-initial-analysis';
    case PENDING_REANALYSIS = 'pending-reanalysis';
    case PROCESSING_REANALYSIS = 'processing-reanalysis';
    case PROCESSING_INITIAL_ANALYSIS = 'processing-initial-analysis';
    case ANALYZED_CLEAN = 'analyzed-clean';
    case ANALYZED_FLAGGED = 'analyzed-flagged';
    case ANALYZED_ERROR = 'analyzed-error';
    case UNKNOWN = 'unknown';
    case API_ERROR = 'api-error';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::UNSENT, self::STALE => Plugin::t('Pending'),
            self::PENDING_INITIAL_ANALYSIS => Plugin::t('Pending Initial Analysis'),
            self::PENDING_REANALYSIS => Plugin::t('Pending Reanalysis'),
            self::PROCESSING_REANALYSIS, self::PROCESSING_INITIAL_ANALYSIS => Plugin::t('Processing'),
            self::ANALYZED_CLEAN => Plugin::t('Clean'),
            self::ANALYZED_FLAGGED => Plugin::t('Flagged'),
            self::ANALYZED_ERROR => Plugin::t('Error'),
            self::API_ERROR => Plugin::t('API Error'),
            self::ARCHIVED => Plugin::t('Archived'),
            default => Plugin::t('Unknown'),
        };
    }

    public function color(): Color
    {
        return match ($this) {
            self::UNSENT, self::STALE => Color::Orange,
            self::PENDING_INITIAL_ANALYSIS, self::PENDING_REANALYSIS => Color::Pink,
            self::PROCESSING_REANALYSIS, self::PROCESSING_INITIAL_ANALYSIS => Color::Purple,
            self::ANALYZED_CLEAN => Color::Teal,
            self::ANALYZED_FLAGGED => Color::Amber,
            self::ANALYZED_ERROR => Color::Red,
            self::API_ERROR => Color::Red,
            self::ARCHIVED => Color::Gray,
            default => Color::Gray,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::UNSENT, self::STALE => 'envelope',
            self::PROCESSING_REANALYSIS => 'hammer',
            self::PENDING_INITIAL_ANALYSIS => 'clock',
            self::PENDING_REANALYSIS => 'clock-rotate-left',
            self::ANALYZED_CLEAN => 'check',
            self::ANALYZED_FLAGGED => 'flag',
            self::ANALYZED_ERROR, self::API_ERROR => 'triangle-exclamation',
            self::ARCHIVED => 'file',
            default => 'question',
        };
    }
}
