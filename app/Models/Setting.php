<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Key/value platform settings the admin can edit without touching code.
 * Read once per request and cached in memory.
 */
final class Setting
{
    /** @var array<string, string|null>|null */
    private static ?array $cache = null;

    /** @return array<string, string|null> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $settings = [];

        try {
            foreach (Database::select('SELECT setting_key, setting_value FROM settings') as $row) {
                $settings[(string) $row['setting_key']] = $row['setting_value'];
            }
        } catch (\Throwable) {
            // Before the first migration there is no table — fall back to defaults.
        }

        return self::$cache = $settings;
    }

    public static function get(string $key, string $default = ''): string
    {
        $value = self::all()[$key] ?? null;
        return $value === null || $value === '' ? $default : (string) $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::all()[$key] ?? null;
        return $value === null ? $default : in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::all()[$key] ?? null;
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function put(string $key, string $value): void
    {
        Database::affectingStatement(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            ['k' => $key, 'v' => $value]
        );

        self::$cache = null;
    }

    /** @param array<string, string> $values */
    public static function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            self::put($key, $value);
        }
    }
}
