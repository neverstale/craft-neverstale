<?php

namespace zaengle\neverstale\web\twig;

use Craft;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;
use zaengle\neverstale\enums\AnalysisStatus;

/**
 * Twig extension
 */
class Neverstale extends AbstractExtension
{
    public function getFilters(): array
    {
        return [];
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('neverstaleToAnalysisStatus', fn (string $status): ?AnalysisStatus  => AnalysisStatus::tryFrom($status)),
        ];
    }

    public function getTests(): array
    {
        return [];
    }
}
