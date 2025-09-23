<?php

/**
 * Neverstale Configuration
 *
 * Copy this file to `config/neverstale.php` to override plugin settings.
 * You can also use environment variables for most settings.
 *
 * Config file overrides take precedence over environment variables,
 * which take precedence over settings configured in the Craft CP.
 */

return [
    // API Configuration
    'apiKey' => '$NEVERSTALE_API_KEY',
    'webhookSecret' => '$NEVERSTALE_WEBHOOK_SECRET',

    // Webhook Configuration
    'webhookDomain' => '$NEVERSTALE_WEBHOOK_DOMAIN', // e.g., 'https://webhook.example.com'

    // Logging Configuration
    'debugLogging' => '$NEVERSTALE_DEBUG_LOGGING', // true/false

    // Bulk Ingest Configuration
    'bulkIngestEnabled' => '$NEVERSTALE_BULK_INGEST_ENABLED', // true/false
    'bulkIngestMaxItems' => '$NEVERSTALE_BULK_INGEST_MAX_ITEMS', // default: 10000
    'bulkIngestBatchSize' => '$NEVERSTALE_BULK_INGEST_BATCH_SIZE', // default: 100
    'bulkIngestMaxConcurrency' => '$NEVERSTALE_BULK_INGEST_MAX_CONCURRENCY', // default: 3
    'bulkIngestRetryAttempts' => '$NEVERSTALE_BULK_INGEST_RETRY_ATTEMPTS', // default: 3
    'bulkIngestTimeoutMinutes' => '$NEVERSTALE_BULK_INGEST_TIMEOUT_MINUTES', // default: 60

    // Section Configuration (callable example)
    'sections' => function (\craft\models\Section $section): bool {
        // Only include sections with specific handles
        return in_array($section->handle, ['blog', 'pages']);
    },

    // Custom Content Transformer (optional)
    'transformer' => function (array $data): array {
        // Custom transformation logic here
        return $data;
    },
];