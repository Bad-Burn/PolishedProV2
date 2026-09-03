<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

/**
 * Thrown when a request should terminate with a specific status code.
 * The error handler turns these into a rendered error page.
 */
class HttpException extends \RuntimeException
{
    public function __construct(
        private readonly int $status = 500,
        string $message = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }

    public static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'That request could not be understood.',
            401 => 'You need to sign in to continue.',
            403 => 'You do not have permission to view this page.',
            404 => 'We could not find the page you were looking for.',
            405 => 'That action is not allowed on this page.',
            419 => 'Your session expired. Please try again.',
            429 => 'Too many attempts. Please slow down and try again shortly.',
            default => 'Something went wrong on our end.',
        };
    }
}
