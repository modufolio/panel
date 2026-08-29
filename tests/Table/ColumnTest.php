<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Table;

use Modufolio\Panel\Contracts\HasColorInterface;
use Modufolio\Panel\Table\Column;
use Modufolio\Panel\Table\Summary;
use PHPUnit\Framework\TestCase;

enum Status: string implements HasColorInterface
{
    case Active = 'active';
    case Closed = 'closed';

    public function getColor(): string
    {
        return $this === self::Active ? 'success' : 'gray';
    }

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    /** @return list<array{label: string, value: string}> */
    public static function toOptions(): array
    {
        return array_map(
            static fn (self $case): array => ['label' => $case->getLabel(), 'value' => $case->value],
            self::cases(),
        );
    }
}

/**
 * A column is pure data on its way to the client. These tests pin the JSON
 * shape rather than the fluent API, because the shape is the contract the Vue
 * table reads — renaming a key here breaks a renderer in another repository.
 */
final class ColumnTest extends TestCase
{
    public function testAColumnCarriesItsKeyUnderBothNames(): void
    {
        $column = Column::make('title')->toArray(sortable: false);

        // `name` is the legacy spelling the client still reads; both must agree
        // or a column sorts under one name and renders under another.
        self::assertSame('title', $column['key']);
        self::assertSame('title', $column['name']);
    }

    public function testTheLabelIsHumanisedFromTheKeyWhenNotGiven(): void
    {
        // Sentence case, not title case: 'created_at' → 'Created at'.
        self::assertSame('Created at', Column::make('created_at')->toArray(false)['label']);
        self::assertSame('When', Column::make('starts_at')->label('When')->toArray(false)['label']);
    }

    /** Nulls are stripped, so a column declares only what it changed. */
    public function testUnsetPropertiesAreOmittedRatherThanSentAsNull(): void
    {
        $column = Column::make('title')->toArray(false);

        self::assertArrayNotHasKey('icon', $column);
        self::assertArrayNotHasKey('colors', $column);
        self::assertArrayNotHasKey('currency', $column);
    }

    public function testSortabilityIsDecidedByTheCallerNotTheColumn(): void
    {
        self::assertTrue(Column::make('title')->toArray(sortable: true)['sortable']);
        self::assertFalse(Column::make('title')->toArray(sortable: false)['sortable']);
    }

    /**
     * Both are needed for a clickable row: recordUrl says where a row points,
     * linksToRecord opts a cell into being the link. A listing shipped without
     * the second rendered rows that looked right and did nothing.
     */
    public function testLinksToRecordIsOptInPerColumn(): void
    {
        // Present either way — only nulls are stripped, and false is a real
        // answer here. The client reads it as a boolean, so it must be one.
        self::assertFalse(Column::make('title')->toArray(false)['linksToRecord']);
        self::assertTrue(Column::make('title')->linksToRecord()->toArray(false)['linksToRecord']);
    }

    public function testMoneyCarriesItsCurrency(): void
    {
        $column = Column::make('total')->money('GBP')->toArray(false);

        self::assertSame('money', $column['type']);
        self::assertSame('GBP', $column['currency']);
    }

    public function testNumericCarriesItsPrecision(): void
    {
        $column = Column::make('weight')->numeric(3)->toArray(false);

        self::assertSame('numeric', $column['type']);
        self::assertSame(3, $column['decimals']);
    }

    /** Naming the enum is enough: it already carries its own labels. */
    public function testOptionsCanComeFromABackedEnum(): void
    {
        $column = Column::make('status')->options(Status::class)->toArray(false);

        self::assertSame(
            [['label' => 'Active', 'value' => 'active'], ['label' => 'Closed', 'value' => 'closed']],
            $column['options'],
        );
    }

    public function testOptionsFromAClassWithoutToOptionsIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/toOptions/');

        Column::make('status')->options(\stdClass::class);
    }

    public function testSummariesAreSerialisedWithTheColumn(): void
    {
        $column = Column::make('total')->summarize(Summary::count('Rows'))->toArray(false);

        self::assertCount(1, $column['summaries']);
        self::assertSame('Rows', $column['summaries'][0]['label']);
    }

    public function testAColumnWithNoSummariesOmitsTheKeyEntirely(): void
    {
        self::assertArrayNotHasKey('summaries', Column::make('total')->toArray(false));
    }
}
