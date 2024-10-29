<?php

namespace zaengle\neverstale\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;
use zaengle\neverstale\Plugin;

/**
 * Base controller
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 *
 * @property-read Plugin $plugin
 *
 */
class BaseController extends Controller
{

    public function getPlugin(): Plugin
    {
        return Plugin::getInstance();
    }
}
