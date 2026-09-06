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
 * Structure is containers among the entries — a {@see Tab} hides what is not
 * selected, a {@see Fieldset} draws a box — and both flatten: every field
 * still lands in one ordered list, carrying its tab as `group` and its box
 * as `fieldset`, so nothing that reads the form has to know about either.
 * The containers themselves travel in {@see layout()} for the client.
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

    /** @var list<array{key: string, label: string, icon: string|null}> */
    private array $tabs = [];

    /** @var list<array{key: string, label: string, help: string|null}> */
    private array $fieldsets = [];

    private function __construct()
    {
    }

    public static function make(): self
    {
        return new self();
    }

    /**
     * The entries, in order — fields, separators, tabs and fieldsets.
     * Replaces any declared before.
     *
     * @param array<int|string, string|Separator|Field|Tab|Fieldset|array<string, mixed>> $entries
     */
    public function fields(array $entries): self
    {
        $clone = clone $this;
        $clone->entries = [];
        $clone->tabs = [];
        $clone->fieldsets = [];
        $clone->flatten($entries, null, null);

        return $clone;
    }

    /**
     * A form that is tabs from the top: the same as fields() with only tabs
     * in the list, spelled for reading.
     *
     * @param list<Tab> $tabs
     */
    public function tabs(array $tabs): self
    {
        return $this->fields($tabs);
    }

    /**
     * The containers the client draws: tabs in order, fieldsets in order.
     * Empty lists for a flat form.
     *
     * @return array{tabs: list<array{key: string, label: string, icon: string|null}>, fieldsets: list<array{key: string, label: string, help: string|null}>}
     */
    public function layout(): array
    {
        return ['tabs' => $this->tabs, 'fieldsets' => $this->fieldsets];
    }

    /** 'Billing address' → 'billing-address', for a container declared by label alone. */
    public static function slug(string $label): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $label), '-'));

        return $slug === '' ? 'section' : $slug;
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
     * Walk the entries, recording containers and flattening their fields with
     * the container keys attached. A tab inside a tab, or a fieldset inside a
     * fieldset, is refused: the client draws one level of each.
     *
     * @param array<int|string, string|Separator|Field|Tab|Fieldset|array<string, mixed>> $entries
     */
    private function flatten(array $entries, ?string $group, ?string $fieldset): void
    {
        foreach ($entries as $key => $value) {
            if ($value instanceof Tab) {
                if ($group !== null) {
                    throw new \InvalidArgumentException(sprintf('Form: tab "%s" sits inside tab "%s"; tabs do not nest.', $value->key(), $group));
                }

                $this->tabs[] = $value->toArray();
                $this->flatten($value->entries(), $value->key(), $fieldset);

                continue;
            }

            if ($value instanceof Fieldset) {
                if ($fieldset !== null) {
                    throw new \InvalidArgumentException(sprintf('Form: fieldset "%s" sits inside fieldset "%s"; fieldsets do not nest.', $value->key(), $fieldset));
                }

                $this->fieldsets[] = $value->toArray();
                $this->flatten($value->entries(), $group, $value->key());

                continue;
            }

            $placed = [...($group !== null ? ['group' => $group] : []), ...($fieldset !== null ? ['fieldset' => $fieldset] : [])];

            if ($value instanceof Field) {
                $this->entries[] = [$value->key(), [...$placed, ...$value->toArray()]];
            } elseif ($value instanceof Separator) {
                $this->entries[] = [$value, $placed];
            } elseif (is_int($key)) {
                if (!is_string($value)) {
                    throw new \InvalidArgumentException(
                        'Form: a plain entry must be a field name, a Field, a Separator, a Tab or a Fieldset; use `key => [options]` to pass options.',
                    );
                }

                $this->entries[] = [$value, $placed];
            } else {
                $this->entries[] = [$key, [...$placed, ...(is_array($value) ? $value : [])]];
            }
        }
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
