<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Modufolio\Panel\Delete\Collector;
use Modufolio\Panel\Delete\DeletionPlan;
use Modufolio\Panel\Delete\OnDelete;
use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\Entity\Actor;
use Modufolio\Panel\Tests\Fixture\Entity\CastMember;
use Modufolio\Panel\Tests\Fixture\Entity\Credit;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;
use Modufolio\Panel\Tests\Fixture\Entity\Studio;
use Modufolio\Panel\Tests\Fixture\Entity\Tag;

/**
 * The collector computes a plan; nothing here executes one.
 *
 * What is pinned is the division the plan exists for: which rows would go,
 * in an order safe to run top to bottom, and — separately — what would stop
 * the whole thing. The fixture graph has one relation per behaviour, so each
 * test can reach for the row that exercises exactly one of them:
 *
 * - CastMember.movie and Credit.movie are declared CASCADE,
 * - CastMember.actor is declared PROTECT,
 * - Credit.castMember is declared RESTRICT,
 * - Movie.studio declares nothing and is inferred SET_NULL from its join
 *   column,
 * - Movie.tags is a many-to-many, which is never a cascade.
 */
final class DeleteCollectorTest extends DoctrineTestCase
{
    private function collect(object $entity): DeletionPlan
    {
        return (new Collector(self::em()))->collect($entity);
    }

    private function studio(): Studio
    {
        return (new Studio())->setName('Warner Bros.');
    }

    private function heat(Studio $studio): Movie
    {
        return (new Movie())->setTitle('Heat')->setStudio($studio);
    }

    /**
     * Heat, with two cast members and a credit pointing at the first of them:
     * every to-one relation in the graph, populated once.
     *
     * @return array{movie: Movie, hanna: CastMember, mccauley: CastMember, credit: Credit}
     */
    private function heatWithCastAndCredit(): array
    {
        $studio  = $this->studio();
        $movie   = $this->heat($studio);
        $pacino  = (new Actor())->setName('Al Pacino');
        $deNiro  = (new Actor())->setName('Robert De Niro');

        $hanna    = (new CastMember())->setActor($pacino)->setCharacter('Vincent Hanna')->setPosition(1);
        $mccauley = (new CastMember())->setActor($deNiro)->setCharacter('Neil McCauley')->setPosition(2);
        $movie->addCastMember($hanna)->addCastMember($mccauley);

        $credit = (new Credit())->setMovie($movie)->setCastMember($hanna)->setNote('Lead');

        $this->persist($studio, $pacino, $deNiro, $movie, $credit);

        return ['movie' => $movie, 'hanna' => $hanna, 'mccauley' => $mccauley, 'credit' => $credit];
    }

    /** @return list<string> the labels of a nested node's children */
    private static function childLabels(DeletionPlan $plan): array
    {
        $labels = [];

        foreach ($plan->nested[0]['children'] as $child) {
            self::assertIsArray($child);
            self::assertIsString($child['label'] ?? null);
            $labels[] = $child['label'];
        }

        sort($labels);

        return $labels;
    }

    /** @param array<mixed> $value */
    private static function containsAnObject(array $value): bool
    {
        foreach ($value as $item) {
            if (is_object($item) || (is_array($item) && self::containsAnObject($item))) {
                return true;
            }
        }

        return false;
    }

    // ── Cascade ──────────────────────────────────────────────────────────────

    /**
     * The order matters as much as the membership: a row carrying a foreign
     * key at the movie has to go before the movie does, and the plan promises
     * a list that is already safe to execute top to bottom.
     */
    public function testDeletingAMovieCascadesIntoItsCastAndCreditsChildrenFirst(): void
    {
        ['movie' => $movie, 'hanna' => $hanna, 'mccauley' => $mccauley, 'credit' => $credit] = $this->heatWithCastAndCredit();

        $plan = $this->collect($movie);

        self::assertFalse($plan->isBlocked());
        self::assertSame([], $plan->protected);
        self::assertCount(4, $plan->deletes);
        self::assertSame($movie, $plan->deletes[3], 'The parent is deleted last.');

        $children = array_slice($plan->deletes, 0, 3);
        self::assertContains($hanna, $children);
        self::assertContains($mccauley, $children);
        self::assertContains($credit, $children);
    }

    public function testTheCascadeIsCountedPerTypeLabel(): void
    {
        ['movie' => $movie] = $this->heatWithCastAndCredit();

        $plan = $this->collect($movie);

        self::assertSame(['Movie' => 1, 'Cast member' => 2, 'Credit' => 1], $plan->counts);
        self::assertSame([], $plan->linkCounts, 'A to-one cascade clears no join rows.');
    }

    /** The nested tree is what a confirmation dialog reads out. */
    public function testTheNestedTreeCarriesReadableLabelsWithChildren(): void
    {
        ['movie' => $movie] = $this->heatWithCastAndCredit();

        $plan = $this->collect($movie);

        self::assertCount(1, $plan->nested);
        self::assertSame('Movie: Heat', $plan->nested[0]['label']);
        self::assertSame('Movie', $plan->nested[0]['type']);
        self::assertSame(
            ['Cast member: Neil McCauley', 'Cast member: Vincent Hanna', 'Credit'],
            self::childLabels($plan),
        );
    }

