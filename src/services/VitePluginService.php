<?php

namespace neverstale\craft\services;

use Craft;
use craft\helpers\App;
use nystudio107\pluginvite\helpers\FileHelper;
use nystudio107\pluginvite\services\VitePluginService as BaseVitePluginService;

/**
 * Vite Plugin Service
 *
 * @extends BaseVitePluginService to make it compatible with Vite 5
 */
class VitePluginService extends BaseVitePluginService
{
    public string $manifestFilename = '.vite/manifest.json';

    public function init(): void
    {
        // See if the $pluginDevServerEnvVar env var exists, and if not, don't run off of the dev server
        $useDevServer = (bool)App::env($this->pluginDevServerEnvVar);
        if ($useDevServer === false) {
            $this->useDevServer = false;
        }

        parent::init();
        // If we're in a plugin, make sure the caches are unique
        if ($this->assetClass) {
            $this->cacheKeySuffix = $this->assetClass;
        }
        if ($this->devServerRunning()) {
            $this->invalidateCaches();
        }
        // If we have no asset bundle class, or the dev server is running, don't swap in our `/cpresources/` paths
        if (!$this->assetClass || $this->devServerRunning()) {
            return;
        }
        // The Vite service is generally only needed for CP requests & previews, save a db write, see:
        // https://github.com/nystudio107/craft-plugin-vite/issues/27
        $request = Craft::$app->getRequest();
        if (!$this->useForAllRequests && !$request->getIsConsoleRequest()) {
            if (!$request->getIsCpRequest() && !$request->getIsPreview() && !in_array($request->getSegment(1), $this->firstSegmentRequests, true)) {
                return;
            }
        }
        // Map the $manifestPath and $serverPublic to the hashed `/cpresources/` path & URL for our AssetBundle
        $bundle = new $this->assetClass();
        $baseAssetsUrl = Craft::$app->assetManager->getPublishedUrl(
            $bundle->sourcePath,
            true
        );

        $this->manifestPath = FileHelper::createUrl($bundle->sourcePath, $this->manifestFilename);
        if ($baseAssetsUrl !== false) {
            $this->serverPublic = $baseAssetsUrl;
        }
    }
}
