<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

class ValidationException extends \RuntimeException
{
    /** @param array<string, string> $errors field => message */
    public function __construct(private readonly array $errors, string $message = 'The submitted details need attention.')
    {
        parent::__construct($message, 422);
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
