# Neverstale plugin for Craft CMS

> Note, this plugin requires an active account on https://nevertale.io 

## Requirements

This plugin requires Craft CMS 5.4.0 or later, and PHP 8.2 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

```shell
composer require neverstale/craft
craft plugin/install neverstale
```

## Configuration

After installing the plugin, go to the plugin dashboard page in the Craft control panel to configure it.

All plugin settings can also be set via in the `config/nevertale.php` file. Settings in the config file will take precedence over those set via the Control Panel.

## Usage

See the full documentation at https://neverstale.io/docs/integrations/craft-cms

## Plugin development

The plugin uses Vite for front end development. To get started:

```bash
npm install
npm run dev # runs Vite devServer
npm run build # builds for production
```

Tailwind CSS is used for styling using a class prefix of `ns-`.
