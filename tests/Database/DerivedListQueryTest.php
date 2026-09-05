<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Doctrine\ORM\QueryBuilder;
use Modufolio\Panel\Query\AbstractQuery;
use Modufolio\Panel\Table\Column;
use Modufolio\Panel\Table\TableSchema;
use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\DerivedMovieResource;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;
use Modufolio\Panel\Tests\Fixture\Entity\Studio;

/**
 * A resource with no list query class gets one derived from its table, built
 * from the same objects a class would chain — so what a column declares is
 * what the query does.
 */
final class DerivedListQueryTest extends DoctrineTestCase
{
    private function seed(): void
    {
        $warner = (new Studio())->setName('Warner Bros.')->setCity('Burbank');
        $amblin = (new Studio())->setName('Amblin')->setCity('Universal City');

        $rows = [
            ['Heat', 1995, '8.3', $warner],
            ['Jaws', 1975, '8.1', $amblin],
            ['Collateral', 2004, '7.5', $warner],
            ['Jurassic Park', 1993, '8.2', $amblin],
        ];

        $movies = [];

        foreach ($rows as $i => [$title, $year, $rating, $studio]) {
            $movies[] = (new Movie())
                ->setTitle($title)
                ->setYear($year)
                ->setRating($rating)
                ->setStudio($studio)
                ->setCreatedAt(new \DateTimeImmutable(sprintf('2026-01-%02d 10:00:00', $i + 1)));
        }

        $this->persist($warner, $amblin, ...$movies);
        $this->clear();
    }

    private function softDelete(string $title): void
    {
        $movie = self::em()->getRepository(Movie::class)->findOneBy(['title' => $title]);
        self::assertInstanceOf(Movie::class, $movie);

        $movie->setDeletedAt(new \DateTimeImmutable('2026-02-01 12:00:00'));
        self::em()->flush();
        $this->clear();
    }

    /**
     * @param  array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function props(DerivedMovieResource $resource, array $query = []): array
    {
        return $this->renderProps($this->listing($resource, $query, urls: $this->urlGenerator(DerivedMovieResource::class)));
    }

    /**
     * @param  array<string, mixed> $props
     * @return list<string>
     */
    private function titles(array $props): array
    {
        return array_column($props['movies']['data'], 'title');
    }

    public function testTheDefaultOrderComesFromTheTable(): void
    {
        $this->seed();

        self::assertSame(['Collateral', 'Heat', 'Jurassic Park', 'Jaws'], $this->titles($this->props(new DerivedMovieResource())));
    }

    public function testSortabilityFollowsTheColumns(): void
    {
        $this->seed();

        $columns = array_column($this->props(new DerivedMovieResource())['table']['columns'], 'sortable', 'key');

        self::assertTrue($columns['title']);
        self::assertTrue($columns['year']);
        self::assertFalse($columns['rating'], 'Opted out.');
        self::assertFalse($columns['studio'], 'A relation path cannot be ordered by `alias.path`; a class would.');

        $props = $this->props(new DerivedMovieResource(), ['sort' => 'title']);
        self::assertSame(['Collateral', 'Heat', 'Jaws', 'Jurassic Park'], $this->titles($props));

        $props = $this->props(new DerivedMovieResource(), ['sort' => '-rating']);
        self::assertSame(['Collateral', 'Heat', 'Jurassic Park', 'Jaws'], $this->titles($props), 'An unsortable column falls back to the default order.');
    }

    /** A column reading no mapped scalar cannot be ordered by `alias.key`, so it renders unsortable — as it would outside a class's allowlist. */
    public function testAPresenterOnlyColumnIsNotSortable(): void
    {
        $this->seed();

        $resource = new class extends DerivedMovieResource {
            public function table(): TableSchema
            {
                return parent::table()->columns([
                    Column::make('title'),
                    Column::make('studio_label')->text('{{ movie.studio.name }}'),
                ]);
            }
        };

        $columns = array_column($this->props($resource)['table']['columns'], 'sortable', 'key');

        self::assertTrue($columns['title']);
        self::assertFalse($columns['studio_label']);
    }

    public function testASearchableColumnMustReadAMappedField(): void
    {
        $this->seed();

        $resource = new class extends DerivedMovieResource {
            public function table(): TableSchema
            {
                return parent::table()->columns([Column::make('poster_label')->text('x')->searchable()]);
            }
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Column "poster_label" is searchable, but');

        $this->props($resource);
    }

    /** The search covers the searchable columns and joins the to-one a path crosses; the count agrees. */
    public function testSearchCoversSearchableColumnsAcrossAJoin(): void
    {
        $this->seed();

        $props = $this->props(new DerivedMovieResource(), ['search' => 'amblin']);

        self::assertSame(['Jurassic Park', 'Jaws'], $this->titles($props));
        self::assertSame(2, $props['movies']['meta']['total']);

        $props = $this->props(new DerivedMovieResource(), ['search' => 'HEAT']);
        self::assertSame(['Heat'], $this->titles($props), 'Case-insensitive.');
    }

    /** The entity has a deletedAt, so the soft-delete scope applies exactly as a class would chain it. */
    public function testSoftDeletedRowsAreScopedOutUnlessTrashedAsksForThem(): void
    {
        $this->seed();
        $this->softDelete('Heat');

        self::assertSame(['Collateral', 'Jurassic Park', 'Jaws'], $this->titles($this->props(new DerivedMovieResource())));
        self::assertSame(['Collateral', 'Heat', 'Jurassic Park', 'Jaws'], $this->titles($this->props(new DerivedMovieResource(), ['trashed' => 'with'])));
        self::assertSame(['Heat'], $this->titles($this->props(new DerivedMovieResource(), ['trashed' => 'only'])));
    }

    /** queries() chains onto the derived query, narrowing rows and count alike. */
    public function testQueriesAreChainedOntoTheDerivedQuery(): void
    {
        $this->seed();

        $resource = new class extends DerivedMovieResource {
            public function queries(array $params): array
            {
                return [new class extends AbstractQuery {
                    public function apply(QueryBuilder $qb): QueryBuilder
                    {
                        $alias = $this->getRootAlias($qb);

                        return $qb->andWhere("{$alias}.year >= :since")->setParameter('since', 1990);
                    }
                }];
            }
        };

        $props = $this->props($resource);

        self::assertSame(['Collateral', 'Heat', 'Jurassic Park'], $this->titles($props));
        self::assertSame(3, $props['movies']['meta']['total'], 'The count sees the same predicate.');
    }
}
