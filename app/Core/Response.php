<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    private static ?string $cspNonce = null;

    /**
     * Send the baseline security headers on every response.
     *
     * The CSP is deliberately strict: no inline scripts are used anywhere in
     * the app (all JS lives in /assets/js/app.js), so 'unsafe-inline' is only
     * granted to styles, which templates do use for progress bars and charts.
     */
    public static function securityHeaders(Request $request): void
    {
        if (headers_sent()) {
            return;
        }

        self::$cspNonce = base64_encode(random_bytes(18));

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(self), payment=(self), interest-cohort=()');
        // PayPal's web SDK opens a cross-origin approval window and documents
        // same-origin-allow-popups as the compatible isolation policy.
        $coop = str_starts_with($request->path(), '/artist/subscription')
            ? 'same-origin-allow-popups'
            : 'same-origin';
        header('Cross-Origin-Opener-Policy: ' . $coop);
        header('X-Permitted-Cross-Domain-Policies: none');
        header_remove('X-Powered-By');

        $csp = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self' https://www.paypal.com https://www.sandbox.paypal.com",
            "script-src 'self' https://*.paypal.com https://*.paypalobjects.com 'nonce-" . self::$cspNonce . "'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://*.paypal.com https://*.paypalobjects.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: https:",
            "connect-src 'self' https://*.paypal.com https://*.paypalobjects.com",
            "frame-src https://*.paypal.com https://*.paypalobjects.com",
        ];
        header('Content-Security-Policy: ' . implode('; ', $csp));

        if ($request->isSecure()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /** Nonce shared with PayPal's SDK and its own script insertions. */
    public static function cspNonce(): string
    {
        return self::$cspNonce ??= base64_encode(random_bytes(18));
    }

    public static function redirect(string $url, int $status = 302): never
    {
        if (!headers_sent()) {
            // Guard against header injection via a tainted path.
            $url = str_replace(["\r", "\n"], '', $url);
            header('Location: ' . $url, true, $status);
        }
        exit;
    }

    public static function json(mixed $data, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function noCache(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }
    }
}
