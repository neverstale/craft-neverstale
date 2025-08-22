<?php

namespace neverstale\neverstale\registrars;

/**
 * Interface for plugin registrars
 *
 * Registrars handle specific aspects of plugin initialization,
 * following the Single Responsibility Principle.
 */
interface RegistrarInterface
{
    public function register(): void;
}
