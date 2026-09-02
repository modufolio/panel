<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Table;

use Modufolio\Panel\Table\ChildTable;
use Modufolio\Panel\Table\Column;
use Modufolio\Panel\Table\Summary;
use PHPUnit\Framework\TestCase;

/**
 * A child table is columns over rows the presented parent row already
 * carries. Its serialised shape is fixed, its rows are never sortable, and
 * anything that would need a query of its own is refused at declaration.
 */
final class ChildTableTest extends TestCase
{
    public function testTheShapeIsFixed(): void
    {
        $child = ChildTable::relation('cast', 'Cast')
            ->columns([Column::make('actor'), Column::make('character')])
            ->recordUrl('/panel/actors/{actor_id}')
            ->empty('No cast listed.');

        $array = $child->toArray();

        self::assertSame(
            ['key', 'label', 'relation', 'source', 'columns', 'recordUrl', 'empty'],
            array_keys($array),
        );
        self::assertSame('cast', $array['key']);
        self::assertSame('Cast', $array['label']);
        self::assertSame('cast', $array['relation']);
        self::assertSame('/panel/actors/{actor_id}', $array['recordUrl']);
        self::assertSame('No cast listed.', $array['empty']);
        self::assertSame(['actor', 'character'], array_column($array['columns'], 'key'));
    }

    public function testOptionalPartsSerialiseAsNullRatherThanDisappearing(): void
    {
        $array = ChildTable::relation('cast', 'Cast')->toArray();

        self::assertNull($array['recordUrl']);
        self::assertNull($array['empty']);
        self::assertSame([], $array['columns']);
    }

    /** Presenters speak snake_case, so a camelCase relation reads a snake_case key. */
    public function testTheSourceDefaultsToTheSnakeCasedRelation(): void
    {
        self::assertSame('cast_members', ChildTable::relation('castMembers', 'Cast')->sourceKey());
        self::assertSame('cast', ChildTable::relation('cast', 'Cast')->sourceKey());
    }

    public function testADeclaredSourceWins(): void
    {
        $child = ChildTable::relation('tags', 'Tags')->source('tag_list');

        self::assertSame('tag_list', $child->sourceKey());
        self::assertSame('tags', $child->relationName());
        self::assertSame('tag_list', $child->toArray()['source']);
    }

    public function testTheKeyDefaultsToTheRelationAndCanBeNamed(): void
    {
        self::assertSame('cast', ChildTable::relation('cast', 'Cast')->key());
        self::assertSame('crew', ChildTable::relation('cast', 'Cast', 'crew')->key());
    }

    /**
     * Sortability is derived from a list query, and a child has none — so a
     * column that would sort on the parent's table is plainly not sortable here.
     */
    public function testChildColumnsAreNeverSortable(): void
    {
        $columns = ChildTable::relation('cast', 'Cast')
            ->columns([Column::make('actor'), Column::make('character')])
            ->toArray()['columns'];

        self::assertSame([false, false], array_column($columns, 'sortable'));
    }

    public function testASummarisedColumnIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('column "fee" declares a summary');

        ChildTable::relation('cast', 'Cast')->columns([Column::make('fee')->summarize(Summary::sum())]);
    }

    public function testAnEditableColumnIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('column "character" is editable');

        ChildTable::relation('cast', 'Cast')->columns([Column::make('character')->editable()]);
    }
}
