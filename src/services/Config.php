<?php

namespace neverstale\neverstale\services;

use yii\base\Component;

class Config extends Component
{
    private ?array $fileConfig = null;

    public function get(?string $key = null): mixed
    {
        if (!$this->fileConfig) {
            $this->fileConfig = \Craft::$app->config->getConfigFromFile('neverstale');
        }

        if ($key) {
            return $this->fileConfig[$key] ?? null;
        }

        return $this->fileConfig;
    }

    public function isOverriddenByFile(string $key): bool
    {
        return array_key_exists($key, $this->get());
    }
}
