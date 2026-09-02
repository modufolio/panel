<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Doctrine\ORM\QueryBuilder;
use Modufolio\Panel\Resource\RecordLocator;
use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;
use Modufolio\Panel\Tests\Fixture\Entity\Studio;
use Modufolio\Panel\Tests\Fixture\MovieResource;
use Ramsey\Uuid\Uuid;

/**
 * The record a route addresses, if this user may reach it at all.
 *
 * Two things are pinned: the lookup is by uuid and nothing else answers, and
 * `scopeQuery()` applies to it exactly as it does to the listing — a record
 * the scope keeps out of the table is not addressable by URL either.
 */
final class RecordLocatorTest extends DoctrineTestCase
{
    private function locator(): RecordLocator
    {
        return new RecordLocator(self::em());
    }

    /** A persisted movie, detached so the lookup has to hit the database. */
    private function persistMovie(string $title, bool $released): Movie
    {
        $studio = (new Studio())->setName($title . ' Studio');
        $movie  = (new Movie())->setTitle($title)->setStudio($studio)->setReleased($released);

        $this->persist($studio, $movie);
        $this->clear();

        return $movie;
    }

    /** A resource whose scope narrows to released movies. */
    private function releasedOnlyResource(): MovieResource
    {
        return new class extends MovieResource {
            public function scopeQuery(object $query, ?object $user = null): void
            {
                if ($query instanceof QueryBuilder) {
                    $query->andWhere('e.released = :scopeReleased')->setParameter('scopeReleased', true);
                }
            }
        };
    }

    // ── find() ───────────────────────────────────────────────────────────────

    public function testFindReturnsTheMovieBehindAUuid(): void
    {
        $heat  = $this->persistMovie('Heat', true);
        $thief = $this->persistMovie('Thief', true);

        $found = $this->locator()->find(new MovieResource(), $heat->getUuid()->toString());

        self::assertInstanceOf(Movie::class, $found);
        self::assertSame($heat->getId(), $found->getId());
        self::assertSame('Heat', $found->getTitle());
        self::assertNotSame($thief->getId(), $found->getId());
    }

    public function testFindReturnsNullForAnUnknownOrMissingUuid(): void
    {
        $this->persistMovie('Heat', true);
        $locator  = $this->locator();
        $resource = new MovieResource();

        self::assertNull($locator->find($resource, Uuid::uuid4()->toString()), 'An unknown uuid.');
        self::assertNull($locator->find($resource, null));
        self::assertNull($locator->find($resource, ''));
    }

    // ── scopeQuery() ─────────────────────────────────────────────────────────

    /**
     * Out of scope reads as not found. A scope that hid rows from the table
     * while leaving them addressable by URL would be decoration.
     */
    public function testAScopedResourceCannotAddressARecordOutsideItsScope(): void
    {
        $released   = $this->persistMovie('Heat', true);
        $unreleased = $this->persistMovie('Heat 2', false);
        $resource   = $this->releasedOnlyResource();

        self::assertNull(
            $this->locator()->find($resource, $unreleased->getUuid()->toString()),
            'The row exists, but not for this resource.',
        );
        $found = $this->locator()->find($resource, $released->getUuid()->toString());
        self::assertInstanceOf(Movie::class, $found);
        self::assertSame($released->getId(), $found->getId());

        self::assertInstanceOf(
            Movie::class,
            $this->locator()->find(new MovieResource(), $unreleased->getUuid()->toString()),
            'Without the scope the same uuid resolves.',
        );
    }

    /** The scope is the resource's chance to ask *who* is looking. */
    public function testScopeQueryReceivesTheQueryBuilderAndTheViewer(): void
    {
        $movie    = $this->persistMovie('Heat', true);
        $resource = new class extends MovieResource {
            public ?object $seenQuery = null;
            public ?object $seenUser = null;

            public function scopeQuery(object $query, ?object $user = null): void
            {
                $this->seenQuery = $query;
                $this->seenUser  = $user;
            }
        };
        $viewer = new \stdClass();

        $found = $this->locator()->find($resource, $movie->getUuid()->toString(), $viewer);

        self::assertInstanceOf(Movie::class, $found);
        self::assertSame($viewer, $resource->seenUser);
        self::assertInstanceOf(QueryBuilder::class, $resource->seenQuery);
        self::assertSame('e', $resource->seenQuery->getRootAliases()[0] ?? null, 'The resource\'s own alias, so a scope can name columns.');
    }

    /** No scope callback runs at all when there is nothing to look up. */
    public function testAnEmptyUuidShortCircuitsBeforeTheScope(): void
    {
        $resource = new class extends MovieResource {
            public bool $scoped = false;

            public function scopeQuery(object $query, ?object $user = null): void
            {
                $this->scoped = true;
            }
        };

        self::assertNull($this->locator()->find($resource, ''));
        self::assertNull($this->locator()->find($resource, null));
        self::assertFalse($resource->scoped);
    }
}
