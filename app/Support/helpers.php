<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;

/**
 * Template helpers. Kept short because they appear on nearly every line of the
 * views — `e()` in particular is the escaping funnel for all dynamic output.
 */

if (!function_exists('e')) {
    /** Escape for HTML text and quoted attribute contexts. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = '/', array $query = []): string
    {
        return Url::to($path, $query);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return Url::asset($path);
    }
}

if (!function_exists('upload_url')) {
    function upload_url(?string $path, ?string $fallback = null): ?string
    {
        return Url::upload($path, $fallback);
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('old')) {
    /** Repopulate a form field after a failed validation round-trip. */
    function old(string $key, mixed $default = ''): string
    {
        $old = View::shared('formOld', []);
        return e(is_array($old) ? ($old[$key] ?? $default) : $default);
    }
}

if (!function_exists('old_raw')) {
    function old_raw(string $key, mixed $default = null): mixed
    {
        $old = View::shared('formOld', []);
        return is_array($old) ? ($old[$key] ?? $default) : $default;
    }
}

if (!function_exists('error_for')) {
    function error_for(string $field): ?string
    {
        $errors = View::shared('formErrors', []);
        return is_array($errors) ? ($errors[$field] ?? null) : null;
    }
}

if (!function_exists('has_error')) {
    function has_error(string $field): bool
    {
        return error_for($field) !== null;
    }
}

if (!function_exists('field_error')) {
    /** Renders the inline error text under an input, if any. */
    function field_error(string $field): string
    {
        $message = error_for($field);
        return $message === null
            ? ''
            : '<p class="field__error" role="alert">' . e($message) . '</p>';
    }
}

if (!function_exists('money')) {
    /** Peso formatting used across bookings, services and payouts. */
    function money(float|int|string|null $amount, bool $withSymbol = true): string
    {
        $value = number_format((float) ($amount ?? 0), 2, '.', ',');
        return $withSymbol ? '₱' . $value : $value;
    }
}

if (!function_exists('money_compact')) {
    /** Drops the decimals for large dashboard figures: ₱12.4k */
    function money_compact(float|int|null $amount): string
    {
        $value = (float) ($amount ?? 0);

        return match (true) {
            $value >= 1_000_000 => '₱' . rtrim(rtrim(number_format($value / 1_000_000, 1, '.', ''), '0'), '.') . 'M',
            $value >= 1_000     => '₱' . rtrim(rtrim(number_format($value / 1_000, 1, '.', ''), '0'), '.') . 'k',
            default             => '₱' . number_format($value, 0),
        };
    }
}

if (!function_exists('duration_label')) {
    function duration_label(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' min';
        }

        $hours = intdiv($minutes, 60);
        $rest  = $minutes % 60;

        return $rest === 0 ? $hours . ' hr' : $hours . ' hr ' . $rest . ' min';
    }
}

if (!function_exists('date_label')) {
    function date_label(?string $datetime, string $format = 'M j, Y'): string
    {
        if ($datetime === null || $datetime === '') {
            return '—';
        }
        $timestamp = strtotime($datetime);
        return $timestamp === false ? '—' : date($format, $timestamp);
    }
}

if (!function_exists('time_label')) {
    function time_label(?string $datetime): string
    {
        if ($datetime === null || $datetime === '') {
            return '—';
        }
        $timestamp = strtotime($datetime);
        return $timestamp === false ? '—' : date('g:i A', $timestamp);
    }
}

if (!function_exists('relative_time')) {
    /** "3 hours ago" / "in 2 days" — used in notifications and activity feeds. */
    function relative_time(?string $datetime): string
    {
        if ($datetime === null || $datetime === '') {
            return '';
        }

        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return '';
        }

        $diff   = time() - $timestamp;
        $future = $diff < 0;
        $diff   = abs($diff);

        $phrase = match (true) {
            $diff < 60        => 'just now',
            $diff < 3600      => plural(intdiv($diff, 60), 'minute'),
            $diff < 86400     => plural(intdiv($diff, 3600), 'hour'),
            $diff < 604800    => plural(intdiv($diff, 86400), 'day'),
            $diff < 2629800   => plural(intdiv($diff, 604800), 'week'),
            $diff < 31557600  => plural(intdiv($diff, 2629800), 'month'),
            default           => plural(intdiv($diff, 31557600), 'year'),
        };

        if ($phrase === 'just now') {
            return $phrase;
        }

        return $future ? 'in ' . $phrase : $phrase . ' ago';
    }
}

if (!function_exists('plural')) {
    function plural(int $count, string $noun): string
    {
        return $count . ' ' . $noun . ($count === 1 ? '' : 's');
    }
}

if (!function_exists('initials')) {
    function initials(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '?';
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last  = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';

        return mb_strtoupper($first . $last);
    }
}

if (!function_exists('excerpt')) {
    function excerpt(?string $text, int $length = 140): string
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $text) ?? '');
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length - 1) . '…';
    }
}

if (!function_exists('active_when')) {
    /** Adds a class when the current path matches, for nav highlighting. */
    function active_when(string $pattern, string $class = 'is-active'): string
    {
        $current = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $current = '/' . trim(str_replace(Url::basePath(), '', $current), '/');
        $pattern = '/' . trim($pattern, '/');

        if ($pattern === '/') {
            return $current === '/' ? $class : '';
        }

        return str_starts_with($current, $pattern) ? $class : '';
    }
}

if (!function_exists('status_tone')) {
    /** Maps a booking/subscription status onto a badge colour token. */
    function status_tone(string $status): string
    {
        return match ($status) {
            'confirmed', 'active', 'completed', 'paid'   => 'positive',
            'pending', 'past_due', 'awaiting_payment'    => 'caution',
            'cancelled', 'declined', 'expired', 'no_show', 'suspended' => 'critical',
            default                                      => 'neutral',
        };
    }
}

if (!function_exists('stars')) {
    /** Accessible star rating markup. */
    function stars(float $rating, bool $showValue = true): string
    {
        $rounded = round($rating * 2) / 2;
        $html    = '<span class="stars" role="img" aria-label="Rated ' . e((string) round($rating, 1)) . ' out of 5">';

        for ($i = 1; $i <= 5; $i++) {
            $state = $rounded >= $i ? 'is-full' : ($rounded >= $i - 0.5 ? 'is-half' : '');
            $html .= '<span class="stars__item ' . $state . '" aria-hidden="true"></span>';
        }

        $html .= '</span>';

        if ($showValue) {
            $html .= '<span class="stars__value">' . e(number_format($rating, 1)) . '</span>';
        }

        return $html;
    }
}

if (!function_exists('flash_messages')) {
    function flash_messages(): array
    {
        return Session::consumeFlashes();
    }
}

if (!function_exists('view_partial')) {
    function view_partial(string $name, array $data = []): string
    {
        return View::partial($name, $data);
    }
}

if (!function_exists('icon')) {
    /** Inline SVG icon. See app/Views/partials/icon.php for the set. */
    function icon(string $name, string $class = ''): string
    {
        return View::partial('icon', ['name' => $name, 'class' => $class]);
    }
}

if (!function_exists('layout')) {
    /** Declare which layout wraps this view. Call it at the top of the template. */
    function layout(string $name): void
    {
        View::useLayout($name);
    }
}

if (!function_exists('section')) {
    /** Begin capturing a named block for the layout to place. */
    function section(string $name): void
    {
        View::startSection($name);
    }
}

if (!function_exists('end_section')) {
    function end_section(): void
    {
        View::endSection();
    }
}
