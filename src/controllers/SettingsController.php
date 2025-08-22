<?php

namespace neverstale\neverstale\controllers;

use Craft;
use craft\helpers\App;
use craft\web\Controller;
use Exception;
use GuzzleHttp\Client;
use neverstale\neverstale\Plugin;
use yii\web\Response;

/**
 * Settings controller
 */
class SettingsController extends Controller
{
    public function actionEdit(): Response
    {
        $plugin = Plugin::getInstance();
        $settings = $plugin->getSettings();

        return $this->renderTemplate('neverstale/_settings.twig', [
            'plugin' => $plugin,
            'settings' => $settings,
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $plugin = Plugin::getInstance();
        $settings = $plugin->getSettings();

        $requestData = Craft::$app->getRequest()->getBodyParam('settings', []);

        // Debug logging to understand what's being submitted
        Plugin::info("Settings form submission - enabledSectionIds: ".json_encode($requestData['enabledSectionIds'] ?? 'not set'));
        Plugin::info("Settings form submission - allowAllSections: ".json_encode($requestData['allowAllSections'] ?? 'not set'));

        if (isset($requestData['enabledSectionIds']) && $requestData['enabledSectionIds'] === '*') {
            // If enabledSectionIds is set to '*', we need to convert it to an empty array
            // to indicate that all sections are enabled.
            $requestData['enabledSectionIds'] = [];
            $requestData['allowAllSections'] = true;
        } elseif (isset($requestData['enabledSectionIds'])) {
            // If specific sections are selected, ensure allowAllSections is false
            $requestData['allowAllSections'] = false;
        } else {
            // If no enabledSectionIds provided, default to no sections enabled
            $requestData['enabledSectionIds'] = [];
            $requestData['allowAllSections'] = false;
        }

        $settings->setAttributes($requestData, false);

        if (! $settings->validate()) {
            Craft::$app->getSession()->setError('Couldn\'t save plugin settings.');

            return $this->renderTemplate('neverstale/_settings.twig', [
                'plugin' => $plugin,
                'settings' => $settings,
            ]);
        }

        $settingsArray = $settings->toArray();

        if (! Craft::$app->getPlugins()->savePluginSettings($plugin, $settingsArray)) {
            Craft::$app->getSession()->setError('Couldn\'t save plugin settings.');

            return $this->renderTemplate('neverstale/_settings.twig', [
                'plugin' => $plugin,
                'settings' => $settings,
            ]);
        }

        Craft::$app->getSession()->setNotice('Plugin settings saved.');

        return $this->redirectToPostedUrl();
    }

    public function actionTestConnection(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $apiKey = $request->getBodyParam('apiKey');
        $webhookSecret = $request->getBodyParam('webhookSecret');

        if (! $apiKey || ! $webhookSecret) {
            return $this->asJson([
                'success' => false,
                'error' => 'API Key and Webhook Secret are required.',
            ]);
        }

        try {
            // Get API endpoint from environment or use default
            $apiEndpoint = App::parseEnv('$NEVERSTALE_API_BASE_URI') ?: 'https://api.neverstale.com';

            $testUrl = rtrim($apiEndpoint, '/').'/health';

            // Test the connection to Neverstale API
            $client = new Client([
                'timeout' => 10,
                'headers' => [
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                ],
            ]);

            // Make a simple API call to test connectivity
            $response = $client->get($testUrl, [
                'query' => ['webhook_secret' => $webhookSecret],
            ]);

            if ($response->getStatusCode() === 200) {
                return $this->asJson(['success' => true]);
            } else {
                return $this->asJson([
                    'success' => false,
                    'error' => 'Invalid API credentials or service unavailable.',
                ]);
            }

        } catch (Exception $e) {
            return $this->asJson([
                'success' => false,
                'error' => 'Connection failed: '.$e->getMessage(),
            ]);
        }
    }
}
