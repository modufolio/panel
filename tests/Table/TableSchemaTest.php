<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Table;

use Doctrine\ORM\QueryBuilder;
use Modufolio\Panel\Query\ListQueryInterface;
use Modufolio\Panel\Table\ChildTable;
use Modufolio\Panel\Table\Column;
use Modufolio\Panel\Table\Filter;
use Modufolio\Panel\Table\TableSchema;
use PHPUnit\Framework\TestCase;

/** A list query that allows sorting on `title` and nothing else. */
final class SortableTitleOnlyQuery implements ListQueryInterface
{
    public static function sortableFields(): array
    {
        return ['title'];
    }

    public static function mapSortField(string $field): ?string
    {
        return $field === 'title' ? 'title' : null;
    }

    public static function defaultSort(): array
    {
        return ['title' => 'ASC'];
    }

    public function apply(QueryBuilder $qb): QueryBuilder
    {
        return $qb;
    }

    public function forCount(QueryBuilder $qb): QueryBuilder
    {
        return $qb;
    }
}

/**
 * The schema is the whole contract between a portal's PHP and the Vue table.
 *
 * The case worth pinning hardest is sortability: a column is sortable only if
 * the *list query* can order by it. Stating it on the column alone would let a
 * schema promise a sort the query cannot perform.
 */
final class TableSchemaTest extends TestCase
{
    public function testAColumnIsSortableOnlyWhenTheListQueryCanOrderByIt(): void
    {
        $schema = TableSchema::make()
            ->columns([Column::make('title'), Column::make('location')])
            ->toArray(SortableTitleOnlyQuery::class);

        self::assertTrue($schema['columns'][0]['sortable'], 'title is in SORTABLE_FIELDS');
        self::assertFalse($schema['columns'][1]['sortable'], 'location is not, so it must not offer sorting');
    }

    public function testAColumnThatOptsOutIsNotSortableEvenWhenTheQueryAllowsIt(): void
    {
        $schema = TableSchema::make()
            ->columns([Column::make('title')->notSortable()])
            ->toArray(SortableTitleOnlyQuery::class);

        self::assertFalse($schema['columns'][0]['sortable']);
    }

    public function testRecordUrlIsCarriedThrough(): void
    {
        $schema = TableSchema::make()
            ->recordUrl('/panel/events/{id}')
            ->toArray(SortableTitleOnlyQuery::class);

        self::assertSame('/panel/events/{id}', $schema['recordUrl']);
    }

    /** Without one, a row has nowhere to point and the drawer never opens. */
    public function testRecordUrlIsNullWhenNotDeclared(): void
    {
        self::assertNull(TableSchema::make()->toArray(SortableTitleOnlyQuery::class)['recordUrl']);
    }

    public function testEmptyStateIsCarriedAsTitleAndDescription(): void
    {
        $schema = TableSchema::make()
            ->emptyState('No events yet', 'Events are added from a contact.')
            ->toArray(SortableTitleOnlyQuery::class);

        self::assertSame('No events yet', $schema['emptyStateTitle']);
        self::assertSame('Events are added from a contact.', $schema['emptyStateDescription']);
    }

    public function testFiltersAreSerialisedInDeclarationOrder(): void
    {
        $schema = TableSchema::make()
            ->filters([Filter::select('type'), Filter::dateRange('when')])
            ->toArray(SortableTitleOnlyQuery::class);

        self::assertSame(['type', 'when'], array_column($schema['filters'], 'key'));
    }

    public function testBulkActionsAreOffUntilAsked(): void
    {
        self::assertFalse(TableSchema::make()->toArray(SortableTitleOnlyQuery::class)['bulkActions']);
        self::assertTrue(TableSchema::make()->bulkActions()->toArray(SortableTitleOnlyQuery::class)['bulkActions']);
    }

    /** Every key the client reads must be present, even when empty. */
    public function testTheShapeIsStableForAnEmptySchema(): void
    {
        $schema = TableSchema::make()->toArray(SortableTitleOnlyQuery::class);

        foreach ([
            'columns', 'filters', 'groups', 'constraints', 'recordUrl',
            'emptyStateTitle', 'emptyStateDescription', 'searchable',
            'bulkActions', 'actions', 'bulkActionItems', 'stickyHeader',
        ] as $key) {
            self::assertArrayHasKey($key, $schema);
        }
    }

    /** Children ride inside the table prop, in the order they were declared. */
    public function testChildrenAreSerialisedInDeclarationOrder(): void
    {
        $schema = TableSchema::make()
            ->children([
                ChildTable::relation('cast', 'Cast'),
                ChildTable::relation('credits', 'Credits'),
            ])
            ->toArray(SortableTitleOnlyQuery::class);

        self::assertSame(['cast', 'credits'], array_column($schema['children'], 'key'));
        self::assertArrayHasKey('children', TableSchema::make()->toArray(SortableTitleOnlyQuery::class));
        self::assertSame([], TableSchema::make()->toArray(SortableTitleOnlyQuery::class)['children']);
    }
}
