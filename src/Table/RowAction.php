<?php

declare(strict_types=1);

namespace Modufolio\Panel\Table;

/**
 * One action offered on a row, declared by the resource rather than written
 * into every listing's `#actions` slot.
 *
 * Same rules as {@see ColumnAction}: pure data, no closures, per-row
 * visibility expressed by naming a boolean field on the record. What this adds
 * is a *behaviour* — the three things a listing always does with a row (open
 * it, edit it, delete it) are named here so the table can service them itself,
 * instead of each page re-typing the drawer push, the visit and the delete
 * confirmation. Anything else is `make()`, dispatched to the handler the page
 * registers under that name.
 *
 * Row actions used to be markup: View/Edit/Delete was ~20 lines of slot in
 * every listing, and the bulk equivalent was a hand-written `confirm()` in
 * each — three copies of the same paragraph before this existed.
 */
final class RowAction
{
    /** Open the row's record in the drawer, via the schema's `recordUrl`. */
    public const BEHAVIOUR_DRAWER = 'drawer';

    /** Ordinary navigation to `url`. */
    public const BEHAVIOUR_VISIT = 'visit';

    /** Confirm, then DELETE `url`; `previewUrl` describes the consequences. */
    public const BEHAVIOUR_DELETE = 'delete';

    /**
     * Open `url` as a dialog on the same stack a drawer uses.
     *
     * The open-ended one. A behaviour is a verb this table knows how to
     * perform, so every new interaction used to need either a new verb here
     * or a handler on the page; a dialog is a *pointer*, and the endpoint on
     * the other end decides what it is. Kirby's panel is built this way and
     * it is why their buttons carry a URL rather than a behaviour.
     */
    public const BEHAVIOUR_DIALOG = 'dialog';

    /** Dispatched to the page's handler for this action's name. */
    public const BEHAVIOUR_HANDLER = 'handler';

    private string $label;

    private ?string $icon = null;

    private ?string $color = null;

    private ?string $urlTemplate = null;

    private ?string $previewUrlTemplate = null;

    private ?string $hiddenWhen = null;

    private ?string $visibleWhen = null;

    private bool $soft = false;

    private bool $confirm = false;

    private ?string $confirmMessage = null;

    private function __construct(
        private readonly string $name,
        private readonly string $behaviour,
    ) {
        $this->label = self::humanize($name);
    }

    /** Open the record's drawer — the row's own URL, so it needs no template. */
    public static function view(string $label = 'View'): self
    {
        return (new self('view', self::BEHAVIOUR_DRAWER))->label($label)->icon('eye');
    }

    /** Navigate to the record's form. */
    public static function edit(string $urlTemplate, string $label = 'Edit'): self
    {
        return (new self('edit', self::BEHAVIOUR_VISIT))
            ->label($label)
            ->icon('edit')
            ->url($urlTemplate);
    }

    /**
     * Delete the record, after a confirmation the server can describe.
     *
     * Give `previewUrl()` when the resource has a delete-preview endpoint and
     * the dialog states consequences instead of asking for a blind guarantee.
     */
    public static function delete(string $urlTemplate, string $label = 'Delete'): self
    {
        return (new self('delete', self::BEHAVIOUR_DELETE))
            ->label($label)
            ->icon('trash')
            ->color('danger')
            ->url($urlTemplate);
    }

    /**
     * Open a dialog route.
     *
     * The route answers like any other drawer-stack URL — it pushes a frame
     * declaring `presentation: 'dialog'` — so the dialog is addressable,
     * deep-linkable and closable by navigation, exactly as a drawer is.
     */
    public static function dialog(string $name, string $urlTemplate): self
    {
        return (new self($name, self::BEHAVIOUR_DIALOG))->url($urlTemplate);
    }

    /**
     * An action the page services itself, dispatched by name — the escape
     * hatch, same as {@see ColumnAction::make()}.
     */
    public static function make(string $name): self
    {
        return new self($name, self::BEHAVIOUR_HANDLER);
    }

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

    /** Dot-path placeholders against the row, e.g. `/panel/movies/{id}/edit`. */
    public function url(string $urlTemplate): self
    {
        $this->urlTemplate = $urlTemplate;

        return $this;
    }

    /**
     * The delete is reversible — the record goes to the trash.
     *
     * Without this the confirmation says "cannot be undone", which for a soft
     * delete is a lie in the frightening direction. Ignored when
     * `previewUrl()` is given: the server's answer wins over a declaration.
     */
    public function soft(bool $soft = true): self
    {
        $this->soft = $soft;

        return $this;
    }

    /** Where to ask what deleting this row would do, before it happens. */
    public function previewUrl(string $urlTemplate): self
    {
        $this->previewUrlTemplate = $urlTemplate;

        return $this;
    }

    /** Omit the action when this field on the row is truthy. */
    public function hiddenWhen(string $field): self
    {
        $this->hiddenWhen = $field;

        return $this;
    }

    /** Offer the action only when this field on the row is truthy. */
    public function visibleWhen(string $field): self
    {
        $this->visibleWhen = $field;

        return $this;
    }

    /**
     * Confirm before acting. Implied by `delete()`, which always confirms.
     */
    public function confirm(?string $message = null): self
    {
        $this->confirm        = true;
        $this->confirmMessage = $message;

        return $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'name'           => $this->name,
            'behaviour'      => $this->behaviour,
            'label'          => $this->label,
            'icon'           => $this->icon,
            'color'          => $this->color,
            'urlTemplate'    => $this->urlTemplate,
            'previewUrl'     => $this->previewUrlTemplate,
            'soft'           => $this->soft ?: null,
            'hiddenWhen'     => $this->hiddenWhen,
            'visibleWhen'    => $this->visibleWhen,
            'confirm'        => $this->confirm ?: null,
            'confirmMessage' => $this->confirmMessage,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private static function humanize(string $key): string
    {
        return ucfirst(trim(preg_replace('/[_\-]+/', ' ', $key) ?? $key));
    }
}
