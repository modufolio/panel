<?php

declare(strict_types=1);

namespace Modufolio\Panel\Form;

use Modufolio\Panel\Blueprint\Separator;

/**
 * A resource's form: its entries, in display order.
 *
 * Each entry is a mapped field with what the mapping cannot know, a field
 * declared outright by type, or a {@see Separator}. Three spellings of an
 * entry are the same thing:
 *
 *     Form::make()->fields([
 *         'title',                                   // guessed, nothing to add
 *         'contact'   => ['width' => '1/2'],         // guessed, with options
 *         Field::make('notes')->textarea()->help('…'), // a Field is the array with autocomplete
 *         Separator::Line,
 *     ]);
 *
 * Returning one from {@see \Modufolio\Panel\Resource\PanelResource::form()}
 * is also the opt-in for the generated write routes: a resource without a
 * form is index-and-show only, because there is nothing to render or
 * validate against.
 */
final class Form
{
    /** @var list<array{0: string|Separator, 1: array<string, mixed>}> */
    private array $entries = [];

    private function __construct()
    {
    }

    public static function make(): self
    {
        return new self();
    }

    /**
     * The entries, in order. Replaces any declared before.
     *
     * @param array<int|string, string|Separator|Field|array<string, mixed>> $entries
     */
    public function fields(array $entries): self
    {
        $clone = clone $this;
        $clone->entries = self::normalize($entries);

        return $clone;
    }

    /**
     * Every entry as `[key, options]`, with a {@see Separator} in the key's
     * place where one was declared. What the guesser consumes.
     *
     * @return list<array{0: string|Separator, 1: array<string, mixed>}>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /** @return list<string> the keys, separators left out */
    public function keys(): array
    {
        $keys = [];

        foreach ($this->entries as [$key]) {
            if (is_string($key)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param  array<int|string, string|Separator|Field|array<string, mixed>> $entries
     * @return list<array{0: string|Separator, 1: array<string, mixed>}>
     */
    private static function normalize(array $entries): array
    {
        $normalized = [];

        foreach ($entries as $key => $value) {
            if ($value instanceof Field) {
                $normalized[] = [$value->key(), $value->toArray()];
            } elseif ($value instanceof Separator) {
                $normalized[] = [$value, []];
            } elseif (is_int($key)) {
                if (!is_string($value)) {
                    throw new \InvalidArgumentException(
                        'Form: a plain entry must be a field name, a Field or a Separator; use `key => [options]` to pass options.',
                    );
                }

                $normalized[] = [$value, []];
            } else {
                $normalized[] = [$key, is_array($value) ? $value : []];
            }
        }

        return $normalized;
    }

    /**
     * The same entries as a `key => options` map — the shape a nested
     * `fields` option (a repeater's row, a set's members) has always taken.
     * Separators have no place inside a row and are refused.
     *
     * @param  array<int|string, string|Field|array<string, mixed>> $entries
     * @return array<string, array<string, mixed>>
     *
     * @internal used by {@see Field::fields()}
     */
    public static function normalizeOptions(array $entries): array
    {
        $options = [];

        foreach ($entries as $key => $value) {
            if ($value instanceof Field) {
                $options[$value->key()] = $value->toArray();
            } elseif (is_int($key) && is_string($value)) {
                $options[$value] = [];
            } elseif (is_string($key)) {
                $options[$key] = is_array($value) ? $value : [];
            } else {
                throw new \InvalidArgumentException('Nested fields take field names, Fields or `key => [options]`; separators do not belong in a row.');
            }
        }

        return $options;
    }
}
