<?php

namespace zaengle\neverstale\variables;

use nystudio107\pluginvite\variables\ViteVariableInterface;
use nystudio107\pluginvite\variables\ViteVariableTrait;
use zaengle\neverstale\services\Config as ConfigService;

class NeverstaleVariable implements ViteVariableInterface
{
    use ViteVariableTrait;

    public ?ConfigService $config = null;
}
