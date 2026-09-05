<?php

declare(strict_types=1);

namespace Modufolio\Panel\Table;

use Modufolio\Panel\Contracts\HasColorInterface;

/**
 * One column in a {@see TableSchema}.
 *
 * Deliberately **declarative only** — every property must survive
 * json_encode, because the schema crosses an Inertia prop boundary into Vue.
 * That is the hard difference from Filament, whose column API leans on
 * closures (`->tooltip(fn ($record) => ...)`) evaluated server-side per row
 * during a Livewire render. Anything genuinely dynamic belongs either in the
 * presenter (compute the value per row, expose it as a field) or in a
 * `#cell-{key}` slot override on the page.
 *
 * Sortability is *not* declared here — see {@see TableSchema} for why.
 */
final class Column
{
    private string $label;
    private string $type = 'text';
    private ?string $valueKey = null;
    private ?string $urlTemplate = null;
    private bool $showArrow = false;
    private ?string $descriptionKey = null;
    private string $placeholder = '—';
    private bool $linksToRecord = false;
    private bool $sortable = true;
    private bool $toggleable = false;
    private bool $hiddenByDefault = false;
    private ?string $width = null;
    private ?string $format = null;
    private bool $relative = false;

    /** @var list<ColumnAction> */
    private array $actions = [];

    private ?string $weight = null;
    private ?string $align = null;
    private ?string $color = null;
    private ?string $icon = null;
    private ?int $limit = null;
    private bool $copyable = false;
    private ?string $currency = null;
    private ?int $decimals = null;

    /** @var list<Summary> */
    private array $summaries = [];

    private bool $editable = false;
    private ?string $disabledWhen = null;
    private ?string $readOnlyWhen = null;

    /** @var list<array{label: string, value: string, class?: string}>|null */
    private ?array $options = null;

    /** @var array<string, string>|null */
    private ?array $colors = null;

    private ?string $imageSize = null;

    private ?string $imageRounded = null;

    private function __construct(private readonly string $key)
    {
        $this->label = self::humanize($key);
    }

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Render with a specific column component: text, badge, date, boolean,
     * image, icon, color. Unknown types fall back to text on the client.
     */
    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Read the displayed value from a different field, dot-path supported.
     *
     * `Column::make('organization')->value('organization.name')` keeps the
     * column keyed on `organization` (so the sort param and the `#cell-`
     * override slot stay stable) while rendering the nested name.
     */
    public function value(string $path): self
    {
        $this->valueKey = $path;

        return $this;
    }

    /**
     * Link the cell somewhere other than the row's own record.
     *
     * Placeholders are dot-paths against the row, so a drill-down into a
     * related record reads naturally:
     * `->linksTo('/panel/contacts/{id}/organization/{organization.id}')`.
     */
    public function linksTo(string $template): self
    {
        $this->urlTemplate = $template;

        return $this;
    }

    /**
     * Render the link in the accented "drill down" style with a trailing
     * arrow, rather than the muted in-place style used for a row's own record.
     */
    public function arrow(bool $showArrow = true): self
    {
        $this->showArrow = $showArrow;

        return $this;
    }

    /**
     * Render a secondary line underneath, read from another field on the row.
     *
     * The *value* is computed by the presenter rather than by a closure here —
     * a conditional label like "Deleted" is a server decision, and this is how
     * it crosses the JSON boundary intact.
     */
    public function descriptionKey(string $key): self
    {
        $this->descriptionKey = $key;

        return $this;
    }

    /** Text shown when the row's value is null or empty. */
    public function placeholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    /**
     * Make the cell link to the record, opening its drawer. Where that is
     * comes from the table: its show route, or a declared `recordUrl()`.
     * Empty cells render the placeholder unlinked.
     */
    public function linksToRecord(bool $linksToRecord = true): self
    {
        $this->linksToRecord = $linksToRecord;

        return $this;
    }

    /**
     * Opt this column out of sorting even though the list query supports it.
     *
     * There is no positive `sortable()` — a column cannot be made sortable
     * here, only suppressed. See {@see TableSchema::toArray()}.
     */
    public function notSortable(): self
    {
        $this->sortable = false;

        return $this;
    }

