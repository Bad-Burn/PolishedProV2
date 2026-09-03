<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\ValidationException;

/**
 * Small rule-based validator.
 *
 * Rules are given as "field" => "required|email|max:255". Everything server-side
 * is validated here; the HTML5 attributes in the templates are convenience only
 * and are never trusted.
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $validated = [];

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules
     * @param array<string, string> $labels human-friendly field names for messages
     */
    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $labels = []
    ) {
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        return (new self($data, $rules, $labels))->run();
    }

    public function run(): self
    {
        foreach ($this->rules as $field => $ruleString) {
            $value    = $this->data[$field] ?? null;
            $ruleList = explode('|', $ruleString);
            $optional = !in_array('required', $ruleList, true);

            if (is_string($value)) {
                $value = trim($value);
            }

            $isEmpty = $value === null || $value === '' || (is_array($value) && $value === []);

            if ($isEmpty && $optional) {
                $this->validated[$field] = in_array('nullable', $ruleList, true) ? null : $value;
                continue;
            }

            foreach ($ruleList as $rule) {
                if ($rule === '' || $rule === 'nullable') {
                    continue;
                }

                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

                if (!$this->apply((string) $name, $param, $field, $value)) {
                    break; // one message per field keeps the form readable
                }
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }

        return $this;
    }

    private function apply(string $rule, ?string $param, string $field, mixed $value): bool
    {
        $label = $this->label($field);

        return match ($rule) {
            'required' => $this->assert(
                !($value === null || $value === '' || (is_array($value) && $value === [])),
                $field,
                "{$label} is required."
            ),
            'email' => $this->assert(
                is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false && strlen($value) <= 255,
                $field,
                'Enter a valid email address.'
            ),
            'min' => $this->assert(
                mb_strlen((string) $value) >= (int) $param,
                $field,
                "{$label} must be at least {$param} characters."
            ),
            'max' => $this->assert(
                mb_strlen((string) $value) <= (int) $param,
                $field,
                "{$label} cannot be longer than {$param} characters."
            ),
            'numeric' => $this->assert(
                is_numeric($value),
                $field,
                "{$label} must be a number."
            ),
            'integer' => $this->assert(
                filter_var($value, FILTER_VALIDATE_INT) !== false,
                $field,
                "{$label} must be a whole number."
            ),
            'gt' => $this->assert(
                is_numeric($value) && (float) $value > (float) $param,
                $field,
                "{$label} must be greater than {$param}."
            ),
            'gte' => $this->assert(
                is_numeric($value) && (float) $value >= (float) $param,
                $field,
                "{$label} must be at least {$param}."
            ),
            'lte' => $this->assert(
                is_numeric($value) && (float) $value <= (float) $param,
                $field,
                "{$label} cannot be more than {$param}."
            ),
            'in' => $this->assert(
                in_array((string) $value, explode(',', (string) $param), true),
                $field,
                "Choose a valid option for {$label}."
            ),
            'date' => $this->assert(
                is_string($value) && strtotime($value) !== false,
                $field,
                "{$label} must be a valid date."
            ),
            'future' => $this->assert(
                is_string($value) && ($ts = strtotime($value)) !== false && $ts > time(),
                $field,
                "{$label} must be in the future."
            ),
            'phone' => $this->assert(
                is_string($value) && preg_match('/^[0-9 +().-]{7,20}$/', $value) === 1,
                $field,
                'Enter a valid contact number.'
            ),
            'url' => $this->assert(
                is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false,
                $field,
                "{$label} must be a valid link."
            ),
            'alpha_dash' => $this->assert(
                is_string($value) && preg_match('/^[A-Za-z0-9_-]+$/', $value) === 1,
                $field,
                "{$label} may only contain letters, numbers, dashes and underscores."
            ),
            'time' => $this->assert(
                is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1,
                $field,
                "{$label} must be a valid time."
            ),
            'confirmed' => $this->assert(
                is_string($value) && $value === (string) ($this->data[$field . '_confirmation'] ?? ''),
                $field . '_confirmation',
                'The two passwords do not match.'
            ),
            'password' => $this->assertPassword($field, (string) $value),
            'boolean'  => true,
            'string'   => $this->assert(is_string($value), $field, "{$label} must be text."),
            default    => true,
        };
    }

    /**
     * Password policy: length does most of the work, but we also reject the
     * obvious dictionary picks and anything that is just the user's email.
     */
    private function assertPassword(string $field, string $value): bool
    {
        $min = (int) Config::get('app.security.min_password_length', 10);

        if (mb_strlen($value) < $min) {
            return $this->fail($field, "Use at least {$min} characters — a short phrase works well.");
        }

        if (preg_match('/^(.)\1+$/', $value) === 1) {
            return $this->fail($field, 'That password is a single repeated character. Please choose something else.');
        }

        $weak = [
            'password', 'password1', 'passw0rd', '12345678', '123456789', '1234567890',
            'qwertyuiop', 'iloveyou', 'letmein', 'welcome123', 'admin123', 'polishedpro',
            'abc12345', 'qwerty123', 'changeme', '11111111', 'password123',
        ];
        if (in_array(strtolower($value), $weak, true)) {
            return $this->fail($field, 'That password appears on common-password lists. Please choose another.');
        }

        $email = (string) ($this->data['email'] ?? '');
        if ($email !== '') {
            $local = strtolower(explode('@', $email)[0]);
            if ($local !== '' && strlen($local) > 3 && str_contains(strtolower($value), $local)) {
                return $this->fail($field, 'Your password should not contain your email address.');
            }
        }

        return true;
    }

    private function assert(bool $passes, string $field, string $message): bool
    {
        return $passes ? true : $this->fail($field, $message);
    }

    private function fail(string $field, string $message): bool
    {
        $this->errors[$field] ??= $message;
        return false;
    }

    private function label(string $field): string
    {
        return $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    /** Register an error discovered outside the rule set (uniqueness, business rules). */
    public function addError(string $field, string $message): self
    {
        $this->errors[$field] ??= $message;
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string, mixed> */
    public function validated(): array
    {
        return $this->validated;
    }

    /** Throw if anything failed — controllers catch this and redirect back. */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors);
        }
        return $this->validated;
    }
}
