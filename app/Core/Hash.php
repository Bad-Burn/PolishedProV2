<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Password hashing with Argon2id where available, bcrypt elsewhere.
 *
 * Hashes are rehashed transparently on login when the algorithm or cost
 * changes, so upgrading a host's PHP silently upgrades stored passwords.
 */
final class Hash
{
    public static function make(string $plain): string
    {
        [$algo, $options] = self::algorithm();
        $hash = password_hash($plain, $algo, $options);

        if (!is_string($hash) || $hash === '') {
            throw new \RuntimeException('Password hashing failed.');
        }

        return $hash;
    }

    public static function verify(string $plain, string $hash): bool
    {
        if ($hash === '') {
            // Still burn the time so a missing hash is not distinguishable by timing.
            password_verify($plain, '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
            return false;
        }

        return password_verify($plain, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        [$algo, $options] = self::algorithm();
        return password_needs_rehash($hash, $algo, $options);
    }

    /** @return array{0: string|int, 1: array<string, int>} */
    private static function algorithm(): array
    {
        if (defined('PASSWORD_ARGON2ID') && in_array('argon2id', password_algos(), true)) {
            return [PASSWORD_ARGON2ID, [
                'memory_cost' => 65536, // 64 MB
                'time_cost'   => 4,
                'threads'     => 2,
            ]];
        }

        return [PASSWORD_BCRYPT, ['cost' => 12]];
    }

    /** URL-safe random token for password resets, email verification, etc. */
    public static function randomToken(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    /** Store only the hash of a token so a database leak does not expose usable links. */
    public static function hmac(string $value): string
    {
        return hash_hmac('sha256', $value, (string) Config::get('app.key', 'polishedpro'));
    }
}
