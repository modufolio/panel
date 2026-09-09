<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Metric;

use Modufolio\Panel\Contracts\HasColorInterface;
use Modufolio\Panel\Metric\Metric;
use Modufolio\Panel\Table\Summary;
use PHPUnit\Framework\TestCase;

enum Genre: string implements HasColorInterface
{
    case Drama = 'drama';
    case SciFi = 'sci_fi';

    public function getColor(): string
    {
        return $this === self::Drama ? 'primary' : 'info';
    }
}

/**
 * A metric is a declaration on its way to the client, and an instruction on
 * its way to the calculator. These pin both: the JSON shape the card renders
 * from, and the refusals that keep an uncomputable declaration from reaching
 * the database.
 */
final class MetricTest extends TestCase
{
    public function testTheLabelIsHumanisedFromTheKeyUntilOneIsGiven(): void
    {
        self::assertSame('Average rating', Metric::value('average_rating')->toArray()['label']);
        self::assertSame('Ratings', Metric::value('average_rating')->label('Ratings')->toArray()['label']);
    }

    public function testAPlainCountNeedsNoAggregate(): void
    {
        $metric = Metric::value('movies');

        self::assertSame(Summary::COUNT, $metric->aggregate()->type());
        self::assertNull($metric->field(), 'A count aggregates no field.');
    }

    public function testAnAggregateCarriesTheFieldItReads(): void
    {
        $metric = Metric::value('takings')->sum('price');

        self::assertSame(Summary::SUM, $metric->aggregate()->type());
        self::assertSame('price', $metric->field());
    }

    public function testAPartitionGroupsByItsKeyUntilByOverridesIt(): void
    {
        self::assertSame('genre', Metric::partition('genre')->groupField());
        self::assertSame('category', Metric::partition('genre')->by('category')->groupField());
    }

    public function testTheWindowNamesItsBucket(): void
    {
        $daily = Metric::trend('added')->over('createdAt')->days(30);

        self::assertSame('day', $daily->bucket());
        self::assertSame(30, $daily->windowLength());

        // Either/or, not both: the last one declared is the window.
        $monthly = Metric::trend('added')->over('createdAt')->days(30)->months(6);

        self::assertSame('month', $monthly->bucket());
        self::assertSame(6, $monthly->windowLength());
    }

    public function testATrendWithoutADateFieldIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('it needs the date field it walks and a window');

        Metric::trend('added')->days(30)->validate();
    }

    public function testATrendWithoutAWindowIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('a window');

        Metric::trend('added')->over('createdAt')->validate();
    }

    /** Nothing to compare against, so the card would have to invent a change. */
    public function testAComparisonWithoutAWindowIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('needs a window to compare');

        Metric::value('movies')->count()->compare()->validate();
    }

    public function testAnAggregateOverNoFieldIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('aggregates no field');

        Metric::value('takings')->sum('')->validate();
    }

    public function testColoursComeFromABackedEnumsOwnDeclaration(): void
    {
        self::assertSame(
            ['drama' => 'primary', 'sci_fi' => 'info'],
            Metric::partition('genre')->colors(Genre::class)->colorMap(),
        );
    }

    public function testColoursFromAnEnumWithoutTheInterfaceAreRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('not a backed enum implementing');

        Metric::partition('genre')->colors(self::class);
    }

    /** The declaration crosses the prop boundary; the numbers are added later. */
    public function testUnsetPropertiesAreOmittedRatherThanSentAsNull(): void
    {
        self::assertSame(
            ['key' => 'movies', 'type' => 'value', 'label' => 'Movies'],
            Metric::value('movies')->toArray(),
        );

        self::assertSame(
            [
                'key'      => 'takings',
                'type'     => 'value',
                'label'    => 'Takings',
                'icon'     => 'chart',
                'currency' => 'EUR',
                'decimals' => 2,
                'bucket'   => 'month',
                'window'   => 12,
            ],
            Metric::value('takings')->sum('price')->icon('chart')->money()->decimals(2)
                ->over('createdAt')->months(12)->toArray(),
        );
    }
}
