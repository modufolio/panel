<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Modufolio\Panel\Delete\Collector;
use Modufolio\Panel\Delete\DeletionPlan;
use Modufolio\Panel\Delete\PlanExecutor;
use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\Entity\Actor;
use Modufolio\Panel\Tests\Fixture\Entity\CastMember;
use Modufolio\Panel\Tests\Fixture\Entity\Credit;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;
use Modufolio\Panel\Tests\Fixture\Entity\Studio;
use Modufolio\Panel\Tests\Fixture\Entity\Tag;

/**
 * The executor carries a plan out; the collector decided what is in it.
 *
 * Every assertion reads the database back after clearing the identity map,
 * so what is pinned is what the rows look like afterwards — not what the
 * plan said would happen. One case per behaviour the fixture graph has: a
 * cascade, a nullified reference, a cleared many-to-many link, and a blocked
 * plan that must change nothing.
 */
final class PlanExecutorTest extends DoctrineTestCase
{
    private function collect(object $entity): DeletionPlan
    {
        return (new Collector(self::em()))->collect($entity);
    }

    private function apply(object $entity): void
    {
        (new PlanExecutor(self::em()))->apply($this->collect($entity));
    }

    /** @param class-string $class */
    private function rowCount(string $class): int
    {
        return self::em()->getRepository($class)->count([]);
    }

    /** Join rows of `movie_tags`, read through DBAL so no collection state can answer for the table. */
    private function tagLinkCount(): int
    {
        return (int) self::em()->getConnection()->fetchOne('SELECT COUNT(*) FROM movie_tags');
    }

    // ── Fixtures ─────────────────────────────────────────────────────────────

    private function studio(): Studio
    {
        return (new Studio())->setName('Warner Bros.');
    }

    private function heat(Studio $studio): Movie
    {
        return (new Movie())->setTitle('Heat')->setStudio($studio);
    }

    /**
     * Heat, two cast members and a credit pointing at the first of them.
     *
     * @return array{movie: Movie, hanna: CastMember, pacino: Actor}
     */
    private function heatWithCastAndCredit(): array
    {
        $studio = $this->studio();
        $movie  = $this->heat($studio);
        $pacino = (new Actor())->setName('Al Pacino');
        $deNiro = (new Actor())->setName('Robert De Niro');

        $hanna    = (new CastMember())->setActor($pacino)->setCharacter('Vincent Hanna')->setPosition(1);
        $mccauley = (new CastMember())->setActor($deNiro)->setCharacter('Neil McCauley')->setPosition(2);
        $movie->addCastMember($hanna)->addCastMember($mccauley);

        $credit = (new Credit())->setMovie($movie)->setCastMember($hanna)->setNote('Lead');

        $this->persist($studio, $pacino, $deNiro, $movie, $credit);

        return ['movie' => $movie, 'hanna' => $hanna, 'pacino' => $pacino];
    }

    // ── Cascade ──────────────────────────────────────────────────────────────

    public function testApplyingAMoviesPlanRemovesTheMovieItsCastAndItsCredits(): void
    {
        ['movie' => $movie] = $this->heatWithCastAndCredit();

        self::assertSame(2, $this->rowCount(CastMember::class));
        self::assertSame(1, $this->rowCount(Credit::class));

        $this->apply($movie);
        $this->clear();

        self::assertSame(0, $this->rowCount(Movie::class));
        self::assertSame(0, $this->rowCount(CastMember::class));
        self::assertSame(0, $this->rowCount(Credit::class));
        self::assertSame(2, $this->rowCount(Actor::class), 'Actors are peers, not dependents.');
        self::assertSame(1, $this->rowCount(Studio::class), 'The studio is the movie\'s parent, not its child.');
    }

    // ── Set null ─────────────────────────────────────────────────────────────

