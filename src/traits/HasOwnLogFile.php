<?php

namespace neverstale\neverstale\traits;

use Craft;
use craft\base\Plugin;
use craft\log\MonologTarget;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LogLevel;
use yii\log\Logger;

/**
 * Trait HasOwnLogFile
 *
 * Allow a module to log messages to its own log file.
 *
 * @method static Plugin getInstance()
 */
trait HasOwnLogFile
{
    /**
     * Logs a debug message to our custom log target (only if debug logging is enabled).
     */
    public static function debug(string|array $message): void
    {
        // Only log debug messages if debug logging is enabled
        if (! self::getInstance()->getSettings()->debugLogging) {
            return;
        }

        self::logMessage($message, 'DEBUG', fn($msg, $category) => Craft::debug($msg, $category));
    }

    /**
     * Logs an informational message to our custom log target.
     */
    public static function info(string|array $message): void
    {
        self::logMessage($message, 'INFO', fn($msg, $category) => Craft::info($msg, $category));
    }

    /**
     * Logs a warning message to our custom log target.
     */
    public static function warning(string|array $message): void
    {
        self::logMessage($message, 'WARNING', fn($msg, $category) => Craft::warning($msg, $category));
    }

    /**
     * Logs an error message to our custom log target.
     */
    public static function error(string|array $message): void
    {
        self::logMessage($message, 'ERROR', fn($msg, $category) => Craft::error($msg, $category));
    }

    /**
     * Helper method to format and log messages
     *
     * @param string|array $message The message to log
     * @param string $level The log level (DEBUG, INFO, WARNING, ERROR)
     * @param callable $craftMethod The Craft logging method to use
     */
    private static function logMessage(string|array $message, string $level, callable $craftMethod): void
    {
        $messageStr = is_array($message) ? json_encode($message) : $message;
        $formattedMessage = "[{$level}] {$messageStr}";
        $category = self::getInstance()->getHandle();

        $craftMethod($formattedMessage, $category);
    }

    /**
     * Write log messages to a custom log target
     */
    protected function registerLogTarget(): void
    {
        Craft::getLogger()->dispatcher->targets[self::getInstance()->getHandle()] = new MonologTarget([
            'name' => self::getInstance()->getHandle(),
            'categories' => [self::getInstance()->getHandle()],
            'level' =>LogLevel::DEBUG,
            'logContext' => false,
            'allowLineBreaks' => false,
            'formatter' => new LineFormatter(
                format: "%datetime% %message%\n",
                dateFormat: 'Y-m-d H:i:s',
            ),
        ]);
    }
}
