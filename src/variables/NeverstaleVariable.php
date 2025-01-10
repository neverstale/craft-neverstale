<?php

namespace zaengle\neverstale\variables;

use nystudio107\pluginvite\variables\ViteVariableInterface;
use nystudio107\pluginvite\variables\ViteVariableTrait;
use zaengle\neverstale\models\Settings;
use zaengle\neverstale\services\Config;
use zaengle\neverstale\services\Format;
use zaengle\neverstale\services\Setup;

class NeverstaleVariable implements ViteVariableInterface
{
    use ViteVariableTrait;

    public ?Config $config = null;
    public ?Settings $settings = null;
    public ?Setup $setup = null;
    public ?Format $format = null;
}
