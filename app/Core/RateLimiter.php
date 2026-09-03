<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Database-backed fixed-window rate limiter.
 *
 * Used to throttle login, registration, password reset and booking creation.
 * Keys are hashed so the table never stores raw email addresses or IPs.
 */
final class RateLimiter
{
    /**
     * @return bool true when the caller is over the limit
     */
    public static function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        return self::attempts($key, $decaySeconds) >= $maxAttempts;
    }

    public static function attempts(string $key, int $decaySeconds): int
    {
        self::purgeOccasionally();

        $row = Database::selectOne(
            'SELECT attempts, expires_at FROM rate_limits WHERE limit_key = :k LIMIT 1',
            ['k' => self::hash($key)]
        );

        if ($row === null) {
            return 0;
        }

        if (strtotime((string) $row['expires_at']) <= time()) {
            self::clear($key);
            return 0;
        }

        return (int) $row['attempts'];
    }

    /** Record one attempt and return the running total for the window. */
    public static function hit(string $key, int $decaySeconds): int
    {
        $hashed  = self::hash($key);
        $expires = date('Y-m-d H:i:s', time() + $decaySeconds);

        $existing = Database::selectOne(
            'SELECT attempts, expires_at FROM rate_limits WHERE limit_key = :k LIMIT 1',
            ['k' => $hashed]
        );

        if ($existing === null || strtotime((string) $existing['expires_at']) <= time()) {
            Database::affectingStatement(
                'DELETE FROM rate_limits WHERE limit_key = :k',
                ['k' => $hashed]
            );
            Database::insert(
                'INSERT INTO rate_limits (limit_key, attempts, expires_at) VALUES (:k, 1, :e)',
                ['k' => $hashed, 'e' => $expires]
            );
            return 1;
        }

        Database::affectingStatement(
            'UPDATE rate_limits SET attempts = attempts + 1 WHERE limit_key = :k',
            ['k' => $hashed]
        );

        return (int) $existing['attempts'] + 1;
    }

    /** Seconds until the current window resets. */
    public static function availableIn(string $key): int
    {
        $row = Database::selectOne(
            'SELECT expires_at FROM rate_limits WHERE limit_key = :k LIMIT 1',
            ['k' => self::hash($key)]
        );

        if ($row === null) {
            return 0;
        }

        return max(0, strtotime((string) $row['expires_at']) - time());
    }

    public static function clear(string $key): void
    {
        Database::affectingStatement(
            'DELETE FROM rate_limits WHERE limit_key = :k',
            ['k' => self::hash($key)]
        );
    }

    private static function hash(string $key): string
    {
        return hash_hmac('sha256', $key, (string) Config::get('app.key', 'polishedpro'));
    }

    /** Cheap probabilistic cleanup so expired rows do not accumulate. */
    private static function purgeOccasionally(): void
    {
        if (random_int(1, 50) !== 1) {
            return;
        }

        try {
            Database::affectingStatement('DELETE FROM rate_limits WHERE expires_at < :now', [
                'now' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Cleanup is best-effort and must never break a request.
        }
    }
}
