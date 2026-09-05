<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Doctrine\ORM\QueryBuilder;
use Modufolio\Panel\Resource\Permissions;
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
 * `Permissions::scope()` applies to it exactly as it does to the listing — a record
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
        return $this->withPermissions(new class extends Permissions {
            public function scope(QueryBuilder $qb, string $alias, ?object $user): void
            {
                $qb->andWhere("{$alias}.released = :scopeReleased")->setParameter('scopeReleased', true);
            }
        });
    }

    private function withPermissions(Permissions $permissions): MovieResource
    {
        return new class ($permissions) extends MovieResource {
            public function __construct(private readonly Permissions $permissions)
            {
            }

            public function permissions(): Permissions
            {
                return $this->permissions;
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

    // ── scope() ──────────────────────────────────────────────────────────────

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
    public function testScopeReceivesTheQueryBuilderItsAliasAndTheViewer(): void
    {
        $movie       = $this->persistMovie('Heat', true);
        $permissions = new class extends Permissions {
            public ?QueryBuilder $seenQuery = null;
            public ?string $seenAlias = null;
            public ?object $seenUser = null;

            public function scope(QueryBuilder $qb, string $alias, ?object $user): void
            {
                $this->seenQuery = $qb;
                $this->seenAlias = $alias;
                $this->seenUser  = $user;
            }
        };
        $viewer = new \stdClass();

        $found = $this->locator()->find($this->withPermissions($permissions), $movie->getUuid()->toString(), $viewer);

        self::assertInstanceOf(Movie::class, $found);
        self::assertSame($viewer, $permissions->seenUser);
        self::assertInstanceOf(QueryBuilder::class, $permissions->seenQuery);
        self::assertSame('e', $permissions->seenAlias, 'The resource\'s own alias, so a scope can name columns.');
        self::assertSame('e', $permissions->seenQuery->getRootAliases()[0] ?? null);
    }

    /** No scope callback runs at all when there is nothing to look up. */
    public function testAnEmptyUuidShortCircuitsBeforeTheScope(): void
    {
        $permissions = new class extends Permissions {
            public bool $scoped = false;

            public function scope(QueryBuilder $qb, string $alias, ?object $user): void
            {
                $this->scoped = true;
            }
        };
        $resource = $this->withPermissions($permissions);

        self::assertNull($this->locator()->find($resource, ''));
        self::assertNull($this->locator()->find($resource, null));
        self::assertFalse($permissions->scoped);
    }
}
