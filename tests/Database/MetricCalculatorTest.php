<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Doctrine\ORM\QueryBuilder;
use Modufolio\Appkit\Security\User\UserInterface;
use Modufolio\Panel\Metric\Metric;
use Modufolio\Panel\Metric\MetricCalculator;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\Permissions;
use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\DerivedMovieResource;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;
use Modufolio\Panel\Tests\Fixture\Entity\Studio;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * The numbers behind the cards.
 *
 * Every query starts at the same door — the repository, narrowed by
 * `Permissions::scope()` and without the soft-deleted rows — because a metric
 * that counted what its viewer cannot open would leak the shape of the data it
 * is hiding.
 */
final class MetricCalculatorTest extends DoctrineTestCase
{
    use ClockSensitiveTrait;

    private function seed(): void
    {
        $studio = (new Studio())->setName('Warner Bros.')->setCity('Burbank');

        $heat = (new Movie())->setTitle('Heat')->setYear(1995)->setRating('8.3')->setStudio($studio)
            ->setCreatedAt(new \DateTimeImmutable('-2 days 10:00'));
        $jaws = (new Movie())->setTitle('Jaws')->setYear(1975)->setRating('8.1')->setStudio($studio)
            ->setCreatedAt(new \DateTimeImmutable('-2 days 11:00'));
        $alien = (new Movie())->setTitle('Alien')->setYear(1979)->setRating('8.5')->setStudio($studio)
            ->setCreatedAt(new \DateTimeImmutable('today 09:00'));

        // Deleted rows are not part of any number: the listing hides them, and
        // a count that disagreed with the list under it is a bug report.
        $gone = (new Movie())->setTitle('Gone')->setYear(2014)->setRating('7.0')->setStudio($studio)
            ->setCreatedAt(new \DateTimeImmutable('today 09:30'));
        $gone->setDeletedAt(new \DateTimeImmutable('today 10:00'));

        $this->persist($studio, $heat, $jaws, $alien, $gone);
        $this->clear();
    }

    /**
     * Extra rows for one year, so a partition has sizes to order *by*.
     *
     * `seed()` gives one movie per year, which is three tied slices — enough
     * to check that a partition groups, and useless for checking that it
     * orders.
     */
    private function seedYear(int $year, int $extra): void
    {
        $movies = [];

        for ($i = 0; $i < $extra; $i++) {
            $movies[] = (new Movie())
                ->setTitle(sprintf('Filler %d/%d', $year, $i))
                ->setYear($year)
                ->setCreatedAt(new \DateTimeImmutable('-2 days 12:00'));
        }

        $this->persist(...$movies);
        $this->clear();
    }

    /**
     * @param  list<Metric>              $metrics
     * @return list<array<string, mixed>>
     */
    private function compute(array $metrics, ?PanelResource $resource = null): array
    {
        $resource ??= $this->resourceWith($metrics);

        return (new MetricCalculator(self::em(), new Clock()))->compute($resource);
    }

    /** @param list<Metric> $metrics */
    private function resourceWith(array $metrics): PanelResource
    {
        return new class ($metrics) extends DerivedMovieResource {
            /** @param list<Metric> $declared */
            public function __construct(private readonly array $declared) {}

            public function metrics(): array
            {
                return $this->declared;
            }
        };
    }

    public function testAValueCountsTheRowsAViewerCanSee(): void
    {
        $this->seed();

        [$metric] = $this->compute([Metric::value('movies')]);

        self::assertSame('value', $metric['type']);
        self::assertSame('Movies', $metric['label']);
        self::assertSame(3, $metric['value'], 'The soft-deleted row is not counted.');
    }

    public function testAnAggregateReadsTheFieldItNames(): void
    {
        $this->seed();

        [$average] = $this->compute([Metric::value('rating')->average('rating')->decimals(1)]);
        [$highest] = $this->compute([Metric::value('best')->max('rating')]);

        self::assertEqualsWithDelta(8.3, (float) $average['value'], 0.0001);
        self::assertEqualsWithDelta(8.5, (float) $highest['value'], 0.0001);
    }

    public function testAWindowNarrowsTheValueToItsPeriod(): void
    {
        self::mockTime('today 12:00');
        $this->seed();

        [$today] = $this->compute([Metric::value('added')->count()->over('createdAt')->days(1)]);

        self::assertSame(1, $today['value'], 'Only Alien was created today; the deleted row still does not count.');
    }

    public function testAComparisonReportsTheChangeAgainstThePrecedingWindow(): void
    {
        self::mockTime('today 12:00');
        $this->seed();

        // Two days: today (1) against the two days before it (2 on day -2).
        [$metric] = $this->compute([
            Metric::value('added')->count()->over('createdAt')->days(2)->compare(),
        ]);

        self::assertSame(1, $metric['value'], 'Today and yesterday.');
        self::assertSame(2, $metric['previous'], 'The two days before that.');
        self::assertEqualsWithDelta(-50.0, (float) $metric['change'], 0.0001);
    }