    /**
     * The browser has no use for entities, so the client shape carries the
     * labels and counts and nothing that could drag an object graph along.
     */
    public function testToArrayIsTheClientShapeWithoutEntityObjects(): void
    {
        ['movie' => $movie] = $this->heatWithCastAndCredit();

        $array = $this->collect($movie)->toArray();

        self::assertSame(['blocked', 'protected', 'nested', 'counts', 'linkCounts'], array_keys($array));
        self::assertFalse($array['blocked']);
        self::assertSame([], $array['protected']);
        self::assertSame(['Movie' => 1, 'Cast member' => 2, 'Credit' => 1], $array['counts']);
        self::assertSame([], $array['linkCounts']);
        self::assertArrayNotHasKey('deletes', $array);
        self::assertArrayNotHasKey('nullifies', $array);
        self::assertFalse(self::containsAnObject($array));
    }

    // ── Protect ──────────────────────────────────────────────────────────────

    /**
     * PROTECT refuses on sight. The plan still lists the actor itself in
     * `deletes` and `counts` — the walk does not stop when it finds a
     * blocker — but a blocked plan is never carried out, so that list is only
     * ever context for the message naming what is in the way.
     */
    public function testDeletingAnActorSomeoneIsCastAsIsProtected(): void
    {
        ['hanna' => $hanna] = $this->heatWithCastAndCredit();
        $pacino = $hanna->getActor();
        self::assertNotNull($pacino);

        $plan = $this->collect($pacino);

        self::assertTrue($plan->isBlocked());
        self::assertSame(['Cast member: Vincent Hanna'], $plan->protected);
        self::assertSame([$pacino], $plan->deletes, 'The actor is listed, but only as context.');
        self::assertSame(['Actor' => 1], $plan->counts);
        self::assertTrue($plan->toArray()['blocked']);
    }

    public function testAnActorNobodyReferencesDeletesCleanly(): void
    {
        $actor = (new Actor())->setName('Val Kilmer');
        $this->persist($actor);

        $plan = $this->collect($actor);

        self::assertFalse($plan->isBlocked());
        self::assertSame([$actor], $plan->deletes);
        self::assertSame(['Actor' => 1], $plan->counts);
        self::assertSame([], $plan->nullifies);
        self::assertSame([['label' => 'Actor: Val Kilmer', 'type' => 'Actor', 'children' => []]], $plan->nested);
    }

    // ── Set null, inferred ───────────────────────────────────────────────────

    /**
     * Movie.studio carries no #[OnDelete]; its join column says `SET NULL`,
     * and a schema that says so means it. The movie keeps its row and loses
     * the reference — it is not a candidate for deletion.
     */
    public function testSetNullIsInferredFromTheJoinColumnWhenNothingIsDeclared(): void
    {
        $studio = $this->studio();
        $movie  = $this->heat($studio);
        $this->persist($studio, $movie);

        $plan = $this->collect($studio);

        self::assertFalse($plan->isBlocked());
        self::assertSame([$studio], $plan->deletes);
        self::assertSame([['entity' => $movie, 'field' => 'studio']], $plan->nullifies);
        self::assertSame(['Studio' => 1], $plan->counts);
    }

    // ── Restrict ─────────────────────────────────────────────────────────────

    /**
     * On its own, a cast member with a credit pointing at it is refused: the
     * credit would survive and outlive the row it refers to.
     */
    public function testDeletingACastMemberACreditPointsAtIsRestricted(): void
    {
        ['hanna' => $hanna] = $this->heatWithCastAndCredit();

        $plan = $this->collect($hanna);

        self::assertTrue($plan->isBlocked());
        self::assertSame(['Credit'], $plan->protected);
        self::assertSame([$hanna], $plan->deletes, 'The credit is not being deleted, which is the whole problem.');
    }

    /**
     * The same credit stops nothing when the movie goes, because the movie's
     * cascade takes the credit with it. This is the case that separates
     * RESTRICT from PROTECT, and why the check is deferred to the end.
     */
    public function testARestrictedReferenceDoesNotBlockWhenItIsCascadedInTheSameOperation(): void
    {
        ['movie' => $movie, 'credit' => $credit] = $this->heatWithCastAndCredit();

        $plan = $this->collect($movie);

        self::assertFalse($plan->isBlocked());
        self::assertContains($credit, $plan->deletes);
    }

    // ── Many-to-many ─────────────────────────────────────────────────────────

