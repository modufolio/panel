<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Modufolio\Panel\Resource\ContainerResourceLocator;
use Modufolio\Panel\Search\GlobalSearch;
use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\DerivedMovieResource;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;
use Modufolio\Panel\Tests\Fixture\Entity\Studio;
use Modufolio\Panel\Tests\Fixture\SearchableMovieResource;
use Psr\Container\ContainerInterface;

/**
 * The search across resources asks each opted-in resource the way its own
 * listing would — same searchable columns, same scope — and answers with
 * a title, details and the record's URL, grouped by resource and bounded.
 */
final class GlobalSearchTest extends DoctrineTestCase
{
    private function seed(): void
    {
        $warner = (new Studio())->setName('Warner Bros.')->setCity('Burbank');
        $amblin = (new Studio())->setName('Amblin')->setCity('Universal City');
        $rows = [
            ['Heat', 1995, $warner],
            ['Jaws', 1975, $amblin],
            ['Collateral', 2004, $warner],
            ['Jurassic Park', 1993, $amblin],
            ['Untitled Spielberg', 2027, $amblin],
        ];
        $movies = [];

        foreach ($rows as [$title, $year, $studio]) {
            $movies[] = (new Movie())->setTitle($title)->setYear($year)->setRating('7.0')->setStudio($studio)
                ->setCreatedAt(new \DateTimeImmutable('2026-01-01'));
        }

        $this->persist($warner, $amblin, ...$movies);
        $this->clear();
    }

    /** @param list<class-string<\Modufolio\Panel\Resource\PanelResource>> $classes */
    private function search(array $classes): GlobalSearch
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => in_array($id, $classes, true));
        $container->method('get')->willReturnCallback(static fn (string $id): object => new $id());

        return new GlobalSearch(self::em(), $this->urlGenerator(...$classes), new ContainerResourceLocator($container), $classes);
    }

    public function testAHitCarriesTitleDetailsAndTheRecordUrl(): void
    {
        $this->seed();

        $result = $this->search([SearchableMovieResource::class])->search('heat', null);

        self::assertSame('heat', $result['query']);
        self::assertCount(1, $result['groups']);
        [$group] = $result['groups'];
        self::assertSame('movies', $group['key']);
        self::assertSame('Movies', $group['label']);
        self::assertSame(1, $group['total']);

        $heat = self::em()->getRepository(Movie::class)->findOneBy(['title' => 'Heat']);
        self::assertNotNull($heat);
        self::assertSame([[
            'title'   => 'Heat',
            'details' => ['1995'],
            'href'    => '/panel/movies/' . $heat->getUuid()->toString(),
        ]], $group['items']);
    }

    public function testEveryWordMustMatchAndTheStudioCounts(): void
    {
        $this->seed();

        $items = $this->search([SearchableMovieResource::class])->search('park amblin', null)['groups'][0]['items'];

        self::assertSame(['Jurassic Park'], array_column($items, 'title'));
    }

    public function testAResourceThatDidNotOptInIsNotSearched(): void
    {
        $this->seed();

        self::assertSame([], $this->search([DerivedMovieResource::class])->search('heat', null)['groups']);
        self::assertSame([], $this->search([SearchableMovieResource::class])->search('   ', null)['groups'], 'Nothing to search for.');
    }

    public function testHitsAreBoundedPerResourceButTheTotalIsHonest(): void
    {
        $this->seed();

        $group = $this->search([SearchableMovieResource::class])->search('a', null, 2)['groups'][0];

        self::assertCount(2, $group['items']);
        self::assertGreaterThan(2, $group['total'], 'More matched than were returned, and it says so.');
    }
}
