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

    /**
     * Log a webhook debug message
     */
    public static function webhookDebug(string $message): void
    {
        self::logWebhookMessage($message, 'DEBUG');
    }

    /**
     * Log a webhook info message
     */
    public static function webhookInfo(string $message): void
    {
        self::logWebhookMessage($message, 'INFO');
    }

    /**
     * Log a webhook warning message
     */
    public static function webhookWarning(string $message): void
    {
        self::logWebhookMessage($message, 'WARNING');
    }

    /**
     * Log a webhook error message
     */
    public static function webhookError(string $message): void
    {
        self::logWebhookMessage($message, 'ERROR');
    }

    /**
     * Log message specifically for webhook operations
     */
    private static function logWebhookMessage(string $message, string $level): void
    {
        self::ensureWebhookLogTarget();
        
        $formattedMessage = "[{$level}] {$message}";
        $category = 'neverstale-webhook';

        // Map log levels to Craft logging methods
        match ($level) {
            'DEBUG' => Craft::debug($formattedMessage, $category),
            'INFO' => Craft::info($formattedMessage, $category),
            'WARNING' => Craft::warning($formattedMessage, $category),
            'ERROR' => Craft::error($formattedMessage, $category),
            default => Craft::info($formattedMessage, $category),
        };
    }

    /**
     * Ensure webhook log target is registered
     */
    private static function ensureWebhookLogTarget(): void
    {
        if (self::$webhookLogTarget !== null) {
            return;
        }

        $date = date('Y-m-d');
        $logFileName = "neverstale-webhook-{$date}";
        
        self::$webhookLogTarget = new MonologTarget([
            'name' => $logFileName,
            'categories' => ['neverstale-webhook'],
            'level' => LogLevel::DEBUG,
            'logContext' => false,
            'allowLineBreaks' => false,
            'formatter' => new LineFormatter(
                format: "%datetime% %message%\n",
                dateFormat: 'Y-m-d H:i:s',
            ),
        ]);

        Craft::getLogger()->dispatcher->targets[$logFileName] = self::$webhookLogTarget;
    }
}