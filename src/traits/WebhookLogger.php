<?php

namespace neverstale\neverstale\traits;

use Craft;
use craft\log\MonologTarget;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LogLevel;

/**
 * Trait WebhookLogger
 *
 * Provides dedicated webhook logging functionality with separate log files.
 */
trait WebhookLogger
{
    private static ?MonologTarget $webhookLogTarget = null;

    public const NEVERSTALE_WEBHOOK = 'neverstale-webhook';

    /**
     * Log a webhook debug message
     */
    public static function webhookDebug(string $message): void
    {
        self::ensureWebhookLogTarget();
        Craft::debug($message, self::NEVERSTALE_WEBHOOK);
    }

    /**
     * Log a webhook info message
     */
    public static function webhookInfo(string $message): void
    {
        self::ensureWebhookLogTarget();
        Craft::info($message, self::NEVERSTALE_WEBHOOK);
    }

    /**
     * Log a webhook warning message
     */
    public static function webhookWarning(string $message): void
    {
        self::ensureWebhookLogTarget();
        Craft::warning($message, self::NEVERSTALE_WEBHOOK);
    }

    /**
     * Log a webhook error message
     */
    public static function webhookError(string $message): void
    {
        self::ensureWebhookLogTarget();
        Craft::error($message, self::NEVERSTALE_WEBHOOK);
    }

    /**
     * Ensure webhook log target is registered
     */
    private static function ensureWebhookLogTarget(): void
    {
        if (self::$webhookLogTarget !== null) {
            return;
        }
        
        self::$webhookLogTarget = new MonologTarget([
            'name' => self::NEVERSTALE_WEBHOOK,
            'allowLineBreaks' => false,
            'categories' => [ self::NEVERSTALE_WEBHOOK ],
            'level' => LogLevel::DEBUG,
            'logContext' => false,
            'formatter' => new LineFormatter(
                format: "%datetime% [%level_name%] %message%\n",
                dateFormat: 'Y-m-d H:i:s',
                ignoreEmptyContextAndExtra: true,
            ),
        ]);

        Craft::getLogger()->dispatcher->targets[self::NEVERSTALE_WEBHOOK] = self::$webhookLogTarget;
    }
}
