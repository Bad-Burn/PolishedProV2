<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Append-only file logger. Never echoes anything: in production the user sees a
 * friendly error page while the detail goes to storage/logs.
 */
final class Logger
{
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    /**
     * Security-relevant events (failed logins, lockouts, CSRF rejections,
     * privilege checks) go to their own file so they are easy to review.
     */
    public static function security(string $message, array $context = []): void
    {
        self::write('SECURITY', $message, $context, 'security.log');
    }

    private static function write(string $level, string $message, array $context, string $file = 'app.log'): void
    {
        $dir = PP_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }

        $line = sprintf(
            "[%s] %s: %s%s%s",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES),
            PHP_EOL
        );

        @file_put_contents($dir . DIRECTORY_SEPARATOR . $file, $line, FILE_APPEND | LOCK_EX);
    }
}