    /**
     * How large an image column renders: sm, md, lg, xl. Ignored by every
     * other column type.
     */
    public function size(string $size): self
    {
        $this->imageSize = $size;

        return $this;
    }

    /**
     * How an image column's corners are rounded: none, sm, md, lg, full.
     * The client's default is `full`, which suits an avatar and not a poster.
     */
    public function rounded(string $rounded): self
    {
        $this->imageRounded = $rounded;

        return $this;
    }

    public function toggleable(bool $hiddenByDefault = false): self
    {
        $this->toggleable      = true;
        $this->hiddenByDefault = $hiddenByDefault;

        return $this;
    }

    public function width(string $width): self
    {
        $this->width = $width;

        return $this;
    }

    /** Date display format, for `type('date')` columns. */
    public function format(string $format, bool $relative = false): self
    {
        $this->format   = $format;
        $this->relative = $relative;

        return $this;
    }

    /**
     * Aggregate(s) shown in this column's footer, computed over the whole
     * filtered set rather than the current page.
     *
     * @param Summary|list<Summary> $summaries
     */
    public function summarize(Summary|array $summaries): self
    {
        $this->summaries = is_array($summaries) ? $summaries : [$summaries];

        return $this;
    }

    /** @return list<Summary> */
    public function summaries(): array
    {
        return $this->summaries;
    }

    /** The entity field this column reads, for aggregates and grouping. */
    public function field(): string
    {
        return $this->valueKey ?? $this->key;
    }

