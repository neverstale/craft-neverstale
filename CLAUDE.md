# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Craft CMS plugin called "Neverstale" that integrates with the Neverstale API to find and manage stale content in Craft CMS sites. The plugin is developed by Zaengle and provides content freshness analysis and flagging capabilities.

## Architecture

### Core Components

- **Plugin Entry Point**: `src/Plugin.php` - Main plugin class that registers services, behaviors, and event handlers
- **Services**: Located in `src/services/` - Core business logic including Content, Flag, Entry, TransactionLog, and API integration
- **Elements**: `src/elements/NeverstaleContent.php` - Custom Craft element for tracking analyzed content
- **Models**: `src/models/` - Data models including Settings, Status, and TransactionLogItem
- **Controllers**: `src/controllers/` - Handle CP requests for dashboard, content management, and webhooks
- **Jobs**: `src/jobs/` - Background processing for content ingestion and scanning
- **Widgets**: `src/widgets/` - Dashboard widgets for connection status and flagged content

### Frontend Architecture

- **Asset Bundle**: `src/web/assets/neverstale/NeverstaleAsset.php` - Manages frontend assets
- **Vue.js Components**: `src/web/assets/neverstale/src/components/` - Interactive UI components
- **TypeScript**: Frontend logic in `src/web/assets/neverstale/src/`
- **CSS/Tailwind**: Styled with Tailwind CSS using `ns-` prefix for classes

### Key Integrations

- **Craft CMS Events**: Registers handlers for entry save/delete, CP navigation, table attributes
- **API Client**: `src/support/ApiClient.php` integrates with external Neverstale API
- **Vite**: Frontend build system with dev server on port 3333
- **Twig Extensions**: `src/web/twig/Neverstale.php` provides template functions

## Development Commands

### Frontend Development
```bash
npm install                 # Install dependencies
npm run dev                 # Start Vite dev server (port 3333)
npm run build              # Build for production
npm run eslint             # Run ESLint with auto-fix
```

### PHP Development
```bash
composer check-cs          # Check coding standards with ECS
composer fix-cs            # Fix coding standards automatically
composer phpstan           # Run PHPStan static analysis (level 4)
```

### Plugin Installation
```bash
composer require neverstale/craft
craft plugin/install neverstale
```

## Configuration

- **Settings Model**: `src/models/Settings.php` defines plugin configuration
- **Config File**: Plugin copies `config.example.php` to `config/neverstale.php` on install
- **Environment Variables**: Uses `NEVERSTALE_API_BASE_URI` and API key from settings

## Key Behavioral Patterns

### Content Processing Flow
1. Entry save triggers `EVENT_AFTER_SAVE` handler in `Plugin.php:340`
2. `EntryService::shouldIngest()` determines if entry should be processed
3. Content is queued via `ContentService::queue()` for background processing
4. `IngestContentJob` processes the content asynchronously

### Element Integration
- Extends Craft entries with `HasNeverstaleContentBehavior`
- Adds custom table attributes for status, analysis dates, and flag counts
- Provides sidebar content in entry edit pages

### API Integration
- All external API calls go through `ApiClient` in `src/support/`
- Services like `Content` and `Flag` receive client injection
- Transaction logging tracks all API interactions

## Testing and Quality

- **ECS**: Easy Coding Standard with Craft CMS 4 ruleset
- **PHPStan**: Static analysis at level 4
- **ESLint**: Frontend linting with Vue.js and TypeScript support
- **Vite**: Modern build tooling with TypeScript compilation

## File Structure Notes

- `src/enums/` - Contains typed enumerations for status values and permissions
- `src/traits/` - Shared functionality like logging and entry relationships  
- `src/utilities/` - Craft CP utilities for content preview
- `src/templates/` - Twig templates for CP interface
- `src/migrations/` - Database migrations for plugin installation