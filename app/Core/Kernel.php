<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\ValidationException;
use App\Models\Notification;

/**
 * Boots the application and turns a Request into a response, including the
 * global error handling that keeps stack traces off the screen in production.
 */
final class Kernel
{
    public static function boot(): void
    {
        Config::loadEnv(PP_ROOT . DIRECTORY_SEPARATOR . '.env');
        Config::load(PP_ROOT . DIRECTORY_SEPARATOR . 'config');

        $debug = (bool) Config::get('app.debug', false);

        // Errors are always logged; they are only *displayed* in debug mode.
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('display_startup_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Manila'));
        mb_internal_encoding('UTF-8');
        setlocale(LC_ALL, 'en_US.UTF-8');

        self::assertAppKey();
    }

    /**
     * Refuse to run with the placeholder APP_KEY — it is the secret behind
     * session fingerprints, remember-me hashes and rate-limit keys.
     */
    private static function assertAppKey(): void
    {
        $key = (string) Config::get('app.key', '');

        if ($key === '' || $key === 'change-me' || strlen($key) < 32) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "PolishedPro is not configured.\n\n";
            echo "Set a unique APP_KEY in your .env file, then reload.\n";
            echo "Generate one with:  php bin/key.php\n";
            exit;
        }
    }

    public static function handle(Request $request, Router $router): void
    {
        try {
            Response::securityHeaders($request);
            Session::start($request);

            // One central CSRF gate: every POST/PUT/PATCH/DELETE is covered,
            // except the PayPal webhook which authenticates by signature instead.
            if (!str_starts_with($request->path(), '/webhooks/')) {
                Csrf::verify($request);
            }

            self::shareViewData();

            $router->dispatch($request);
        } catch (ValidationException $e) {
            // A controller that let one escape: treat as a bounce-back.
            Session::flashFormState($e->errors(), $request->all());
            Session::flash('error', $e->getMessage());
            Response::redirect($_SERVER['HTTP_REFERER'] ?? Url::to('/'));
        } catch (HttpException $e) {
            self::renderError($request, $e->status(), $e->getMessage());
        } catch (\Throwable $e) {
            Logger::error($e->getMessage(), [
                'file'  => $e->getFile() . ':' . $e->getLine(),
                'path'  => $request->path(),
                'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 8),
            ]);

            if (Config::get('app.debug')) {
                self::renderDebug($e);
            }

            self::renderError($request, 500, HttpException::defaultMessage(500));
        }
    }

    /**
     * Data every template can rely on existing.
     */
    private static function shareViewData(): void
    {
        $user = Auth::user();

        View::share('currentUser', $user);
        View::share('flashes', Session::consumeFlashes());
        View::share('formErrors', Session::pull('form_errors', []));
        View::share('formOld', Session::pull('form_old', []));
        View::share('appName', Config::get('app.name', 'PolishedPro'));

        View::share('unreadNotifications', $user === null ? 0 : Notification::unreadCount((int) $user['id']));
    }

    private static function renderError(Request $request, int $status, string $message): never
    {
        if ($request->wantsJson()) {
            Response::json(['error' => $message], $status);
        }

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=utf-8');
        }

        try {
            echo View::render('errors/error', [
                'status'  => $status,
                'message' => $message,
            ]);
        } catch (\Throwable) {
            // Last resort if even the error template is broken.
            echo '<!doctype html><meta charset="utf-8"><title>' . $status . '</title>'
                . '<div style="font:16px/1.6 system-ui;max-width:32rem;margin:20vh auto;padding:0 1.5rem">'
                . '<h1 style="font-size:1.5rem">' . $status . '</h1><p>' . htmlspecialchars($message) . '</p></div>';
        }

        exit;
    }

    private static function renderDebug(\Throwable $e): never
    {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        echo '<!doctype html><meta charset="utf-8"><title>Application error</title>';
        echo '<div style="font:14px/1.6 ui-monospace,Menlo,Consolas,monospace;padding:2rem;background:#1c1917;color:#fafaf9;min-height:100vh">';
        echo '<p style="color:#fca5a5;font-size:1.1rem;margin:0 0 .5rem"><strong>' . htmlspecialchars($e::class) . '</strong></p>';
        echo '<p style="font-size:1.05rem;margin:0 0 1rem">' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p style="color:#a8a29e">' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>';
        echo '<pre style="white-space:pre-wrap;color:#d6d3d1;border-top:1px solid #44403c;padding-top:1rem">'
            . htmlspecialchars($e->getTraceAsString()) . '</pre></div>';

        exit;
    }
}
