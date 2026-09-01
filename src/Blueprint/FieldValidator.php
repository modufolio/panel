<?php

declare(strict_types=1);

namespace Modufolio\Panel\Blueprint;

/**
 * Validates submitted field values against the rules a blueprint declares.
 *
 * The rule names mirror the client-side rules deliberately
 * (ui/src/Components/Fields/validation.ts), so a field described once behaves
 * the same in both places. The client copy is a convenience that saves a
 * round trip; this is the one that decides.
 *
 * Rules are a map rather than a Laravel-style pipe string — `['max' => 300]`
 * needs no parser and cannot be mistyped into silence the way `'max:300:'`
 * can. A `messages` entry overrides the built-in wording per rule:
 *
 *     'rules' => ['max' => 60, 'messages' => ['max' => 'Keep titles short.']]
 *
 * Conditional fields are validated conditionally: a field whose `when` does
 * not hold is skipped entirely (and {@see stripHidden()} removes its
 * submitted value, so a hidden input cannot smuggle data past the form), and
 * `requiredWhen` makes emptiness an error only while its condition holds.
 */
final class FieldValidator
{
    /**
     * @param list<array<string, mixed>> $blueprint field definitions
     * @param array<string, mixed>       $values    submitted values
     *
     * @return array<string, string> message per field key, empty when valid
     */
    public static function validate(array $blueprint, array $values): array
    {
        $errors = [];

        foreach ($blueprint as $field) {
            $key = (string) ($field['key'] ?? '');

            if ('' === $key || self::hidden($field, $values)) {
                continue;
            }

            $rules = \is_array($field['rules'] ?? null) ? $field['rules'] : [];

            if (isset($field['requiredWhen'])
                && \is_array($field['requiredWhen'])
                && Condition::evaluate($field['requiredWhen'], $values)
            ) {
                $rules = ['required' => true] + $rules;
            }

            if ([] === $rules) {
                continue;
            }

            $message = self::firstError($rules, $values[$key] ?? null, (string) ($field['label'] ?? $key));

            if (null !== $message) {
                $errors[$key] = $message;
            }
        }

        // Structure rows: run each sub-field's rules against every submitted
        // row, addressing failures the way the repeater expects them —
        // `cards.0.title` — so the message lands on the row that caused it.
        foreach ($blueprint as $field) {
            $key = (string) ($field['key'] ?? '');
            $subFields = $field['fields'] ?? null;
            $rows = $values[$key] ?? null;

            if ('' === $key || !\is_array($subFields) || !\is_array($rows) || self::hidden($field, $values)) {
                continue;
            }

            foreach (array_values($rows) as $index => $row) {
                if (!\is_array($row)) {
                    continue;
                }

                foreach ($subFields as $subField) {
                    $subKey = (string) ($subField['key'] ?? '');
                    $subRules = $subField['rules'] ?? null;

                    if ('' === $subKey || !\is_array($subRules)) {
                        continue;
                    }

                    $message = self::firstError(
                        $subRules,
                        $row[$subKey] ?? null,
                        (string) ($subField['label'] ?? $subKey),
                    );

                    if (null !== $message) {
                        $errors[$key.'.'.$index.'.'.$subKey] = $message;
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * The submitted values minus every field whose `when` does not currently
     * hold. Run before persisting: the client never rendered those inputs,
     * so whatever arrived under their keys was not typed into this form.
     *
     * @param list<array<string, mixed>> $blueprint
     * @param array<string, mixed>       $values
     *
     * @return array<string, mixed>
     */
    public static function stripHidden(array $blueprint, array $values): array
    {
        foreach ($blueprint as $field) {
            $key = (string) ($field['key'] ?? '');

            if ('' !== $key && self::hidden($field, $values)) {
                unset($values[$key]);
            }
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $field
     * @param array<string, mixed> $values
     */
    private static function hidden(array $field, array $values): bool
    {
        $when = $field['when'] ?? null;

        return \is_array($when) && !Condition::evaluate($when, $values);
    }

    /**
     * @param array<string, mixed> $rules
     */
    private static function firstError(array $rules, mixed $value, string $label): ?string
    {
        $messages = \is_array($rules['messages'] ?? null) ? $rules['messages'] : [];
        $blank = self::isBlank($value);

        // `required` is the only rule that has an opinion about emptiness. The
        // rest pass on a blank value so an optional field is not reported as
        // malformed merely for being left alone.
        if (($rules['required'] ?? false) === true && $blank) {
            return $messages['required'] ?? sprintf('%s is required.', $label);
        }

        if ($blank) {
            return null;
        }

        $text = \is_string($value) ? $value : (\is_scalar($value) ? (string) $value : '');

        foreach ($rules as $rule => $param) {
            $message = match ($rule) {
                'min' => self::size($value) >= (int) $param
                    ? null
                    : $messages['min'] ?? sprintf('%s must be at least %d.', $label, (int) $param),
                'max' => self::size($value) <= (int) $param
                    ? null
                    : $messages['max'] ?? sprintf('%s must be at most %d.', $label, (int) $param),
                'email' => false !== filter_var($text, \FILTER_VALIDATE_EMAIL)
                    ? null
                    : $messages['email'] ?? sprintf('%s must be a valid email address.', $label),
                'url' => false !== filter_var($text, \FILTER_VALIDATE_URL)
                    ? null
                    : $messages['url'] ?? sprintf('%s must be a valid URL.', $label),
                'integer' => 1 === preg_match('/^-?\d+$/', $text)
                    ? null
                    : $messages['integer'] ?? sprintf('%s must be a whole number.', $label),
                'pattern' => \is_string($param) && 1 === preg_match($param, $text)
                    ? null
                    : $messages['pattern'] ?? sprintf('%s is not in the expected format.', $label),
                default => null,
            };

            if (null !== $message) {
                return $message;
            }
        }

        return null;
    }

    private static function isBlank(mixed $value): bool
    {
        return null === $value
            || '' === $value
            || (\is_array($value) && [] === $value);
    }

    /** One measure for text, numbers and collections, as the client rules use. */
    private static function size(mixed $value): int
    {
        if (\is_int($value) || \is_float($value)) {
            return (int) $value;
        }

        if (\is_array($value)) {
            return \count($value);
        }

        return mb_strlen((string) $value);
    }
}
