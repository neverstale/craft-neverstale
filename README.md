# NeverStale

NeverStale <=> Craft integration

## Requirements

This plugin requires Craft CMS 5.4.0 or later, and PHP 8.2 or later.

## FE dev

Assets reside in `src/web/assets/neverstale/src`. FE build uses Vite.

One time setup

```bash
cp .env.example .env
# from host craft install
ddev npm --prefix path/to/plugin/dir install
```

daily dev

```bash
ddev npm --prefix path/to/plugin/dir run dev
```

From the craft-test-content project you can use the ddev command `ddev nsv` (NeverStaleVite) to run commands in the plugin directory.
