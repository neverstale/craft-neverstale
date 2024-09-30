<?php

namespace zaengle\neverstale\enums;

use Craft;

enum SubmissionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Flagged = 'flagged';
    case Clean = 'clean';

    // get the color of the status
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'fb923c', // --orange-400
            self::Processing => '6d28d9', // --violet-700
            self::Clean => '11a697', // --teal-550
            self::Flagged => 'dc2626', // --red-600
        };
    }

    // get the label of the status
    public function label(): string
    {
        return match ($this) {
            self::Pending => Craft::t('neverstale','Pending'),
            self::Processing => Craft::t('neverstale','Processing'),
            self::Clean => Craft::t('neverstale','Clean'),
            self::Flagged => Craft::t('neverstale','Flagged'),
        };
    }
}
