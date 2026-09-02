<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;
use Modufolio\Panel\Tests\Fixture\Entity\Studio;
use Modufolio\Panel\Tests\Fixture\MovieResource;

/**
 * The harness itself: every test starts from an empty schema, and a listing
 * built through it renders against real rows.
 */
final class DoctrineTestCaseTest extends DoctrineTestCase
{
    private function movieCount(): int
    {
        return (int) self::em()->createQueryBuilder()
            ->select('COUNT(m.id)')->from(Movie::class, 'm')
            ->getQuery()->getSingleScalarResult();
    }

    public function testEveryTestStartsFromAnEmptySchema(): void
    {
        self::assertSame(0, $this->movieCount());

        $studio = (new Studio())->setName('Warner Bros.');
        $this->persist($studio, (new Movie())->setTitle('Heat')->setStudio($studio));

        self::assertSame(1, $this->movieCount());
    }

    /** Runs after the test above wrote a row, and must not see it. */
    public function testAndTheRowsOfThePreviousTestAreGone(): void
    {
        self::assertSame(0, $this->movieCount());
    }

    public function testAListingRendersTheResourceAgainstRealRows(): void
    {
        $studio = (new Studio())->setName('Warner Bros.');
        $this->persist(
            $studio,
            (new Movie())->setTitle('Heat')->setYear(1995)->setStudio($studio),
            (new Movie())->setTitle('Collateral')->setYear(2004)->setStudio($studio),
        );
        $this->clear();

        $props = $this->renderProps($this->listing(new MovieResource()));

        self::assertSame('Resource/Index', $this->renderer?->component);
        self::assertSame(2, $props['movies']['meta']['total']);
        self::assertSame(['Collateral', 'Heat'], array_column($props['movies']['data'], 'title'));
        self::assertArrayHasKey('auth', $props, 'Shared props ride along with every page.');
    }
}
