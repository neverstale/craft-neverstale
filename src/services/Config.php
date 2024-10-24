<?php

namespace zaengle\neverstale\services;

use Craft;
use yii\base\Component;
/**
 * Neverstale Config Service
 *
 * Handles access to the Neverstale config file settings / determining if a
 * plugin CP setting has been overridden by the config file
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 *
 * @property-read array $configFile
 */
class Config extends Component
{
    /**
     * @property $fileConfig array<string,mixed>|null
     */
    private ?array $fileConfig = null;

    /**
     * @return array<string, mixed>
     */
    public function getConfigFile(?string $key = null): mixed
    {
        if (!$this->fileConfig) {
            $this->fileConfig = Craft::$app->config->getConfigFromFile('neverstale');
        }

        if ($key) {
            return $this->fileConfig[$key] ?? null;
        }

        return $this->fileConfig;
    }

    public function isOverriddenByFile(string $key): bool
    {
        return array_key_exists($key, $this->getConfigFile());
    }
}
