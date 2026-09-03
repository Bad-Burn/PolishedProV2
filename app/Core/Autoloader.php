<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal PSR-4 autoloader. The project deliberately ships without Composer so
 * it can be dropped onto any shared host with nothing but PHP 8.1+.
 */
final class Autoloader
{
    /** @var array<string, string> namespace prefix => base directory */
    private static array $prefixes = [];

    public static function register(): void
    {
        spl_autoload_register([self::class, 'load'], true, true);
    }

    public static function addNamespace(string $prefix, string $baseDir): void
    {
        self::$prefixes[trim($prefix, '\\') . '\\'] = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;
    }

    private static function load(string $class): void
    {
        foreach (self::$prefixes as $prefix => $baseDir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

            if (is_file($file)) {
                require $file;
                return;
            }
        }
    }
}
