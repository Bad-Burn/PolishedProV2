<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * "Stay signed in" cookies, done safely.
 *
 * The cookie holds `selector:validator`. Only the selector is stored in the
 * clear; the validator is kept as a SHA-256 hash and compared with hash_equals.
 * Tokens are single-use — every successful match rotates the validator — so a
 * stolen cookie stops working as soon as the real owner returns, and the reuse
 * is detectable.
 */
final class RememberToken
{
    private const COOKIE = 'polishedpro_remember';

    public static function issue(int $userId): void
    {
        $selector  = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(32));
        $days      = (int) Config::get('app.session.remember_days', 30);
        $expires   = time() + ($days * 86400);

        Database::insert(
            'INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at, created_at)
             VALUES (:u, :s, :v, :e, :c)',
            [
                'u' => $userId,
                's' => $selector,
                'v' => hash('sha256', $validator),
                'e' => date('Y-m-d H:i:s', $expires),
                'c' => date('Y-m-d H:i:s'),
            ]
        );

        self::writeCookie($selector . ':' . $validator, $expires);
    }

    /** @return array<string, mixed>|null the user this cookie authenticates */
    public static function resolve(): ?array
    {
        $raw = $_COOKIE[self::COOKIE] ?? null;
        if (!is_string($raw) || !str_contains($raw, ':')) {
            return null;
        }

        [$selector, $validator] = explode(':', $raw, 2);
        if ($selector === '' || $validator === '') {
            self::forgetCookie();
            return null;
        }

        $row = Database::selectOne(
            'SELECT * FROM remember_tokens WHERE selector = :s LIMIT 1',
            ['s' => $selector]
        );

        if ($row === null) {
            self::forgetCookie();
            return null;
        }

        if (strtotime((string) $row['expires_at']) <= time()) {
            self::deleteBySelector($selector);
            self::forgetCookie();
            return null;
        }

        if (!hash_equals((string) $row['validator_hash'], hash('sha256', $validator))) {
            // Right selector, wrong validator: the token was guessed or replayed.
            // Drop every token for that account and make them sign in again.
            Logger::security('Remember-me validator mismatch — all tokens revoked', [
                'user_id' => $row['user_id'],
            ]);
            self::clearForUser((int) $row['user_id']);
            self::forgetCookie();
            return null;
        }

        $user = User::find((int) $row['user_id']);
        if ($user === null || $user['status'] !== 'active') {
            self::deleteBySelector($selector);
            self::forgetCookie();
            return null;
        }

        self::rotate($selector, (string) $row['expires_at']);

        return $user;
    }

    private static function rotate(string $selector, string $expiresAt): void
    {
        $validator = bin2hex(random_bytes(32));

        Database::affectingStatement(
            'UPDATE remember_tokens SET validator_hash = :v WHERE selector = :s',
            ['v' => hash('sha256', $validator), 's' => $selector]
        );

        self::writeCookie($selector . ':' . $validator, strtotime($expiresAt) ?: (time() + 86400));
    }

    private static function writeCookie(string $value, int $expires): void
    {
        setcookie(self::COOKIE, $value, [
            'expires'  => $expires,
            'path'     => Url::basePath() === '' ? '/' : Url::basePath() . '/',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function forgetCookie(): void
    {
        if (!isset($_COOKIE[self::COOKIE])) {
            return;
        }
        unset($_COOKIE[self::COOKIE]);
        self::writeCookie('', time() - 42000);
    }

    public static function clearForUser(int $userId): void
    {
        Database::affectingStatement('DELETE FROM remember_tokens WHERE user_id = :u', ['u' => $userId]);
    }

    private static function deleteBySelector(string $selector): void
    {
        Database::affectingStatement('DELETE FROM remember_tokens WHERE selector = :s', ['s' => $selector]);
    }

    public static function purgeExpired(): void
    {
        Database::affectingStatement('DELETE FROM remember_tokens WHERE expires_at < :now', [
            'now' => date('Y-m-d H:i:s'),
        ]);
    }
}
