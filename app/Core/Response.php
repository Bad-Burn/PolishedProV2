<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
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

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(self), payment=(self), interest-cohort=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('X-Permitted-Cross-Domain-Policies: none');
        header_remove('X-Powered-By');

        $csp = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self' https://www.paypal.com https://www.sandbox.paypal.com",
            "script-src 'self' https://www.paypal.com https://www.sandbox.paypal.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: https:",
            "connect-src 'self' https://api-m.paypal.com https://api-m.sandbox.paypal.com",
            "frame-src https://www.paypal.com https://www.sandbox.paypal.com",
        ];
        header('Content-Security-Policy: ' . implode('; ', $csp));

        if ($request->isSecure()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
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
