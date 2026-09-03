<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Session-backed authentication.
 *
 * The session only stores the user id plus a hash of the password hash. If an
 * account's password changes (or is reset after a compromise) every other
 * session for that account stops validating on its next request.
 */
final class Auth
{
    private static ?array $user = null;
    private static bool $resolved = false;

    public static function login(array $user, bool $remember = false): void
    {
        // New ID on privilege change defeats session fixation.
        Session::regenerate();
        Csrf::rotate();

        Session::put('user_id', (int) $user['id']);
        Session::put('auth_hash', self::stamp((string) $user['password_hash']));
        Session::put('auth_role', (string) $user['role']);
        Session::put('__started_at', time());

        self::$user     = $user;
        self::$resolved = true;

        User::touchLogin((int) $user['id']);

        if ($remember) {
            RememberToken::issue((int) $user['id']);
        }
    }

    public static function logout(): void
    {
        $userId = Session::get('user_id');
        if (is_int($userId)) {
            RememberToken::clearForUser($userId);
        }

        RememberToken::forgetCookie();
        Session::destroy();

        self::$user     = null;
        self::$resolved = true;
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;

        $id = Session::get('user_id');

        if (!is_int($id)) {
            return self::$user = self::attemptRemember();
        }

        $user = User::find($id);

        if ($user === null || $user['status'] !== 'active') {
            self::forceLogout($user === null ? 'account removed' : 'account not active');
            return self::$user = null;
        }

        // Invalidate sessions issued before the current password.
        $expected = self::stamp((string) $user['password_hash']);
        $stored   = Session::get('auth_hash');
        if (!is_string($stored) || !hash_equals($expected, $stored)) {
            self::forceLogout('credentials changed');
            return self::$user = null;
        }

        return self::$user = $user;
    }

    private static function attemptRemember(): ?array
    {
        $user = RememberToken::resolve();
        if ($user === null) {
            return null;
        }

        Session::regenerate();
        Csrf::rotate();
        Session::put('user_id', (int) $user['id']);
        Session::put('auth_hash', self::stamp((string) $user['password_hash']));
        Session::put('auth_role', (string) $user['role']);
        Session::put('__started_at', time());
        User::touchLogin((int) $user['id']);

        return $user;
    }

    private static function forceLogout(string $reason): void
    {
        Logger::security('Session invalidated: ' . $reason, ['user_id' => Session::get('user_id')]);
        RememberToken::forgetCookie();
        Session::forget('user_id');
        Session::forget('auth_hash');
        Session::forget('auth_role');
    }

    private static function stamp(string $passwordHash): string
    {
        return hash_hmac('sha256', $passwordHash, (string) Config::get('app.key', 'polishedpro'));
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user === null ? null : (int) $user['id'];
    }

    public static function role(): ?string
    {
        $user = self::user();
        return $user === null ? null : (string) $user['role'];
    }

    public static function is(string ...$roles): bool
    {
        $role = self::role();
        return $role !== null && in_array($role, $roles, true);
    }

    public static function isCustomer(): bool
    {
        return self::is('customer');
    }

    public static function isArtist(): bool
    {
        return self::is('artist');
    }

    public static function isAdmin(): bool
    {
        return self::is('admin');
    }

    /** Clear the memoised user so the next call re-reads from the database. */
    public static function refresh(): void
    {
        self::$user     = null;
        self::$resolved = false;
    }

    /** Where a given role should land after signing in. */
    public static function homeFor(?string $role): string
    {
        return match ($role) {
            'admin'  => Url::to('/admin'),
            'artist' => Url::to('/artist'),
            default  => Url::to('/account'),
        };
    }
}