    /** A rise from nothing is not a percentage, and saying +100% would be a lie. */
    public function testNoChangeIsReportedWhenThePreviousPeriodWasEmpty(): void
    {
        self::mockTime('today 12:00');
        $this->seed();

        [$metric] = $this->compute([
            Metric::value('added')->count()->over('createdAt')->days(1)->compare(),
        ]);

        self::assertSame(1, $metric['value']);
        self::assertSame(0, $metric['previous']);
        self::assertNull($metric['change']);
    }

    public function testATrendFillsEveryBucketInItsWindow(): void
    {
        self::mockTime('today 12:00');
        $this->seed();

        [$metric] = $this->compute([Metric::trend('added')->count()->over('createdAt')->days(3)]);

        self::assertCount(3, $metric['series'], 'Three days, including the quiet one in the middle.');
        self::assertSame(
            [2, 0, 1],
            array_column($metric['series'], 'value'),
            'Two two days ago, none yesterday, one today.',
        );
        self::assertSame(3, $metric['value'], 'The headline is the window total.');
        self::assertSame((new \DateTimeImmutable('today'))->format('Y-m-d'), $metric['series'][2]['label']);
    }

    public function testAPartitionGroupsAndOrdersBySize(): void
    {
        $this->seed();
        // 1979 → 3, 1995 → 2, 1975 → 1: three distinct sizes, so the assertion
        // below is about ordering rather than about whatever order the engine
        // returned.
        $this->seedYear(1979, 2);
        $this->seedYear(1995, 1);

        [$metric] = $this->compute([Metric::partition('year')->count()]);

        self::assertSame('partition', $metric['type']);
        self::assertCount(3, $metric['slices'], 'One slice per year still standing.');
        self::assertSame(['1979', '1995', '1975'], array_column($metric['slices'], 'label'));
        self::assertSame([3, 2, 1], array_column($metric['slices'], 'value'));
    }

    /**
     * The case that broke the build: three slices of one each.
     *
     * The query groups without ordering, so equal counts arrive in whatever
     * order the engine chose — and it chose differently on MySQL than on
     * PostgreSQL and SQL Server. The label is the tie-break, so every engine
     * now agrees.
     */
    public function testTiedSlicesAreOrderedByLabel(): void
    {
        $this->seed();

        [$metric] = $this->compute([Metric::partition('year')->count()]);

        self::assertSame([1, 1, 1], array_column($metric['slices'], 'value'), 'All tied.');
        self::assertSame(['1975', '1979', '1995'], array_column($metric['slices'], 'label'));
    }

    public function testAPartitionKeepsTheLargestSlicesAndSumsTheRest(): void
    {
        $this->seed();
        // Which two survive has to be a fact about size, not about the order
        // the engine happened to return equal counts in.
        $this->seedYear(1979, 2);
        $this->seedYear(1995, 1);

        [$metric] = $this->compute([Metric::partition('year')->count()->limit(2)]);

        self::assertCount(3, $metric['slices'], 'Two slices, plus what they left over.');
        self::assertSame(['1979', '1995'], array_column(array_slice($metric['slices'], 0, 2), 'label'));
        self::assertSame('Other', $metric['slices'][2]['label']);
        self::assertSame(1, $metric['slices'][2]['value']);
    }

    public function testASlicesColourComesFromTheDeclaration(): void
    {
        $this->seed();

        [$metric] = $this->compute([
            // Keyed by the value as the client sees it. A year is the case
            // where PHP turns that key into an int on the way in, which is why
            // the map is typed by array-key rather than by string.
            Metric::partition('year')->count()->colors(['1995' => 'primary']),
        ]);

        $byLabel = array_column($metric['slices'], null, 'label');

        self::assertSame('primary', $byLabel['1995']['color'] ?? null);
        self::assertArrayNotHasKey('color', $byLabel['1975'], 'A slice with no declared colour carries none.');
    }

    /**
     * The guarantee the whole class hangs on: one door, and it applies the
     * resource's own scope. A metric that ignored it would report a total the
     * viewer's own list contradicts.
     */
    public function testEveryMetricIsNarrowedByTheResourcesScope(): void
    {
        self::mockTime('today 12:00');
        $this->seed();

        $resource = new class extends DerivedMovieResource {
            public function metrics(): array
            {
                return [
                    Metric::value('movies')->count(),
                    Metric::trend('added')->count()->over('createdAt')->days(3),
                    Metric::partition('year')->count(),
                ];
            }

            public function permissions(): Permissions
            {
                return new class extends Permissions {
                    public function scope(QueryBuilder $qb, string $alias, ?UserInterface $user): void
                    {
                        // Arbitrary DQL, which is exactly why metrics are built
                        // on the ORM builder the scope hook is written against.
                        $qb->andWhere("{$alias}.year >= :from")->setParameter('from', 1990);
                    }
                };
            }
        };

        [$value, $trend, $partition] = $this->compute([], $resource);

        self::assertSame(1, $value['value'], 'Only Heat is in scope.');
        self::assertSame(1, $trend['value']);
        self::assertSame(['1995'], array_column($partition['slices'], 'label'));
    }

    public function testAMetricNamingAPathAcrossARelationIsRefused(): void
    {
        $this->seed();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('which is not a field of the entity');

        $this->compute([Metric::partition('studio')->by('studio.name')]);
    }
}
