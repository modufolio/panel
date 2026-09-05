<?php

declare(strict_types=1);

namespace Modufolio\Panel\Table;

use Modufolio\Appkit\Toolkit\Str;

/**
 * Related rows shown under a parent row, in a nested table.
 *
 * The read half of master–detail: a movie's cast under the movie, an order's
 * lines under the order. A child names a to-many association of the listed
 * entity and the columns to show for each related row; the rows themselves
 * come from the presented parent row, under `source`, exactly as a drawer's
 * relation tab reads them — the child adds no query of its own, and
 * {@see \Modufolio\Panel\Resource\ResourceListing} loads the association for
 * the whole page in one query because the schema declared it.
 *
 * Deliberately columns, not a nested {@see TableSchema}: a child has no list
 * query, so filters, groups, constraints, summaries, search and bulk actions
 * could not work, and what cannot work is better left undeclarable than
 * validated away. Rows are never sortable for the same reason.
 */
final class ChildTable
{
    /** @var list<Column> */
    private array $columns = [];

    private ?string $source = null;
    private ?string $recordUrl = null;
    private ?string $empty = null;

    private function __construct(
        private readonly string $relation,
        private readonly string $label,
        private readonly string $key,
    ) {
    }

    /**
     * @param string      $relation The parent entity's association name, validated
     *                              against Doctrine's metadata when the listing renders.
     * @param string|null $key      Client-side identity; defaults to the relation.
     */
    public static function relation(string $relation, string $label, ?string $key = null): self
    {
        return new self($relation, $label, $key ?? $relation);
    }

    /**
     * The key in the presented parent row that holds the child rows.
     *
     * Defaults to the snake_cased relation, which is how presenters name
     * things (`castMembers` → `cast_members`); name it when the presenter
     * emits a display copy under another key.
     */
    public function source(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    /**
     * @param list<Column> $columns
     */
    public function columns(array $columns): self
    {
        foreach ($columns as $column) {
            if ($column->summaries() !== []) {
                throw new \LogicException(sprintf(
                    'Child table "%s": column "%s" declares a summary, but a child has no query to aggregate over.',
                    $this->key,
                    $column->key(),
                ));
            }

            if ($column->isEditable()) {
                throw new \LogicException(sprintf(
                    'Child table "%s": column "%s" is editable, but a nested table has no save path.',
                    $this->key,
                    $column->key(),
                ));
            }
        }

        $this->columns = $columns;

        return $this;
    }

    /**
     * Where a child row leads. Placeholders resolve against the child row,
     * plus `{parent}` for the parent row's id — the drawer-tab convention.
     */
    public function recordUrl(string $template): self
    {
        $this->recordUrl = $template;

        return $this;
    }

    public function declaredRecordUrl(): ?string
    {
        return $this->recordUrl;
    }

    /** @return list<Column> */
    public function declaredColumns(): array
    {
        return $this->columns;
    }

    /** What the nested table says when the parent has no related rows. */
    public function empty(string $text): self
    {
        $this->empty = $text;

        return $this;
    }

    /** The parent entity's association name. */
    public function relationName(): string
    {
        return $this->relation;
    }

    /** The presented-row key the rows are read from. */
    public function sourceKey(): string
    {
        return $this->source ?? Str::snake($this->relation);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    /**
     * The client shape. Fixed keys, no null-filtering, so the TypeScript
     * type is stable; columns serialise as never sortable.
     *
     * @return array{
     *     key: string,
     *     label: string,
     *     relation: string,
     *     source: string,
     *     columns: list<array<string, mixed>>,
     *     recordUrl: string|null,
     *     empty: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'key'       => $this->key,
            'label'     => $this->label,
            'relation'  => $this->relation,
            'source'    => $this->sourceKey(),
            'columns'   => array_map(static fn (Column $column): array => $column->toArray(false), $this->columns),
            'recordUrl' => $this->recordUrl,
            'empty'     => $this->empty,
        ];
    }
}
