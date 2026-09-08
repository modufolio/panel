<?php

declare(strict_types=1);

namespace Modufolio\Panel\Form;

/**
 * A bordered group of fields with a heading, inside a form or a tab.
 *
 *     Fieldset::make('Address', help: 'Where invoices go')->fields([
 *         'street' => ['width' => '2/3'],
 *         'number' => ['width' => '1/3'],
 *     ]);
 *
 * Like {@see Tab}, a container and not a field: its entries are flattened
 * into the form carrying this fieldset's key, and the heading travels in the
 * form's layout. A fieldset draws a box; a tab hides what is not selected.
 */
final class Fieldset
{
    /** @var array<int|string, string|\Modufolio\Panel\Blueprint\Separator|Field|array<string, mixed>> */
    private array $entries = [];

    private function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly ?string $help,
    ) {
    }

    public static function make(string $label, ?string $key = null, ?string $help = null): self
    {
        if (trim($label) === '') {
            throw new \InvalidArgumentException('Fieldset::make(): a fieldset needs a label.');
        }

        return new self($key ?? Form::slug($label, 'fieldset'), $label, $help);
    }

    /** @param array<int|string, string|\Modufolio\Panel\Blueprint\Separator|Field|array<string, mixed>> $entries */
    public function fields(array $entries): self
    {
        $clone = clone $this;
        $clone->entries = $entries;

        return $clone;
    }

    public function key(): string
    {
        return $this->key;
    }

    /** @return array<int|string, string|\Modufolio\Panel\Blueprint\Separator|Field|array<string, mixed>> */
    public function entries(): array
    {
        return $this->entries;
    }

    /** @return array{key: string, label: string, help: string|null} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'help' => $this->help];
    }
}
