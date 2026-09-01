<?php

declare(strict_types=1);

namespace Modufolio\Panel\Resource;

/**
 * A section of a resource's drawer.
 *
 * Three kinds, because the drawers in this panel only ever show three:
 *
 * - {@see details()} — the record's own scalar values as the definition grid,
 *   optionally followed by relation lists it names as {@see sections()}.
 * - {@see relation()} — a child collection or to-many relation, as rows.
 * - {@see custom()} — content only the page can render (an upload dropzone,
 *   a gallery). The declaration still owns the tab's label and count, so the
 *   tab bar stays server-authored; only the body is the page's business.
 *
 * A resource declaring no tabs keeps the flat grid, so this is additive.
 *
 * Pure data: counts are read from the presented record at render time, so a
 * tab never touches the database and never sees an entity.
 */
final class DrawerTab
{
    private string $emptyText = 'Nothing here yet.';

    private ?string $primaryKey = null;

    private ?string $secondaryKey = null;

    private ?string $categoryKey = null;

    /** @var array<string, string|null> key => label override */
    private array $fields = [];

    /** @var list<self|string> */
    private array $sections = [];

    private bool $addable = false;

    private string $addLabel = '+ Add';

    private bool $deletable = false;

    private ?string $recordUrl = null;

    private string $navigation = 'drawer';

    private string $variant = 'list';

