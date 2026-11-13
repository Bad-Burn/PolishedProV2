<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Url;

/**
 * Proving an account owns its email address.
 *
 * The link carries a signed token rather than a database row: user id, expiry,
 * and an HMAC over both plus the *current* address, keyed with APP_KEY.
 * Changing the email therefore voids every link sent to the old one, with no
 * table to clean up.
 *
 * Booking is the gate. An unverified account can browse and set up, but
 * cannot hold an artist's time — otherwise a throwaway address could fill a
 * calendar with requests nobody will turn up for.
 */
final class EmailVerification
{
    private const TTL = 172800; // 48 hours

    /** @param array<string, mixed> $user */
    public static function isVerified(array $user): bool
    {
        return !empty($user['email_verified_at']);
    }

    /** @param array<string, mixed> $user */
    public static function send(array $user): void
    {
        if (self::isVerified($user)) {
            return;
        }

        Emails::verifyEmail($user, Url::absolute('/verify-email', ['token' => self::token($user)]));
    }

    /**
     * @return int|null the user id the token proves, or null if it is forged,
     *                  expired, or was issued for an address they no longer use
     */
    public static function verify(string $token): ?int
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3 || !ctype_digit($parts[0]) || !ctype_digit($parts[1])) {
            return null;
        }

        [$userId, $expires, $signature] = $parts;

        if ((int) $expires < time()) {
            return null;
        }

        $email = Database::scalar('SELECT email FROM users WHERE id = :u LIMIT 1', ['u' => (int) $userId]);
        if (!is_string($email)) {
            return null;
        }

        if (!hash_equals(self::sign((int) $userId, (int) $expires, $email), $signature)) {
            return null;
        }

        Database::affectingStatement(
            'UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = :u',
            ['u' => (int) $userId]
        );

        return (int) $userId;
    }

    /** @param array<string, mixed> $user */
    private static function token(array $user): string
    {
        $expires = time() + self::TTL;

        return (int) $user['id'] . '.' . $expires . '.' . self::sign((int) $user['id'], $expires, (string) $user['email']);
    }

    private static function sign(int $userId, int $expires, string $email): string
    {
        return hash_hmac('sha256', 'verify-email|' . $userId . '|' . $expires . '|' . strtolower($email), (string) Config::get('app.key'));
    }
}
