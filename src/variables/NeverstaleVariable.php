<?php

namespace neverstale\craft\variables;

use nystudio107\pluginvite\variables\ViteVariableInterface;
use nystudio107\pluginvite\variables\ViteVariableTrait;

use neverstale\craft\models\Settings;
use neverstale\craft\services\Config;
use neverstale\craft\services\Format;
use neverstale\craft\services\Setup;
use neverstale\craft\services\Template;

class NeverstaleVariable implements ViteVariableInterface
{
    use ViteVariableTrait;

    public ?Config $config = null;
    public ?Settings $settings = null;
    public ?Setup $setup = null;
    public ?Format $format = null;
    public ?Template  $template = null;
}
