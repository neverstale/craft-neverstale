<?php

namespace neverstale\craft\web\assets\neverstale;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Neverstale asset bundle
 */
class NeverstaleAsset extends AssetBundle
{
    public function init()
    {
        parent::init();
        $this->sourcePath = '@neverstale/craft/web/assets/neverstale/dist';

        $this->depends = [
            CpAsset::class,
        ];
    }
}
