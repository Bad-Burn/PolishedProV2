<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;
use App\Models\Subscription;

/**
 * Route guards. Anything that stops a request before the controller runs lives
 * here so authorisation rules are declared in one place (routes/web.php) rather
 * than scattered through controllers where one can be forgotten.
 */
final class Middleware
{
    public static function run(string $name, Request $request): void
    {
        [$key, $param] = array_pad(explode(':', $name, 2), 2, null);

        match ($key) {
            'auth'         => self::auth($request),
            'guest'        => self::guest(),
            'role'         => self::role((string) $param),
            'verified'     => self::verified(),
            'throttle'     => self::throttle($request, (string) $param),
            'nocache'      => Response::noCache(),
            'subscription' => self::subscription(),
            default        => throw new \RuntimeException("Unknown middleware [{$name}]"),
        };
    }

    private static function auth(Request $request): void
    {
        if (Auth::check()) {
            return;
        }

        if ($request->wantsJson()) {
            Response::json(['error' => 'Authentication required.'], 401);
        }

        // Remember where they were headed so login can send them back.
        if ($request->isGet()) {
            Session::put('intended_url', $request->path());
        }

        Session::flash('info', 'Please sign in to continue.');
        Response::redirect(Url::to('/login'));
    }

    private static function guest(): void
    {
        if (Auth::check()) {
            Response::redirect(Auth::homeFor(Auth::role()));
        }
    }

    private static function role(string $roles): void
    {
        $allowed = array_filter(array_map('trim', explode(',', $roles)));

        if (!Auth::check()) {
            Session::flash('info', 'Please sign in to continue.');
            Response::redirect(Url::to('/login'));
        }

        if (!Auth::is(...$allowed)) {
            Logger::security('Blocked cross-role access attempt', [
                'user_id'  => Auth::id(),
                'has'      => Auth::role(),
                'required' => $allowed,
            ]);
            throw new HttpException(403);
        }
    }

    /** Artists must be approved by an admin before they can take bookings. */
    private static function verified(): void
    {
        $user = Auth::user();
        if ($user === null) {
            throw new HttpException(401);
        }

        if (($user['role'] ?? '') === 'artist' && empty($user['approved_at'])) {
            Session::flash('info', 'Your artist profile is still being reviewed. You can set everything up in the meantime.');
            Response::redirect(Url::to('/artist'));
        }
    }

    /** Make the caller's current plan available to controllers and views. */
    /**
     * Make the caller's plan and access state available to every artist view.
     *
     * This deliberately does not block the dashboard. A lapsed artist is hidden
     * from clients and cannot take bookings (enforced in Artist::PUBLIC_WHERE
     * and therefore in BookingService), but they keep access to their own data
     * so they can see what they are losing and pay to restore it. Locking them
     * out of their own calendar would only make them harder to win back.
     */
    private static function subscription(): void
    {
        $userId = Auth::id();
        if ($userId === null) {
            return;
        }

        View::share('currentPlan', Subscription::planFor($userId));

        if (Auth::role() === 'artist') {
            View::share('subscriptionAccess', Subscription::accessFor($userId));
        }
    }

    /**
     * throttle:60,1  → 60 requests per 1 minute, keyed by route + IP (+ user).
     */
    private static function throttle(Request $request, string $param): void
    {
        [$max, $minutes] = array_pad(explode(',', $param), 2, '1');
        $max     = max(1, (int) $max);
        $decay   = max(1, (int) $minutes) * 60;
        $key     = 'route:' . $request->path() . '|' . $request->ip() . '|' . (Auth::id() ?? 0);

        if (RateLimiter::tooManyAttempts($key, $max, $decay)) {
            $retry = RateLimiter::availableIn($key);
            if (!headers_sent()) {
                header('Retry-After: ' . $retry);
            }
            Logger::security('Rate limit exceeded', ['ip' => $request->ip(), 'path' => $request->path()]);
            throw new HttpException(429);
        }

        RateLimiter::hit($key, $decay);
    }
}
