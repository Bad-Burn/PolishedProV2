<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Hardened session handling.
 *
 * On top of the usual cookie flags this enforces an idle timeout, an absolute
 * lifetime, periodic ID rotation, and a client fingerprint so a stolen session
 * cookie replayed from another browser is rejected.
 */
final class Session
{
    private const IDLE_KEY        = '__last_seen';
    private const STARTED_KEY     = '__started_at';
    private const ROTATED_KEY     = '__rotated_at';
    private const FINGERPRINT_KEY = '__fingerprint';

    private static bool $started = false;

    public static function start(Request $request): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $lifetime = (int) Config::get('app.session.idle_timeout', 3600);

        // A dedicated name avoids colliding with other apps on the same host.
        session_name((string) Config::get('app.session.name', 'polishedpro_session'));

        session_set_cookie_params([
            'lifetime' => 0,               // session cookie: cleared when the browser closes
            'path'     => Url::basePath() === '' ? '/' : Url::basePath() . '/',
            'domain'   => '',
            'secure'   => $request->isSecure(),
            'httponly' => true,            // unreadable from JavaScript
            'samesite' => 'Lax',           // survives PayPal's redirect back, blocks cross-site POSTs
        ]);

        ini_set('session.use_strict_mode', '1');   // reject session IDs the server never issued
        ini_set('session.use_only_cookies', '1');  // never accept an ID from the query string
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');

        session_start();
        self::$started = true;

        self::enforceLifetimes($request, $lifetime);
        self::enforceFingerprint($request);
    }

    private static function enforceLifetimes(Request $request, int $idleTimeout): void
    {
        $now      = time();
        $absolute = (int) Config::get('app.session.absolute_timeout', 43200);

        $lastSeen  = (int) ($_SESSION[self::IDLE_KEY] ?? $now);
        $startedAt = (int) ($_SESSION[self::STARTED_KEY] ?? $now);

        $expired = ($now - $lastSeen) > $idleTimeout
            || ($startedAt > 0 && ($now - $startedAt) > $absolute);

        if ($expired && isset($_SESSION['user_id'])) {
            self::destroy();
            session_start();
            self::$started = true;
            $_SESSION['flash'][] = ['type' => 'info', 'message' => 'Your session expired. Please sign in again.'];
            $startedAt = $now;
        }

        $_SESSION[self::IDLE_KEY]    = $now;
        $_SESSION[self::STARTED_KEY] = $_SESSION[self::STARTED_KEY] ?? $startedAt;

        // Rotate the ID periodically so a leaked ID has a short useful life.
        $rotatedAt = (int) ($_SESSION[self::ROTATED_KEY] ?? 0);
        if ($rotatedAt === 0) {
            $_SESSION[self::ROTATED_KEY] = $now;
        } elseif (($now - $rotatedAt) > (int) Config::get('app.session.rotate_every', 900)) {
            self::regenerate();
        }
    }

    /**
     * Bind the session to a coarse client fingerprint. Only the user agent and
     * the /24 of the IP are used, so ordinary mobile IP changes do not log
     * people out, but a cookie replayed from a different machine does not work.
     */
    private static function enforceFingerprint(Request $request): void
    {
        $fingerprint = hash('sha256', implode('|', [
            $request->userAgent(),
            self::ipPrefix($request->ip()),
            (string) Config::get('app.key'),
        ]));

        $stored = $_SESSION[self::FINGERPRINT_KEY] ?? null;

        if ($stored === null) {
            $_SESSION[self::FINGERPRINT_KEY] = $fingerprint;
            return;
        }

        if (!hash_equals((string) $stored, $fingerprint)) {
            Logger::security('Session fingerprint mismatch — session discarded', [
                'ip'      => $request->ip(),
                'user_id' => $_SESSION['user_id'] ?? null,
            ]);
            self::destroy();
            session_start();
            self::$started = true;
            $_SESSION[self::FINGERPRINT_KEY] = $fingerprint;
            $_SESSION['flash'][] = ['type' => 'error', 'message' => 'Your session was ended for security reasons. Please sign in again.'];
        }
    }

    private static function ipPrefix(string $ip): string
    {
        if (str_contains($ip, ':')) {
            // IPv6: keep the routing prefix only.
            return implode(':', array_slice(explode(':', $ip), 0, 4));
        }
        return implode('.', array_slice(explode('.', $ip), 0, 3));
    }

    /** Issue a brand-new session ID, keeping the data. Called on privilege changes. */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION[self::ROTATED_KEY] = time();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Read a key and remove it in one step. */
    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $value;
    }

    public static function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    // ---- Flash messages -------------------------------------------------

    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return array<int, array{type:string, message:string}> */
    public static function consumeFlashes(): array
    {
        $messages = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return is_array($messages) ? $messages : [];
    }

    /**
     * Stash validation errors + submitted values so a redirect back to the form
     * can repopulate it instead of making the user retype everything.
     *
     * @param array<string, string> $errors
     * @param array<string, mixed>  $old
     */
    public static function flashFormState(array $errors, array $old = []): void
    {
        unset($old['password'], $old['password_confirmation'], $old['current_password'], $old['_token'], $old['totp_code']);
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_old']    = $old;
    }
}
