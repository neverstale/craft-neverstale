<?php

namespace zaengle\neverstale\errors;

use yii\base\Exception;

/**
 * Neverstale Base Exception
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
class BaseNeverstaleException extends Exception
{
    public string $name = 'Neverstale Exception';
}
