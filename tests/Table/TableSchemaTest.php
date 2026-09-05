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
    public function sortable(): array
    {
        return ['title'];
    }

    public function mapSort(string $field): ?string
    {
        return $field === 'title' ? 'title' : null;
    }

    public function defaultOrder(): array
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
            ->toArray(new SortableTitleOnlyQuery());

        self::assertTrue($schema['columns'][0]['sortable'], 'title is in SORTABLE_FIELDS');
        self::assertFalse($schema['columns'][1]['sortable'], 'location is not, so it must not offer sorting');
    }

    public function testAColumnThatOptsOutIsNotSortableEvenWhenTheQueryAllowsIt(): void
    {
        $schema = TableSchema::make()
            ->columns([Column::make('title')->notSortable()])
            ->toArray(new SortableTitleOnlyQuery());

        self::assertFalse($schema['columns'][0]['sortable']);
    }

    public function testRecordUrlIsCarriedThrough(): void
    {
        $schema = TableSchema::make()
            ->recordUrl('/panel/events/{id}')
            ->toArray(new SortableTitleOnlyQuery());

        self::assertSame('/panel/events/{id}', $schema['recordUrl']);
    }

    /** Undeclared here means "derive it from the show route", which is the listing's job. */
    public function testRecordUrlIsNullWhenNotDeclared(): void
    {
        self::assertNull(TableSchema::make()->toArray(new SortableTitleOnlyQuery())['recordUrl']);
    }

    public function testWithRecordUrlLeavesTheDeclaredSchemaUntouched(): void
    {
        $declared = TableSchema::make();
        $resolved = $declared->withRecordUrl('/panel/events/{id}');

        self::assertNull($declared->declaredRecordUrl(), 'The resource\'s own schema is not mutated behind its back.');
        self::assertSame('/panel/events/{id}', $resolved->declaredRecordUrl());
        self::assertSame('/panel/events/{id}', $resolved->toArray(new SortableTitleOnlyQuery())['recordUrl']);
    }

    public function testEmptyStateIsCarriedAsTitleAndDescription(): void
    {
        $schema = TableSchema::make()
            ->emptyState('No events yet', 'Events are added from a contact.')
            ->toArray(new SortableTitleOnlyQuery());

        self::assertSame('No events yet', $schema['emptyStateTitle']);
        self::assertSame('Events are added from a contact.', $schema['emptyStateDescription']);
    }

    public function testFiltersAreSerialisedInDeclarationOrder(): void
    {
        $schema = TableSchema::make()
            ->filters([Filter::select('type'), Filter::dateRange('when')])
            ->toArray(new SortableTitleOnlyQuery());

        self::assertSame(['type', 'when'], array_column($schema['filters'], 'key'));
    }

    public function testBulkActionsAreOffUntilAsked(): void
    {
        self::assertFalse(TableSchema::make()->toArray(new SortableTitleOnlyQuery())['bulkActions']);
        self::assertTrue(TableSchema::make()->bulkActions()->toArray(new SortableTitleOnlyQuery())['bulkActions']);
    }

    /** Every key the client reads must be present, even when empty. */
    public function testTheShapeIsStableForAnEmptySchema(): void
    {
        $schema = TableSchema::make()->toArray(new SortableTitleOnlyQuery());

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
            ->toArray(new SortableTitleOnlyQuery());

        self::assertSame(['cast', 'credits'], array_column($schema['children'], 'key'));
        self::assertArrayHasKey('children', TableSchema::make()->toArray(new SortableTitleOnlyQuery()));
        self::assertSame([], TableSchema::make()->toArray(new SortableTitleOnlyQuery())['children']);
    }
}
