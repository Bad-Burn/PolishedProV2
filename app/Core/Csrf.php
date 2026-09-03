<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;

/**
 * Synchroniser-token CSRF protection.
 *
 * One secret per session, compared with hash_equals. Every state-changing
 * request must carry it as a `_token` field or an X-CSRF-Token header; the
 * check is enforced centrally in the kernel, not per-controller, so a new
 * form cannot accidentally opt out.
 */
final class Csrf
{
    private const KEY = '__csrf_token';

    public static function token(): string
    {
        if (!isset($_SESSION[self::KEY]) || !is_string($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    /** Ready-made hidden input for forms. */
    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function check(Request $request): bool
    {
        $submitted = $request->input('_token');
        if (!is_string($submitted) || $submitted === '') {
            $submitted = $request->header('X-CSRF-Token') ?? '';
        }

        $expected = $_SESSION[self::KEY] ?? null;
        if (!is_string($expected) || $expected === '' || !is_string($submitted) || $submitted === '') {
            return false;
        }

        return hash_equals($expected, $submitted);
    }

    /**
     * Verify or abort. Also rejects cross-origin form posts by comparing the
     * Origin/Referer host to our own — a cheap second layer behind the token.
     */
    public static function verify(Request $request): void
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        if (!self::sameOrigin($request) || !self::check($request)) {
            Logger::security('CSRF verification failed', [
                'ip'      => $request->ip(),
                'path'    => $request->path(),
                'user_id' => $_SESSION['user_id'] ?? null,
            ]);
            throw new HttpException(419);
        }
    }

    private static function sameOrigin(Request $request): bool
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return true;
        }

        $source = $request->header('Origin') ?? $request->header('Referer');
        if ($source === null || $source === '') {
            // Some privacy tools strip both; the token check still applies.
            return true;
        }

        $sourceHost = strtolower((string) (parse_url($source, PHP_URL_HOST) ?: ''));
        $sourcePort = parse_url($source, PHP_URL_PORT);
        if ($sourcePort !== null) {
            $sourceHost .= ':' . $sourcePort;
        }

        return $sourceHost === $host;
    }

    /** Rotate the token — done on login and logout to prevent fixation. */
    public static function rotate(): void
    {
        $_SESSION[self::KEY] = bin2hex(random_bytes(32));
    }
}
