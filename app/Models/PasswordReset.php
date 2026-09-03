<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;
use App\Core\Hash;

/**
 * Single-use, time-limited password reset tokens.
 *
 * Only a SHA-256 hash of the token is stored, so the database never holds a
 * value that could be turned back into a working reset link.
 */
final class PasswordReset
{
    /** @return string the plain token to put in the emailed link */
    public static function issue(int $userId): string
    {
        // Any earlier link for this account stops working immediately.
        Database::affectingStatement('DELETE FROM password_resets WHERE user_id = :u', ['u' => $userId]);

        $token = Hash::randomToken(32);
        $ttl   = (int) Config::get('app.security.reset_token_ttl', 3600);

        Database::insert(
            'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:u, :t, :e)',
            [
                'u' => $userId,
                't' => hash('sha256', $token),
                'e' => date('Y-m-d H:i:s', time() + $ttl),
            ]
        );

        return $token;
    }

    /** @return array<string, mixed>|null the matching user, when the token is good */
    public static function resolve(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $row = Database::selectOne(
            'SELECT * FROM password_resets WHERE token_hash = :t LIMIT 1',
            ['t' => hash('sha256', $token)]
        );

        if ($row === null || $row['used_at'] !== null) {
            return null;
        }

        if (strtotime((string) $row['expires_at']) <= time()) {
            self::invalidate((int) $row['id']);
            return null;
        }

        $user = User::find((int) $row['user_id']);
        if ($user === null || $user['status'] !== 'active') {
            return null;
        }

        $user['__reset_id'] = (int) $row['id'];

        return $user;
    }

    public static function consume(int $resetId): void
    {
        Database::affectingStatement(
            'UPDATE password_resets SET used_at = NOW() WHERE id = :id',
            ['id' => $resetId]
        );
    }

    public static function invalidate(int $resetId): void
    {
        Database::affectingStatement('DELETE FROM password_resets WHERE id = :id', ['id' => $resetId]);
    }

    public static function purgeExpired(): void
    {
        Database::affectingStatement(
            'DELETE FROM password_resets WHERE expires_at < :now',
            ['now' => date('Y-m-d H:i:s')]
        );
    }
}
