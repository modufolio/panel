<?php

declare(strict_types=1);

namespace Modufolio\Panel\Table;

/**
 * One action button rendered inside every cell of a {@see Column}.
 *
 * Declarative only, for the same reason as {@see Column}: the whole schema
 * crosses an Inertia prop boundary, so nothing here may be a closure. An
 * action either navigates (`->url()`, a dot-path template resolved per row) or
 * emits (`->name()`, dispatched to the handler the page registers under that
 * name via `cellActionHandlers`). Per-row visibility is expressed by naming a
 * boolean field on the record rather than by evaluating a callback server-side.
 *
 * Row-level actions are a different thing — those stay in the table's
 * `actions` slot as an ActionGroup.
 */
final class ColumnAction
{
    private string $label;
    private ?string $icon = null;
    private ?string $color = null;
    private ?string $urlTemplate = null;
    private ?string $disabledWhen = null;
    private ?string $hiddenWhen = null;
    private bool $confirm = false;
    private ?string $confirmMessage = null;

    private function __construct(private readonly string $name)
    {
        $this->label = self::humanize($name);
    }

    /**
     * @param string $name Identifies the handler the page registers for this
     *                     action. Also the default label.
     */
    public static function make(string $name): self
    {
        return new self($name);
    }

    /** Accessible name and tooltip. Defaults to a humanised `$name`. */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /** One of the panel's semantic colours: primary, success, danger, … */
    public function color(string $color): self
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Render as a link instead of a button. Supports the same dot-path
     * placeholders as {@see Column::linksTo()}, e.g. `/panel/contacts/{id}`.
     */
    public function url(string $urlTemplate): self
    {
        $this->urlTemplate = $urlTemplate;

        return $this;
    }

    /** Render inert when this field on the row is truthy. */
    public function disabledWhen(string $field): self
    {
        $this->disabledWhen = $field;

        return $this;
    }

    /** Omit the button entirely when this field on the row is truthy. */
    public function hiddenWhen(string $field): self
    {
        $this->hiddenWhen = $field;

        return $this;
    }

    /** Require a confirmation step before the handler is called. */
    public function confirm(?string $message = null): self
    {
        $this->confirm        = true;
        $this->confirmMessage = $message;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'name'           => $this->name,
            'label'          => $this->label,
            'icon'           => $this->icon,
            'color'          => $this->color,
            'urlTemplate'    => $this->urlTemplate,
            'disabledWhen'   => $this->disabledWhen,
            'hiddenWhen'     => $this->hiddenWhen,
            'confirm'        => $this->confirm ?: null,
            'confirmMessage' => $this->confirmMessage,
        ], static fn(mixed $value): bool => $value !== null);
    }

    private static function humanize(string $key): string
    {
        return ucfirst(trim(preg_replace('/[_\-]+/', ' ', $key) ?? $key));
    }
}