    /** Font emphasis: 'medium' or 'bold'. */
    public function weight(string $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    /** Cell alignment: 'left', 'center' or 'right'. */
    public function align(string $align): self
    {
        $this->align = $align;

        return $this;
    }

    /** Static text colour token (primary, success, danger, warning, info, gray). */
    public function color(string $color): self
    {
        $this->color = $color;

        return $this;
    }

    /** Registered icon name rendered before the value. */
    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /** Truncate after N characters; the full value stays in the title attribute. */
    public function limit(int $characters): self
    {
        $this->limit = $characters;

        return $this;
    }

    /** Offer a click-to-copy affordance on the cell. */
    public function copyable(bool $copyable = true): self
    {
        $this->copyable = $copyable;

        return $this;
    }

    /**
     * Action buttons rendered inside every cell of this column, after the
     * value. Row-level actions belong in the table's `actions` slot instead.
     *
     * @param ColumnAction ...$actions
     */
    public function actions(ColumnAction ...$actions): self
    {
        $this->actions = array_values($actions);

        return $this;
    }

    /**
     * Render as currency. Values are assumed to be in major units.
     */
    public function money(string $currency = 'EUR'): self
    {
        $this->type     = 'money';
        $this->currency = $currency;

        return $this;
    }

    /** Render as a fixed-precision number. */
    public function numeric(int $decimals = 0): self
    {
        $this->type     = 'numeric';
        $this->decimals = $decimals;

        return $this;
    }

    /** Render as a tick/cross. */
    public function boolean(): self
    {
        $this->type = 'boolean';

        return $this;
    }

    /**
     * Value → colour map for badges and read-only selects.
     *
     * Accepts a literal map or a backed enum class-string —
     * `->colors(PostStatus::class)` — mirroring {@see options()}: an enum
     * implementing HasColorInterface already knows what each case looks like, so
     * restating it per table is how the two drift apart.
     *
     * @param array<string, string>|class-string $colors
     */
    public function colors(array|string $colors): self
    {
        if (is_string($colors)) {
            $colors = self::colorsFromEnum($colors);
        }

        $this->colors = $colors;

        return $this;
    }

    /**
     * @param  class-string          $enum
     * @return array<string, string>
     */
    private static function colorsFromEnum(string $enum): array
    {
        if (!is_a($enum, \BackedEnum::class, true) || !is_a($enum, HasColorInterface::class, true)) {
            throw new \LogicException(sprintf(
                'Column::colors() was given "%s", which is not a backed enum implementing %s.',
                $enum,
                HasColorInterface::class,
            ));
        }

        $colors = [];

        foreach ($enum::cases() as $case) {
            $colors[(string) $case->value] = $case->getColor();
        }

        return $colors;
    }

    /**
     * Choices for a `select` column.
     *
     * Accepts either a literal option list or a backed enum class-string —
     * `->options(AccountStatus::class)` — mirroring Filament, where naming the
     * enum is enough because the enum carries its own label/colour/class.
     *
     * Options are pure data, so they cross the prop boundary happily; it is
     * the *save* callback that cannot, which is why {@see editable()} only
     * marks the column and the page supplies the handler.
     *
     * @param list<array{label: string, value: string, class?: string}>|class-string $options
     */
    public function options(array|string $options): self
    {
        if (is_string($options)) {
            if (!method_exists($options, 'toOptions')) {
                throw new \LogicException(sprintf(
                    'Column::options() was given "%s", which does not provide toOptions(). Use the ProvidesOptions trait.',
                    $options,
                ));
            }

            $options = $options::toOptions();
        }

        $this->options = $options;

        return $this;
    }

    /**
     * Let the cell be edited in place.
     *
     * The schema says *that* a column is editable; the page says *how* to
     * persist it, by passing a handler under this column's key. A closure here
     * would not survive json_encode.
     */
    public function isEditable(): bool
    {
        return $this->editable;
    }

    public function editable(bool $editable = true): self
    {
        $this->editable = $editable;

        return $this;
    }

    /**
     * Render the control disabled when the named field on the row is truthy,
     * e.g. `->disabledWhen('deleted_at')`.
     */
    public function disabledWhen(string $field): self
    {
        $this->disabledWhen = $field;

        return $this;
    }

    /**
     * Drop the control entirely when the named field is truthy, falling back
     * to a static badge. Stronger than {@see disabledWhen()}, which keeps the
     * control visible but inert.
     */
    public function readOnlyWhen(string $field): self
    {
        $this->readOnlyWhen = $field;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function wantsSorting(): bool
    {
        return $this->sortable;
    }

    /**
     * Whether the cell relies on the table's record URL: it links to the
     * record and names no target of its own.
     */
    public function wantsRecordLink(): bool
    {
        return $this->linksToRecord && $this->urlTemplate === null;
    }

    /**
     * @param bool $sortable resolved against the list query by TableSchema
     * @return array<string, mixed>
     */
    public function toArray(bool $sortable): array
    {
        return array_filter([
            'key'             => $this->key,
            'name'            => $this->key,
            'label'           => $this->label,
            'type'            => $this->type,
            'sortable'        => $sortable,
            'toggleable'      => $this->toggleable,
            'hiddenByDefault' => $this->hiddenByDefault,
            'linksToRecord'   => $this->linksToRecord,
            'valueKey'        => $this->valueKey,
            'urlTemplate'     => $this->urlTemplate,
            'showArrow'       => $this->showArrow,
            'placeholder'     => $this->placeholder,
            'descriptionKey'  => $this->descriptionKey,
            'width'           => $this->width,
            'format'          => $this->format,
            'relative'        => $this->relative,
            'colors'          => $this->colors,
            'weight'          => $this->weight,
            'align'           => $this->align,
            'color'           => $this->color,
            'icon'            => $this->icon,
            'size'            => $this->imageSize,
            'rounded'         => $this->imageRounded,
            'limit'           => $this->limit,
            'copyable'        => $this->copyable,
            'currency'        => $this->currency,
            'decimals'        => $this->decimals,
            'summaries'       => $this->summaries === []
                ? null
                : array_map(static fn(Summary $s): array => $s->toArray(), $this->summaries),
            'options'         => $this->options,
            'editable'        => $this->editable,
            'disabledWhen'    => $this->disabledWhen,
            'readOnlyWhen'    => $this->readOnlyWhen,
            'actions'         => $this->actions === []
                ? null
                : array_map(static fn(ColumnAction $a): array => $a->toArray(), $this->actions),
        ], static fn(mixed $value): bool => $value !== null);
    }

    private static function humanize(string $key): string
    {
        return ucfirst(trim(preg_replace('/[_\-]+/', ' ', $key) ?? $key));
    }
}
