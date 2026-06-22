<?php

declare(strict_types=1);

namespace App\Helpers;

class Validator
{
    private array $errors = [];
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data, array $rules): self
    {
        $v = new self($data);
        $v->validate($rules);
        return $v;
    }

    private function validate(array $rules): void
    {
        foreach ($rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            $fieldRules = explode('|', $ruleString);

            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }
    }

    private function applyRule(string $field, mixed $value, string $rule): void
    {
        [$ruleName, $param] = array_pad(explode(':', $rule, 2), 2, null);

        match ($ruleName) {
            'required'  => $this->checkRequired($field, $value),
            'string'    => $this->checkString($field, $value),
            'email'     => $this->checkEmail($field, $value),
            'min'       => $this->checkMin($field, $value, (int) $param),
            'max'       => $this->checkMax($field, $value, (int) $param),
            'numeric'   => $this->checkNumeric($field, $value),
            'integer'   => $this->checkInteger($field, $value),
            'boolean'   => $this->checkBoolean($field, $value),
            'array'     => $this->checkArray($field, $value),
            'uuid'      => $this->checkUuid($field, $value),
            'date'      => $this->checkDate($field, $value),
            'in'        => $this->checkIn($field, $value, explode(',', $param ?? '')),
            'url'       => $this->checkUrl($field, $value),
            'nullable'  => null,  // always passes
            default     => null,
        };
    }

    private function checkRequired(string $field, mixed $value): void
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            $this->addError($field, "The $field field is required.");
        }
    }

    private function checkString(string $field, mixed $value): void
    {
        if ($value !== null && !is_string($value)) {
            $this->addError($field, "The $field must be a string.");
        }
    }

    private function checkEmail(string $field, mixed $value): void
    {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, "The $field must be a valid email address.");
        }
    }

    private function checkMin(string $field, mixed $value, int $min): void
    {
        if ($value === null) return;
        $length = is_string($value) ? mb_strlen($value) : (is_numeric($value) ? (float) $value : 0);
        $check  = is_string($value) ? $length < $min : $length < $min;
        if ($check) {
            $this->addError($field, "The $field must be at least $min" . (is_string($value) ? ' characters.' : '.'));
        }
    }

    private function checkMax(string $field, mixed $value, int $max): void
    {
        if ($value === null) return;
        $length = is_string($value) ? mb_strlen($value) : (is_numeric($value) ? (float) $value : PHP_INT_MAX);
        $check  = is_string($value) ? $length > $max : $length > $max;
        if ($check) {
            $this->addError($field, "The $field may not be greater than $max" . (is_string($value) ? ' characters.' : '.'));
        }
    }

    private function checkNumeric(string $field, mixed $value): void
    {
        if ($value !== null && !is_numeric($value)) {
            $this->addError($field, "The $field must be a number.");
        }
    }

    private function checkInteger(string $field, mixed $value): void
    {
        if ($value !== null && filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->addError($field, "The $field must be an integer.");
        }
    }

    private function checkBoolean(string $field, mixed $value): void
    {
        if ($value !== null && !in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true)) {
            $this->addError($field, "The $field must be a boolean.");
        }
    }

    private function checkArray(string $field, mixed $value): void
    {
        if ($value !== null && !is_array($value)) {
            $this->addError($field, "The $field must be an array.");
        }
    }

    private function checkUuid(string $field, mixed $value): void
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        if ($value !== null && !preg_match($pattern, (string) $value)) {
            $this->addError($field, "The $field must be a valid UUID.");
        }
    }

    private function checkDate(string $field, mixed $value): void
    {
        if ($value !== null && strtotime((string) $value) === false) {
            $this->addError($field, "The $field must be a valid date.");
        }
    }

    private function checkIn(string $field, mixed $value, array $options): void
    {
        if ($value !== null && !in_array($value, $options, true)) {
            $this->addError($field, "The $field must be one of: " . implode(', ', $options) . '.');
        }
    }

    private function checkUrl(string $field, mixed $value): void
    {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, "The $field must be a valid URL.");
        }
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        return $this->data;
    }
}
