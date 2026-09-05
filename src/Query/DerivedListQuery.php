<?php

declare(strict_types=1);

namespace Modufolio\Panel\Query;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Modufolio\Panel\Table\Column;
use Modufolio\Panel\Table\TableSchema;

/**
 * The list query a table already describes, so that a resource need not
 * restate it in a class of its own.
 *
 * What a hand-written list query declares in constants and an
 * `applyFilters()` is read off the table instead:
 *
 *  - sortable: the columns that did not opt out with `notSortable()`, each
 *    mapped to the entity property it reads;
 *  - the default order: `TableSchema::defaultSort()`, else the first sortable
 *    column ascending, else the id;
 *  - search: a case-insensitive LIKE across the `searchable()` columns,
 *    joining a to-one relation a path crosses — {@see SearchQuery};
 *  - the soft-delete scope, when the entity has a `deletedAt` —
 *    {@see FilterTrashedQuery}, exactly as a class would chain it.
 *
 * Built from the same {@see QueryInterface} objects a class composes with
 * `chain()`, so the two paths cannot disagree about what a predicate means.
 * A resource adds to the chain with {@see \Modufolio\Panel\Resource\PanelResource::queries()},
 * and names a class with `listQueryClass()` when the chain is not enough.
 */
final class DerivedListQuery extends AbstractQuery implements ListQueryInterface
{
    /**
     * @param array<string, string> $sortable   public key => entity property
     * @param array<string, string> $default    one `property => ASC|DESC`
     * @param list<string>          $searchable paths the search covers
     * @param array<string, string> $sort       requested `key => direction`
     */
    public function __construct(
        private readonly array $sortable,
        private readonly array $default,
        private readonly array $searchable,
        private readonly bool $softDeletes,
        private readonly ?string $search = null,
        private readonly ?string $trashed = null,
        private readonly array $sort = [],
        private readonly ?int $limit = null,
        private readonly ?int $offset = null,
    ) {
    }

    /**
     * @param ClassMetadata<object> $meta   the entity's mapping: what may be sorted and searched
     * @param array<string, mixed>  $params as PanelResource::parseListParams() returns them
     */
    public static function fromTable(?TableSchema $table, ClassMetadata $meta, array $params, ?int $limit, ?int $offset): self
    {
        $columns  = $table?->declaredColumns() ?? [];
        $sortable = [];
        $search   = [];

        foreach ($columns as $column) {
            // Presenters emit snake_case keys, Doctrine properties are camel:
            // `released_on` reads `releasedOn`, as SortQuery has always
            // assumed and the write path already does.
            $field = self::property($column->field());

            // Sortable only when the column reads a mapped scalar: a
            // presenter-only key (`owner`, a computed label) or a path into a
            // relation cannot be ordered by `{alias}.{field}`, and rendered
            // unsortable — exactly as a column outside a class's allowlist
            // always has. A resource that wants either sorts through a class.
            if ($column->wantsSorting() && $meta->hasField($field)) {
                $sortable[$column->key()] = $field;
            }

            if ($column->wantsSearch()) {
                $search[] = self::searchable($meta, $column->key(), $field);
            }
        }

        $default = $table?->declaredDefaultSort();

        if ($default === null) {
            $first   = array_key_first($sortable);
            $default = $first === null ? ['id' => 'ASC'] : [$sortable[$first] => 'ASC'];
        }

        $sort = is_array($params['sort'] ?? null) ? $params['sort'] : [];

        return new self(
            sortable: $sortable,
            default: $default,
            searchable: $search,
            softDeletes: $meta->hasField('deletedAt'),
            search: is_string($params['search'] ?? null) ? $params['search'] : null,
            trashed: is_string($params['trashed'] ?? null) ? $params['trashed'] : null,
            sort: array_filter($sort, 'is_string'),
            limit: $limit,
            offset: $offset,
        );
    }

    /**
     * A searchable column has to read something the query can LIKE: a mapped
     * scalar, or one step into a to-one relation. Anything else is a
     * declaration bug, and says so here rather than as a DQL error later.
     *
     * @param ClassMetadata<object> $meta
     */
    private static function searchable(ClassMetadata $meta, string $key, string $field): string
    {
        [$head] = explode('.', $field, 2);

        if ($meta->hasField($field) || (str_contains($field, '.') && $meta->hasAssociation($head))) {
            return $field;
        }

        throw new \InvalidArgumentException(sprintf(
            'Column "%s" is searchable, but %s maps no field "%s". Point it at a mapped property with ->value(), '
            . 'or search through a list query class.',
            $key,
            $meta->getName(),
            $field,
        ));
    }

    /** `released_on` → `releasedOn`, `studio.legal_name` → `studio.legalName`. */
    private static function property(string $path): string
    {
        return implode('.', array_map(
            static fn (string $segment): string => lcfirst(str_replace('_', '', ucwords($segment, '_'))),
            explode('.', $path),
        ));
    }

    public function sortable(): array
    {
        return array_values(array_unique(array_values($this->sortable)));
    }

    public function defaultOrder(): array
    {
        return $this->default;
    }

    public function mapSort(string $field): ?string
    {
        if (isset($this->sortable[$field])) {
            return $this->sortable[$field];
        }

        // A request may name the property itself, as the keyset navigation
        // does when it reverses the resolved sort.
        return in_array($field, $this->sortable, true) ? $field : null;
    }

    public function apply(QueryBuilder $qb): QueryBuilder
    {
        $qb = $this->forCount($qb);

        $mapped = [];

        foreach ($this->sort as $key => $direction) {
            $property = $this->mapSort($key);

            if ($property !== null) {
                $mapped[$property] = $direction;
            }
        }

        $defaultField = array_key_first($this->default);

        $qb = (new SortQuery(
            $mapped,
            defaultField: $defaultField,
            defaultDirection: $defaultField === null ? 'ASC' : $this->default[$defaultField],
            // Already mapped to properties above: the identity mapping stops
            // SortQuery from snake-casing a property name a second time.
            fieldMapping: array_combine(array_keys($mapped), array_keys($mapped)),
        ))->apply($qb);

        if ($this->limit !== null) {
            $qb->setMaxResults($this->limit);
        }

        if ($this->offset !== null) {
            $qb->setFirstResult($this->offset);
        }

        return $qb;
    }

    public function forCount(QueryBuilder $qb): QueryBuilder
    {
        return $this->chain($qb, [
            ...($this->softDeletes ? [new FilterTrashedQuery($this->trashed)] : []),
            new SearchQuery($this->search, $this->searchable),
        ]);
    }
}
