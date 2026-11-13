<?php

declare(strict_types=1);

namespace App\Core;

/**
 * URL generation that survives being installed in a subdirectory.
 */
final class Url
{
    private static ?string $basePath = null;

    /**
     * The portion of the URL path that points at public/, e.g. "/polishedpro-v2/public"
     * when running under XAMPP, or "" when a vhost points straight at public/.
     */
    public static function basePath(): string
    {
        if (self::$basePath !== null) {
            return self::$basePath;
        }

        $configured = Config::env('APP_BASE_PATH');
        if (is_string($configured) && $configured !== '') {
            return self::$basePath = '/' . trim($configured, '/');
        }

        // From the command line (cron, bin/ scripts) SCRIPT_NAME is the CLI
        // script, not the front controller — take the path from APP_URL so
        // links in emails and notifications point at the real site.
        if (PHP_SAPI === 'cli') {
            $path = rtrim((string) parse_url((string) Config::env('APP_URL', ''), PHP_URL_PATH), '/');
            return self::$basePath = $path;
        }

        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

        // dirname() must be normalised *after* it runs: on Windows it returns
        // a backslash for a root-level script, which would otherwise leak into
        // every generated URL as "\/account".
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');

        return self::$basePath = ($dir === '' || $dir === '.') ? '' : $dir;
    }

    /**
     * Build an app-relative URL: url('/artists?category=nails')
     */
    public static function to(string $path = '/', array $query = []): string
    {
        $path = '/' . ltrim($path, '/');
        $url  = self::basePath() . ($path === '/' ? '/' : rtrim($path, '/'));

        if ($url === '') {
            $url = '/';
        }

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        return $url;
    }

    /**
     * Absolute URL, needed for PayPal return/cancel links and webhooks.
     */
    public static function absolute(string $path = '/', array $query = []): string
    {
        $configured = Config::env('APP_URL');
        if (is_string($configured) && $configured !== '') {
            $base = rtrim($configured, '/');
            $rel  = '/' . ltrim($path, '/');
            $url  = $base . ($rel === '/' ? '' : rtrim($rel, '/'));
            if ($query !== []) {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
            }
            return $url;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return $scheme . '://' . $host . self::to($path, $query);
    }

    /**
     * Turn a link built with Url::to() — which already carries the base path —
     * into an absolute one for use outside the site, such as in an email.
     */
    public static function absoluteLink(string $link): string
    {
        if (preg_match('#^https?://#i', $link) === 1) {
            return $link;
        }

        // Strip the base path this request was served under and hang the rest
        // off APP_URL, so the link is right even when the two differ.
        $base = self::basePath();
        if ($base !== '' && str_starts_with($link, $base . '/')) {
            $link = substr($link, strlen($base));
        }

        return self::absolute($link === '' ? '/' : $link);
    }

    /**
     * Public URL for something under public/ (CSS, uploads, images).
     * A cache-busting mtime is appended for local asset files.
     */
    public static function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = PP_PUBLIC . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $url  = self::basePath() . '/' . $path;

        if (is_file($file)) {
            $url .= '?v=' . filemtime($file);
        }

        return $url;
    }

    /**
     * Uploaded file URL, falling back to a generated placeholder when missing.
     */
    public static function upload(?string $path, ?string $fallback = null): ?string
    {
        if ($path === null || $path === '') {
            return $fallback;
        }

        // Never let a stored path escape the uploads directory.
        $clean = ltrim(str_replace(['..', '\\'], ['', '/'], $path), '/');

        return self::basePath() . '/uploads/' . $clean;
    }

    /**
     * Current path plus modified query parameters — handy for filter/sort links.
     */
    public static function withQuery(array $changes): string
    {
        $query = $_GET;
        foreach ($changes as $key => $value) {
            if ($value === null || $value === '') {
                unset($query[$key]);
            } else {
                $query[$key] = $value;
            }
        }

        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

        return $path . ($query === [] ? '' : '?' . http_build_query($query));
    }
}
