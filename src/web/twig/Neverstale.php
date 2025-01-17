<?php

namespace neverstale\craft\web\twig;

use neverstale\craft\models\Status;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

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
            new TwigFunction('neverstaleToAnalysisStatus', fn(string $status): ?Status => Status::from($status)),
        ];
    }

    public function getTests(): array
    {
        return [];
    }
}
