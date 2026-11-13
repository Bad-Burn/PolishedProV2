<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Read-only view over the current HTTP request.
 */
final class Request
{
    /** @var array<string, mixed> */
    private array $query;

    /** @var array<string, mixed> */
    private array $body;

    /** @var array<string, mixed> */
    private array $files;

    /** @var array<string, mixed> route parameters filled in by the Router */
    private array $params = [];

    private string $method;
    private string $path;

    public function __construct()
    {
        $this->query = $_GET;
        $this->body  = $_POST;
        $this->files = $_FILES;

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        // Browsers can only send GET/POST, so honour a _method override on POST.
        if ($method === 'POST' && isset($this->body['_method'])) {
            $override = strtoupper((string) $this->body['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        $this->method = $method;

        $uri  = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $this->path = '/' . trim($this->stripBasePath($path), '/');
    }

    /**
     * When the app lives in a subdirectory (htdocs/polishedpro-v2/public) the
     * script name prefix has to come off before routes will match.
     */
    private function stripBasePath(string $path): string
    {
        $base = Url::basePath();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        return $path === '' ? '/' : $path;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    public function wantsJson(): bool
    {
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $xhr    = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

        return str_contains($accept, 'application/json') || $xhr === 'xmlhttprequest';
    }

    /** @param array<string, mixed> $params */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function intParam(string $key): int
    {
        return (int) $this->param($key, 0);
    }

    /**
     * Query-string value, trimmed. Arrays are returned untouched.
     */
    public function query(string $key, mixed $default = null): mixed
    {
        $value = $this->query[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    /** @return array<string, mixed> */
    public function allQuery(): array
    {
        return $this->query;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $value = $this->body[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->input($key);
        return is_numeric($value) ? (float) $value : $default;
    }

    public function bool(string $key): bool
    {
        $value = $this->input($key);
        return in_array($value, ['1', 'true', 'on', 'yes', 1, true], true);
    }

    /** @return array<int, string> */
    public function stringArray(string $key): array
    {
        $value = $this->body[$key] ?? [];
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_scalar($item)) {
                $trimmed = trim((string) $item);
                if ($trimmed !== '') {
                    $out[] = $trimmed;
                }
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->body;
    }

    /** @return array<string, mixed>|null */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $file;
    }

    public function rawBody(): string
    {
        return (string) file_get_contents('php://input');
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$key] ?? null;
        return is_string($value) ? $value : null;
    }

    /**
     * Client IP. Proxy headers are only trusted when TRUSTED_PROXY is enabled,
     * since they are attacker-controlled on a direct connection.
     */
    public function ip(): string
    {
        if (Config::env('TRUSTED_PROXY', false) === true) {
            // Cloudflare states the visitor's address outright.
            $cloudflare = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
            if (is_string($cloudflare) && filter_var($cloudflare, FILTER_VALIDATE_IP)) {
                return $cloudflare;
            }

            // Otherwise take the address our proxy appended — the last entry.
            // Everything to its left arrived from the client and can be forged,
            // which would let one attacker look like a new IP on every request
            // and walk straight past the login throttle.
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if (is_string($forwarded) && $forwarded !== '') {
                $hops = array_map('trim', explode(',', $forwarded));
                $last = (string) end($hops);
                if (filter_var($last, FILTER_VALIDATE_IP)) {
                    return $last;
                }
            }
        }

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function isSecure(): bool
    {
        if (($_SERVER['HTTPS'] ?? 'off') !== 'off' && !empty($_SERVER['HTTPS'])) {
            return true;
        }
        if (Config::env('TRUSTED_PROXY', false) === true) {
            return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        }
        return (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
    }
}