    private function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $type,
        private readonly ?string $source = null,
    ) {
    }

    /** The record's own values, rendered as the definition grid. */
    public static function details(string $label = 'Details', string $key = 'details'): self
    {
        return new self($key, $label, 'details');
    }

    /**
     * A list of related rows read from `$source` in the presented record.
     *
     * The source is a key the resource's `presentOne()` already returns — the
     * tab adds no query of its own, which is what keeps the drawer a single
     * round trip.
     */
    public static function relation(string $source, string $label, ?string $key = null): self
    {
        return new self($key ?? $source, $label, 'relation', $source);
    }

    /**
     * A tab whose body the page renders itself.
     *
     * The escape hatch, in the same spirit as `indexComponent()`: declaring
     * the tab keeps the bar consistent (label, order, count) while the page
     * fills a slot named after the key. `$source` is optional and only feeds
     * the badge.
     */
    public static function custom(string $key, string $label, ?string $source = null): self
    {
        return new self($key, $label, 'custom', $source);
    }

    /**
     * Which of the record's values this grid shows, in this order.
     *
     * Omitted, a details tab shows everything the record carries, which is
     * right for a generated resource nobody has curated. Naming fields is what
     * lets a record be *split* across grids — a user's identity in one tab and
     * their access in another — instead of one tab showing all of it.
     *
     * Accepts a list of keys, or `key => label` where the humanised key is
     * not the wording the drawer used ('created_at' → 'Created').
     *
     * @param array<int|string, string> $fields
     */
    public function fields(array $fields): self
    {
        $normalized = [];

        foreach ($fields as $key => $value) {
            if (is_int($key)) {
                $normalized[$value] = null;
            } else {
                $normalized[$key] = $value;
            }
        }

        $clone = clone $this;
        $clone->fields = $normalized;

        return $clone;
    }

    /**
     * Relation lists shown inline beneath the details grid.
     *
     * Accepts either the key of another declared tab — reusing its label,
     * keys and empty text rather than repeating them — or a declaration of its
     * own, for a list that belongs under the details grid but does not deserve
     * a tab (contacts' addresses).
     */
    public function sections(self|string ...$sections): self
    {
        $clone = clone $this;
        $clone->sections = array_values($sections);

        return $clone;
    }

    /**
     * Offer an "add" action on this list.
     *
     * What adding *means* is the page's business: the generic page opens the
     * record's edit form, where the repeater or multiselect editing the
     * relation lives; a bespoke page opens its own nested form. The
     * declaration says only that the action exists, and the page hides it from
     * anyone who cannot edit.
     */
    public function addable(string $label = '+ Add'): self
    {
        $clone = clone $this;
        $clone->addable  = true;
        $clone->addLabel = $label;

        return $clone;
    }

    /**
     * Render rows as separate cards rather than one bordered list. Suits a
     * short list with per-row actions; the list suits a dense sub-section.
     */
    public function cards(): self
    {
        $clone = clone $this;
        $clone->variant = 'cards';

        return $clone;
    }

    /** Offer a per-row remove action, which the page wires to its own call. */
    public function deletable(): self
    {
        $clone = clone $this;
        $clone->deletable = true;

        return $clone;
    }

    /**
     * Where a row drills to, as a pattern over `{parent}` (the record's id)
     * and `{id}` (the row's).
     *
     * Held as a pattern rather than resolved per row because the declaration
     * is built once, before any row is in hand — the same reason
     * `TableSchema::recordUrl()` takes one.
     */
    public function recordUrl(string $pattern, string $navigation = 'drawer'): self
    {
        if (!in_array($navigation, ['drawer', 'visit'], true)) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown row navigation "%s"; expected "drawer" or "visit".',
                $navigation,
            ));
        }

        $clone = clone $this;
        $clone->recordUrl  = $pattern;
        $clone->navigation = $navigation;

        return $clone;
    }

    /** Row's main line. Defaults to the first string value the row carries. */
    public function primary(string $key): self
    {
        $clone = clone $this;
        $clone->primaryKey = $key;

        return $clone;
    }

    /** Row's muted second value, shown after the primary. */
    public function secondary(string $key): self
    {
        $clone = clone $this;
        $clone->secondaryKey = $key;

        return $clone;
    }

    /**
     * A short classifying value shown *before* the primary, in a fixed column.
     *
     * The distinction is not decoration: a repeated category (an address's
     * Office/Home) scans best in its own column, while a qualifier belonging
     * to the row's subject (a connection's "Partner") reads as trailing
     * detail. Declaring which one a value is keeps a generic row renderer from
     * having to guess — and guessing produced "Partner  Demarcus Gutkowski".
     */
    public function category(string $key): self
    {
        $clone = clone $this;
        $clone->categoryKey = $key;

        return $clone;
    }

    public function empty(string $text): self
    {
        $clone = clone $this;
        $clone->emptyText = $text;

        return $clone;
    }

    public function key(): string
    {
        return $this->key;
    }

    /**
     * The client-facing shape for a whole declaration, with section references
     * resolved and counts read from the record.
     *
     * Resolution happens here rather than in {@see toArray()} because a tab
     * naming a sibling by key cannot see its siblings on its own.
     *
     * @param  list<self>                 $tabs
     * @param  array<string, mixed>       $record
     * @param  list<array<string, mixed>> $formFields
     * @return list<array<string, mixed>>
     */
    public static function collect(array $tabs, array $record, array $formFields = []): array
    {
        $byKey = [];
        foreach ($tabs as $tab) {
            $byKey[$tab->key] = $tab;
        }

        return array_map(
            static function (self $tab) use ($byKey, $record, $formFields): array {
                $declaration = $tab->toArray($record);

                if (($declaration['addable'] ?? false) === true) {
                    $declaration = [...$declaration, ...self::addFormFor($tab, $formFields)];
                }

                if ($tab->type !== 'details') {
                    return $declaration;
                }

                $sections = [];
                foreach ($tab->sections as $section) {
                    $resolved = $section instanceof self ? $section : ($byKey[$section] ?? null);

                    if ($resolved === null) {
                        throw new \LogicException(sprintf(
                            'Drawer tab "%s" lists section "%s", but no tab declares that key. '
                            . 'Name a declared tab, or pass a DrawerTab for a section that is not one.',
                            $tab->key,
                            $section,
                        ));
                    }

                    $sectionDeclaration = $resolved->toArray($record);

                    if (($sectionDeclaration['addable'] ?? false) === true) {
                        $sectionDeclaration = [
                            ...$sectionDeclaration,
                            ...self::addFormFor($resolved, $formFields),
                        ];
                    }

                    $sections[] = $sectionDeclaration;
                }

                return [...$declaration, 'sections' => $sections];
            },
            $tabs,
        );
    }

    /**
     * The form a list's add action opens, taken from the resource's *own* form
     * declaration rather than declared a second time.
     *
     * A repeater contributes its row's fields — adding one cast entry asks
     * exactly what editing one does. A to-many contributes itself narrowed to a
     * single choice: attaching one tag is picking one, not editing the set.
     *
     * Returns the fields *and* the form key they write to. Those differ: a tab
     * may read from a display copy (`tag_list`) while the field that edits the
     * relation is the form's (`tags`), and posting to the display key is a 404.
     *
     * Empty fields mean the page has nothing to render and should fall back to
     * sending the user to the full form.
     *
     * @param  list<array<string, mixed>> $formFields
     * @return array{addFields: list<array<string, mixed>>, addTarget: ?string}
     */
    private static function addFormFor(self $tab, array $formFields): array
    {
        foreach ($formFields as $field) {
            $key = (string) ($field['key'] ?? '');

            if ($key !== $tab->key && $key !== $tab->source) {
                continue;
            }

            if (($field['type'] ?? null) === 'repeater') {
                return [
                    'addFields' => self::rowFields($field['fields'] ?? null),
                    'addTarget' => $key,
                ];
            }

            if (($field['type'] ?? null) === 'multiselect') {
                // The set's control asks for many; adding asks for one, so the
                // same relation is offered as a single lookup.
                return ['addTarget' => $key, 'addFields' => [[
                    ...$field,
                    'type'     => 'belongs-to',
                    'required' => true,
                    'width'    => 'full',
                    'props'    => [
                        ...($field['props'] ?? []),
                        // Always stated, not only for the searchable path: the
                        // lookup defaults to id/name, while every option this
                        // panel resolves is {value,label} — the mismatch reads
                        // as a list of blank rows rather than as an error.
                        'valueKey'  => 'value',
                        'labelKey'  => 'label',
                        'clearable' => false,
                    ],
                ]]];
            }
        }

        return ['addFields' => [], 'addTarget' => null];
    }

    /**
     * A repeater's row declarations, or nothing when it carries none.
     *
     * @return list<array<string, mixed>>
     */
    private static function rowFields(mixed $fields): array
    {
        if (!is_array($fields)) {
            return [];
        }

        $rows = [];

        foreach ($fields as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed> $record
     * @return array<string, mixed>
     */
    public function toArray(array $record): array
    {
        $tab = [
            'key'   => $this->key,
            'label' => $this->label,
            'type'  => $this->type,
        ];

        if ($this->type === 'details') {
            return $this->fields === [] ? $tab : [...$tab, 'fields' => $this->fields];
        }

        $rows = $this->source === null ? [] : ($record[$this->source] ?? []);
        $rows = is_array($rows) ? array_values($rows) : [];

        return [
            ...$tab,
            'source'    => $this->source,
            'primary'   => $this->primaryKey,
            'secondary' => $this->secondaryKey,
            'category'  => $this->categoryKey,
            'empty'     => $this->emptyText,
            'addable'   => $this->addable,
            'addLabel'  => $this->addLabel,
            'deletable' => $this->deletable,
            'recordUrl' => $this->recordUrl,
            'navigation' => $this->navigation,
            'variant'   => $this->variant,
            // A zero badge is rendered as no badge, matching what the bespoke
            // drawers already do with `|| null`.
            'badge'     => count($rows) ?: null,
        ];
    }
}
