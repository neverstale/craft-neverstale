<?php

namespace neverstale\neverstale\assets;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class NeverstaleAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@neverstale/neverstale/resources';

        $this->depends = [
            CpAsset::class,
        ];

        $this->css = [
            'css/neverstale.css',
        ];

        $this->js = [
            'js/neverstale.js',
        ];

        parent::init();
    }
}
