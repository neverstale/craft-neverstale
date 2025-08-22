<?php

namespace neverstale\neverstale\models;

use craft\base\Model;
use craft\enums\Color;
use Illuminate\Support\Collection;
use neverstale\neverstale\enums\AnalysisStatus;
use neverstale\neverstale\Plugin;

/**
 * Status model
 *
 * Wraps AnalysisStatus with Craft specific properties for use in the CP
 *
 * @property-read Color  $color
 * @property-read string $icon
 * @property-read string $label
 * @property-read string $value
 */
class Status extends Model
{
    public AnalysisStatus $analysisStatus;

    /**
     * Returns all statuses
     *
     * @return Collection<Status>
     */
    public static function all(): Collection
    {
        return collect(AnalysisStatus::cases())->map(fn($status) => self::from($status));
    }

    public static function from(AnalysisStatus|string $status): self
    {
        $analysisStatus = is_string($status) ? AnalysisStatus::from($status) : $status;

        $model = new self();
        $model->analysisStatus = $analysisStatus;

        return $model;
    }

    public function getValue(): string
    {
        return $this->analysisStatus->value;
    }

    public function getLabel(): string
    {
        return match ($this->analysisStatus) {
            AnalysisStatus::UNSENT, AnalysisStatus::STALE => Plugin::t('Pending'),
            AnalysisStatus::PENDING_INITIAL_ANALYSIS => Plugin::t('Pending Initial Analysis'),
            AnalysisStatus::PENDING_REANALYSIS => Plugin::t('Pending Reanalysis'),
            AnalysisStatus::PROCESSING_REANALYSIS, AnalysisStatus::PROCESSING_INITIAL_ANALYSIS => Plugin::t('Processing'),
            AnalysisStatus::ANALYZED_CLEAN => Plugin::t('Clean'),
            AnalysisStatus::ANALYZED_FLAGGED => Plugin::t('Flagged'),
            AnalysisStatus::ANALYZED_ERROR => Plugin::t('Error'),
            AnalysisStatus::API_ERROR => Plugin::t('API Error'),
            AnalysisStatus::ARCHIVED => Plugin::t('Archived'),
            default => $this->analysisStatus->label(),
        };
    }

    public function getColor(): Color
    {
        return match ($this->analysisStatus) {
            AnalysisStatus::UNSENT, AnalysisStatus::STALE => Color::Orange,
            AnalysisStatus::PENDING_INITIAL_ANALYSIS, AnalysisStatus::PENDING_REANALYSIS => Color::Pink,
            AnalysisStatus::PROCESSING_REANALYSIS, AnalysisStatus::PROCESSING_INITIAL_ANALYSIS => Color::Purple,
            AnalysisStatus::ANALYZED_CLEAN => Color::Teal,
            AnalysisStatus::ANALYZED_FLAGGED => Color::Red,
            AnalysisStatus::ANALYZED_ERROR => Color::Red,
            AnalysisStatus::API_ERROR => Color::Red,
            AnalysisStatus::ARCHIVED => Color::Gray,
            default => Color::Gray,
        };
    }

    public function getIcon(): string
    {
        return match ($this->analysisStatus) {
            AnalysisStatus::UNSENT, AnalysisStatus::STALE => 'envelope',
            AnalysisStatus::PROCESSING_REANALYSIS => 'hammer',
            AnalysisStatus::PROCESSING_INITIAL_ANALYSIS => 'hammer',
            AnalysisStatus::PENDING_INITIAL_ANALYSIS => 'clock',
            AnalysisStatus::PENDING_REANALYSIS => 'clock-rotate-left',
            AnalysisStatus::ANALYZED_CLEAN => 'check',
            AnalysisStatus::ANALYZED_FLAGGED => 'flag',
            AnalysisStatus::ANALYZED_ERROR, AnalysisStatus::API_ERROR => 'triangle-exclamation',
            AnalysisStatus::ARCHIVED => 'file',
            default => 'question',
        };
    }

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['analysisStatus'], 'required'],
        ]);
    }
}
