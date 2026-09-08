<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Modufolio\Panel\Table\Column;
use Modufolio\Panel\Table\TableSchema;
use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\DerivedMovieResource;

/**
 * What the mapping already knows, filled into the table's columns.
 *
 * The form has read the entity since the beginning; the table did not, so an
 * enum was spelled out again per listing or read as its stored value —
 * `sci_fi` in a grey cell, beside a timestamp printed as an ISO literal.
 * Every guess here is last in line: a declaration always wins.
 */
final class ColumnGuesserTest extends DoctrineTestCase
{
    /**
     * @param  list<Column> $columns
     * @return array<string, array<string, mixed>>
     */
    private function columns(array $columns): array
    {
        $resource = new class ($columns) extends DerivedMovieResource {
            /** @param list<Column> $columns */
            public function __construct(private readonly array $columns)
            {
            }

            public function table(): TableSchema
            {
                return TableSchema::make()->columns($this->columns);
            }
        };

        $props = $this->renderProps($this->listing($resource, urls: $this->urlGenerator(DerivedMovieResource::class)));

        return array_column($props['table']['columns'], null, 'key');
    }

    /** An enum that knows its colours is a badge, labelled by its own cases. */
    public function testAnEnumColumnWithColoursBecomesABadge(): void
    {
        $genre = $this->columns([Column::make('genre')])['genre'];

        self::assertSame('badge', $genre['type']);
        self::assertSame(
            ['drama' => 'info', 'sci_fi' => 'primary', 'comedy' => 'success'],
            $genre['colors'],
        );
        self::assertSame(
            ['Drama', 'Science fiction', 'Comedy'],
            array_column($genre['options'], 'label'),
        );
    }

    /** No colours, no badge — but the cell still reads the case, not the stored value. */
    public function testAnEnumColumnWithoutColoursKeepsItsTypeAndGainsItsLabels(): void
    {
        $audience = $this->columns([Column::make('audience')])['audience'];

        self::assertSame('text', $audience['type']);
        self::assertArrayNotHasKey('colors', $audience, 'Nothing to colour it with.');
        self::assertSame(['Family', 'Adults only'], array_column($audience['options'], 'label'));
    }

    /** A date column reads as a date rather than as the ISO literal the server sent. */
    public function testADateColumnReadsAsADate(): void
    {
        $columns = $this->columns([Column::make('released_on'), Column::make('premiere_at')]);

        self::assertSame('date', $columns['released_on']['type']);
        self::assertSame('date', $columns['premiere_at']['type'], 'A datetime is a date to the client, which formats it.');
    }

    /** Everything here is a fallback: what the resource wrote down stands. */
    public function testADeclarationAlwaysWins(): void
    {
        $columns = $this->columns([
            Column::make('genre')->type('text')->colors(['drama' => 'danger']),
            Column::make('released_on')->type('text'),
        ]);

        self::assertSame('text', $columns['genre']['type']);
        self::assertSame(['drama' => 'danger'], $columns['genre']['colors']);
        self::assertSame('text', $columns['released_on']['type']);
    }

    /** Nothing to read a presenter-only key or a relation path from. */
    public function testAColumnTheEntityDoesNotMapIsLeftAlone(): void
    {
        $columns = $this->columns([
            Column::make('studio')->value('studio.name'),
            Column::make('genre_label'),
        ]);

        self::assertSame('text', $columns['studio']['type']);
        self::assertArrayNotHasKey('options', $columns['genre_label']);
    }
}
