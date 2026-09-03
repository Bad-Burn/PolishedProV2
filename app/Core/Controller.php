<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;

/**
 * Shared behaviour for every controller: rendering, redirecting, validation
 * error handling, and ownership assertions.
 */
abstract class Controller
{
    /**
     * Render a view into the response.
     *
     * @param array<string, mixed> $data
     */
    protected function view(string $template, array $data = [], int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=utf-8');
        }

        echo View::render($template, $data);
        exit;
    }

    protected function redirect(string $path, int $status = 302): never
    {
        Response::redirect(Url::to($path), $status);
    }

    protected function back(): never
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? null;

        // Only follow a Referer that points back at this host.
        if (is_string($referer) && $referer !== '') {
            $host = strtolower((string) (parse_url($referer, PHP_URL_HOST) ?: ''));
            if ($host === strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''))) {
                Response::redirect($referer);
            }
        }

        Response::redirect(Url::to('/'));
    }

    protected function json(mixed $data, int $status = 200): never
    {
        Response::json($data, $status);
    }

    /**
     * Validate input, or flash the errors and bounce back to the form.
     *
     * @param array<string, string> $rules
     * @return array<string, mixed>
     */
    protected function validate(Request $request, array $rules, array $labels = [], ?string $redirectTo = null): array
    {
        $validator = Validator::make($request->all(), $rules, $labels);

        if ($validator->fails()) {
            $this->failValidation($request, $validator->errors(), $redirectTo);
        }

        return $validator->validated();
    }

    /**
     * @param array<string, string> $errors
     */
    protected function failValidation(Request $request, array $errors, ?string $redirectTo = null): never
    {
        if ($request->wantsJson()) {
            Response::json(['errors' => $errors], 422);
        }

        Session::flashFormState($errors, $request->all());
        Session::flash('error', 'Please check the highlighted fields and try again.');

        if ($redirectTo !== null) {
            $this->redirect($redirectTo);
        }

        $this->back();
    }

    protected function success(string $message, string $redirectTo): never
    {
        Session::flash('success', $message);
        $this->redirect($redirectTo);
    }

    protected function failure(string $message, string $redirectTo): never
    {
        Session::flash('error', $message);
        $this->redirect($redirectTo);
    }

    /**
     * Assert the signed-in user owns a record. Admins bypass the check.
     * Used on every artist/customer detail route so ids cannot be enumerated.
     */
    protected function authorizeOwner(?int $ownerId, string $context = 'record'): void
    {
        if ($ownerId !== null && ($ownerId === Auth::id() || Auth::isAdmin())) {
            return;
        }

        Logger::security('Ownership check failed', [
            'user_id'  => Auth::id(),
            'owner_id' => $ownerId,
            'context'  => $context,
        ]);

        throw new HttpException(403);
    }

    protected function abort(int $status, string $message = ''): never
    {
        throw new HttpException($status, $message);
    }

    /** @param array<string, mixed>|null $record */
    protected function abortIfMissing(?array $record, string $what = 'page'): array
    {
        if ($record === null) {
            throw new HttpException(404, "We could not find that {$what}.");
        }
        return $record;
    }
}
