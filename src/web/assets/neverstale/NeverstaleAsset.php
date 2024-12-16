<?php

namespace zaengle\neverstale\web\assets\neverstale;

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
        $this->sourcePath = '@zaengle/neverstale/web/assets/neverstale/dist';

        $this->depends = [
            CpAsset::class,
        ];
    }
}
