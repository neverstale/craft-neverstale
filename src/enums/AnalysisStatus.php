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
    case Unsent = 'unsent';
    case PendingInitialAnalysis = 'pending-initial-analysis';
    case PendingReanalysis = 'pending-reanalysis';
    case Processing = 'processing';
    case AnalyzedClean = 'analyzed-clean';
    case AnalyzedFlagged = 'analyzed-flagged';
    case AnalyzedError = 'analyzed-error';
    case Unknown = 'unknown';
    case ApiError = 'api-error';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Unsent => Plugin::t('Pending'),
            self::PendingInitialAnalysis => Plugin::t('Pending Initial Analysis'),
            self::PendingReanalysis => Plugin::t('Pending Reanalysis'),
            self::Processing => Plugin::t('Processing'),
            self::AnalyzedClean => Plugin::t('Clean'),
            self::AnalyzedFlagged => Plugin::t('Flagged'),
            self::AnalyzedError => Plugin::t('Error'),
            self::ApiError => Plugin::t('API Error'),
            self::Archived => Plugin::t('Archived'),
            default => Plugin::t('Unknown'),
        };
    }

    public function color(): Color
    {
        return match ($this) {
            self::Unsent => Color::Orange,
            self::Processing => Color::Purple,
            self::PendingInitialAnalysis => Color::Purple,
            self::PendingReanalysis => Color::Purple,
            self::AnalyzedClean => Color::Teal,
            self::AnalyzedFlagged => Color::Amber,
            self::AnalyzedError => Color::Red,
            self::ApiError => Color::Red,
            self::Archived => Color::Gray,
            default => Color::Gray,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Unsent => 'envelope',
            self::Processing => 'hammer',
            self::PendingInitialAnalysis => 'clock',
            self::PendingReanalysis => 'clock-rotate-left',
            self::AnalyzedClean => 'check',
            self::AnalyzedFlagged => 'flag',
            self::AnalyzedError, self::ApiError => 'triangle-exclamation',
            self::Archived => 'file',
            default => 'question',
        };
    }
}
