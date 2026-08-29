<?php

declare(strict_types=1);

namespace Modufolio\Panel\Table;

/**
 * A grouping the user can switch the table into.
 *
 * Purely declarative: the server orders rows by the group field so they
 * cluster, and the client draws a heading row whenever the value changes.
 */
final class Group
{
    private string $label;

    private function __construct(
        private readonly string $key,
        private readonly string $field,
    ) {
        $this->label = ucfirst(trim(preg_replace('/[_\-]+/', ' ', $key) ?? $key));
    }

    public static function make(string $key, ?string $field = null): self
    {
        return new self($key, $field ?? $key);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    /** Entity property to order by — hardcoded, never read from the request. */
    public function field(): string
    {
        return $this->field;
    }

    /**
     * @return array{value: string, label: string}
     */
    public function toOption(): array
    {
        return ['value' => $this->key, 'label' => $this->label];
    }
}
