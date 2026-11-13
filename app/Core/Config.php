<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Dot-notation access to the arrays returned by the files in /config,
 * plus a tiny .env reader. Values are loaded once and cached in memory.
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    private static bool $envLoaded = false;

    public static function loadEnv(string $path): void
    {
        if (self::$envLoaded) {
            return;
        }
        self::$envLoaded = true;

        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Strip matching surrounding quotes so values may contain spaces or '#'.
            $len = strlen($value);
            if ($len >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[$len - 1] === $value[0]) {
                $value = substr($value, 1, -1);
            } else {
                // An unquoted value ends at an inline comment. Without this,
                // "APP_DEBUG=false   # never in production" is the string
                // "false   # never…", and PAYPAL_MODE=live with a comment
                // silently stays on the sandbox API.
                $value = rtrim((string) preg_replace('/\s+#.*$/', '', $value));
            }

            if ($key !== '' && getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }

    /**
     * Read an environment variable, casting the usual literals to real types.
     */
    public static function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }

        return match (strtolower($value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            ''                 => $default,
            default            => $value,
        };
    }

    public static function load(string $configDir): void
    {
        foreach (glob(rtrim($configDir, '/\\') . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            self::$items[$name] = require $file;
        }
    }

    /**
     * Config::get('app.name') / Config::get('paypal.mode', 'sandbox')
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref = &self::$items;

        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }

        $ref = $value;
    }
}