    /**
     * The other side of a many-to-many is a peer, not a dependent: the join
     * rows are cleared and counted, and the movies stay exactly where they are.
     */
    public function testDeletingATagUnlinksItsMoviesInsteadOfCascadingIntoThem(): void
    {
        $studio = $this->studio();
        $tag    = (new Tag())->setName('Crime');
        $heat   = $this->heat($studio)->addTag($tag);
        $thief  = (new Movie())->setTitle('Thief')->setStudio($studio)->addTag($tag);
        $this->persist($studio, $tag, $heat, $thief);

        $plan = $this->collect($tag);

        self::assertFalse($plan->isBlocked());
        self::assertSame([$tag], $plan->deletes, 'Neither movie is a candidate for deletion.');
        self::assertSame(['Tag' => 1], $plan->counts);
        self::assertSame(['Movie links' => 2], $plan->linkCounts);

        self::assertCount(2, $plan->nullifies);

        $unlinked = [];

        foreach ($plan->nullifies as $entry) {
            self::assertSame(['entity', 'field', 'unlink'], array_keys($entry));
            self::assertInstanceOf(Movie::class, $entry['entity']);
            self::assertSame('tags', $entry['field']);
            $unlinked[] = $entry['entity']->getTitle();
        }

        self::assertSame(['Heat', 'Thief'], $unlinked);
        self::assertSame([$tag, $tag], array_column($plan->nullifies, 'unlink'), 'Each entry names what to unlink.');
        self::assertSame(['Tag' => 1], $plan->toArray()['counts']);
        self::assertSame(['Movie links' => 2], $plan->toArray()['linkCounts']);
    }

    // ── Repeated references ──────────────────────────────────────────────────

    /**
     * Two credits on the same cast member both point at a row the movie's
     * cascade reaches once. The cast member is counted once, listed once, and
     * neither credit blocks — both are going in the same operation.
     */
    public function testARowReferencedTwiceIsCountedAndListedOnce(): void
    {
        ['movie' => $movie, 'hanna' => $hanna] = $this->heatWithCastAndCredit();
        $second = (new Credit())->setMovie($movie)->setCastMember($hanna)->setNote('Also lead');
        $this->persist($second);

        $plan = $this->collect($movie);

        self::assertFalse($plan->isBlocked());
        self::assertSame(['Movie' => 1, 'Cast member' => 2, 'Credit' => 2], $plan->counts);
        self::assertCount(5, $plan->deletes);
        self::assertCount(
            5,
            array_unique(array_map(spl_object_id(...), $plan->deletes)),
            'No entity is queued for deletion twice.',
        );
        self::assertSame(1, count(array_filter($plan->deletes, static fn (object $row): bool => $row === $hanna)));
    }

    /**
     * Every plan starts from nothing. The cycle guard and the gathered rows
     * are per walk, so one collector can serve a bulk delete or be registered
     * as a service: the first version kept them per instance, and a second
     * call returned the first walk's deletes and counts with the new row
     * appended — and the same row asked twice came back as already seen.
     */
    public function testACollectorCanBeReusedAndEachPlanStartsClean(): void
    {
        $pacino = (new Actor())->setName('Al Pacino');
        $kilmer = (new Actor())->setName('Val Kilmer');
        $this->persist($pacino, $kilmer);

        $collector = new Collector(self::em());
        $first     = $collector->collect($pacino);
        $again     = $collector->collect($pacino);
        $other     = $collector->collect($kilmer);

        self::assertSame([$pacino], $first->deletes);
        self::assertSame([$pacino], $again->deletes, 'Asking again yields the same plan, not an empty one.');
        self::assertSame($first->counts, $again->counts);
        self::assertSame([$kilmer], $other->deletes, 'A different row gets a plan of its own.');
        self::assertSame(['Actor' => 1], $other->counts);
    }

    // ── Labels ───────────────────────────────────────────────────────────────

    /**
     * The type label is the short class name spaced and lower-cased after the
     * first word; the entity label is that plus the first getter that yields
     * a string — and just the type when none does, which is what a Credit
     * (only a `note`) falls back to.
     */
    public function testLabelsHumaniseTheClassNameAndFallBackToTheTypeWithoutAReadableGetter(): void
    {
        ['movie' => $movie, 'credit' => $credit] = $this->heatWithCastAndCredit();

        $plan = $this->collect($movie);

        self::assertArrayHasKey('Cast member', $plan->counts);
        self::assertContains('Cast member: Vincent Hanna', self::childLabels($plan));
        self::assertContains('Credit', self::childLabels($plan), 'No title, name, character or label: the type stands in.');

        self::assertSame(
            [['label' => 'Credit', 'type' => 'Credit', 'children' => []]],
            $this->collect($credit)->nested,
        );
    }

    /**
     * A blank getter value is not a label either: an unnamed studio reads as
     * "Studio", never as "Studio: ".
     */
    public function testAnEmptyGetterValueFallsBackToTheTypeToo(): void
    {
        $studio = new Studio();
        $this->persist($studio);

        self::assertSame('Studio', $this->collect($studio)->nested[0]['label']);
    }

    // ── The attribute ────────────────────────────────────────────────────────

    public function testOnDeleteRejectsAnUnknownBehaviour(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown delete behaviour "drop"');

        new OnDelete('drop');
    }

    public function testOnDeleteAcceptsEveryDeclaredBehaviour(): void
    {
        foreach (OnDelete::ALL as $behaviour) {
            self::assertSame($behaviour, (new OnDelete($behaviour))->behaviour);
        }
    }
}
