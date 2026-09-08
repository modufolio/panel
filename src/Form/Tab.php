<?php

declare(strict_types=1);

namespace Modufolio\Panel\Form;

/**
 * A tab of a form: a label, and the entries that render under it.
 *
 *     Form::make()->tabs([
 *         Tab::make('General')->fields(['title', 'contact' => ['width' => '1/2']]),
 *         Tab::make('Notes', icon: 'pencil')->fields([Field::make('notes')->textarea()]),
 *     ]);
 *
 * A container, not a field: the entries stay the form's, flattened in order
 * with each one carrying this tab's key as its `group`, so every path that
 * reads the form — guessing, access, validation, the drawer following the
 * form — sees the same flat list it always did. The tab itself travels in
 * the form's layout, for the client to draw the bar.
 */
final class Tab
{
    /** @var array<int|string, string|\Modufolio\Panel\Blueprint\Separator|Field|Fieldset|array<string, mixed>> */
    private array $entries = [];

    private function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly ?string $icon,
    ) {
    }

    /** @param string|null $key defaults to the label, lower-cased and hyphenated */
    public static function make(string $label, ?string $key = null, ?string $icon = null): self
    {
        if (trim($label) === '') {
            throw new \InvalidArgumentException('Tab::make(): a tab needs a label.');
        }

        return new self($key ?? Form::slug($label, 'tab'), $label, $icon);
    }

    /** @param array<int|string, string|\Modufolio\Panel\Blueprint\Separator|Field|Fieldset|array<string, mixed>> $entries */
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

    /** @return array<int|string, string|\Modufolio\Panel\Blueprint\Separator|Field|Fieldset|array<string, mixed>> */
    public function entries(): array
    {
        return $this->entries;
    }

    /** @return array{key: string, label: string, icon: string|null} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'icon' => $this->icon];
    }
}