    public function testApplyingAStudiosPlanNullifiesTheMoviesReferenceAndRemovesTheStudio(): void
    {
        $studio = $this->studio();
        $movie  = $this->heat($studio);
        $this->persist($studio, $movie);
        $movieId = $movie->getId();
        self::assertNotNull($movieId);

        $this->apply($studio);
        $this->clear();

        self::assertSame(0, $this->rowCount(Studio::class));
        self::assertSame(1, $this->rowCount(Movie::class), 'The movie keeps its row.');

        $reloaded = self::em()->find(Movie::class, $movieId);
        self::assertInstanceOf(Movie::class, $reloaded);
        self::assertNull($reloaded->getStudio());
        self::assertSame('Heat', $reloaded->getTitle());
    }

    // ── Many-to-many ─────────────────────────────────────────────────────────

    public function testApplyingATagsPlanUnlinksItFromEveryMovieAndRemovesTheTag(): void
    {
        $studio = $this->studio();
        $crime  = (new Tag())->setName('Crime');
        $drama  = (new Tag())->setName('Drama');
        $heat   = $this->heat($studio)->addTag($crime)->addTag($drama);
        $thief  = (new Movie())->setTitle('Thief')->setStudio($studio)->addTag($crime);
        $this->persist($studio, $crime, $drama, $heat, $thief);
        $heatId  = $heat->getId();
        $thiefId = $thief->getId();
        self::assertNotNull($heatId);
        self::assertNotNull($thiefId);

        self::assertSame(3, $this->tagLinkCount());

        $this->apply($crime);
        $this->clear();

        self::assertSame(1, $this->rowCount(Tag::class), 'Only the deleted tag is gone.');
        self::assertSame(2, $this->rowCount(Movie::class), 'Neither movie was a candidate.');
        self::assertSame(1, $this->tagLinkCount(), 'Only Heat\'s link to Drama survives.');

        $heat  = self::em()->find(Movie::class, $heatId);
        $thief = self::em()->find(Movie::class, $thiefId);
        self::assertInstanceOf(Movie::class, $heat);
        self::assertInstanceOf(Movie::class, $thief);
        self::assertSame(['Drama'], array_map(static fn (Tag $tag): string => $tag->getName(), $heat->getTags()->toArray()));
        self::assertCount(0, $thief->getTags());
    }

    // ── Blocked ──────────────────────────────────────────────────────────────

    /**
     * A blocked plan is refused here too, so a caller cannot execute what the
     * collector said not to — and nothing has moved when it is.
     */
    public function testABlockedPlanIsRefusedAndChangesNothing(): void
    {
        ['pacino' => $pacino] = $this->heatWithCastAndCredit();

        $plan = $this->collect($pacino);
        self::assertTrue($plan->isBlocked());

        try {
            (new PlanExecutor(self::em()))->apply($plan);
            self::fail('A blocked plan must not be applied.');
        } catch (\LogicException $e) {
            self::assertSame(
                'Refusing to apply a blocked deletion plan: Cast member: Vincent Hanna.',
                $e->getMessage(),
            );
        }

        $this->clear();

        self::assertSame(2, $this->rowCount(Actor::class));
        self::assertSame(2, $this->rowCount(CastMember::class));
        self::assertSame(1, $this->rowCount(Credit::class));
        self::assertSame(1, $this->rowCount(Movie::class));
    }

    // ── Afterwards ───────────────────────────────────────────────────────────

    /**
     * The plan runs inside one transaction. That is hard to observe from the
     * outside on a plan that succeeds; what can be pinned is that the
     * transaction was closed properly — the entity manager is still open and
     * the next write goes through.
     */
    public function testTheEntityManagerIsStillOpenAndUsableAfterwards(): void
    {
        ['movie' => $movie] = $this->heatWithCastAndCredit();

        $this->apply($movie);

        $em = self::em();
        self::assertTrue($em->isOpen());
        self::assertFalse($em->getConnection()->isTransactionActive(), 'Nothing is left open.');

        $this->persist((new Actor())->setName('Val Kilmer'));
        $this->clear();

        self::assertSame(3, $this->rowCount(Actor::class));
    }
}
