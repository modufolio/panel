<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Modufolio\Panel\Form\FormResolver;
use Modufolio\Panel\Form\SubmissionHandler;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\Permissions;
use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\Entity\Actor;
use Modufolio\Panel\Tests\Fixture\Entity\CastMember;
use Modufolio\Panel\Tests\Fixture\Entity\Genre;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;
use Modufolio\Panel\Tests\Fixture\Entity\Studio;
use Modufolio\Panel\Tests\Fixture\Entity\Tag;
use Modufolio\Panel\Tests\Fixture\MovieResource;
use Modufolio\Panel\Tests\Fixture\StubListQuery;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Validator\Validation;

/**
 * A submission from the request body to the row, and back out again.
 *
 * The handler is the one place where the declaration, the validator, the
 * relation lookups and Doctrine's association mappings all meet, so these
 * tests run it against the real thing: the fixture Movie with a column of
 * every kind, a body shaped exactly as the panel's form would send it, and a
 * reload after `clear()` so what is asserted is what the database holds and
 * not what the in-memory object still remembers.
 *
 * Errors are asserted as whole arrays wherever a body is meant to fail on
 * one field only — an extra key would mean a rule fired that the test did
 * not intend, and that is a finding too.
 */
final class SubmissionHandlerTest extends DoctrineTestCase
{
    // ── Building blocks ──────────────────────────────────────────────────────

    /**
     * A handler over a fresh resolver each time. The resolver memoises forms
     * by class name, and an anonymous resource class is the same class on
     * every call to the helper that declares it — so a test that builds its
     * own form must not share a resolver with one that built another.
     */
    private function handler(): SubmissionHandler
    {
        return new SubmissionHandler(
            new FormResolver(self::em()),
            self::em(),
            Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(),
        );
    }

    private function studio(string $name): Studio
    {
        $studio = (new Studio())->setName($name);
        $this->persist($studio);

        return $studio;
    }

    private function tag(string $name): Tag
    {
        $tag = (new Tag())->setName($name);
        $this->persist($tag);

        return $tag;
    }

    private function actor(string $name): Actor
    {
        $actor = (new Actor())->setName($name);
        $this->persist($actor);

        return $actor;
    }

    /**
     * A persisted movie with a studio (so the entity's NotNull is satisfied
     * before any form touches it) and, optionally, cast rows in order.
     *
     * @param list<array{string, Actor}> $cast character and actor, in position order
     */
    private function movie(string $title, Studio $studio, array $cast = []): Movie
    {
        $movie = (new Movie())->setTitle($title)->setStudio($studio);

        foreach ($cast as $position => [$character, $actor]) {
            $movie->addCastMember(
                (new CastMember())->setCharacter($character)->setActor($actor)->setPosition($position),
            );
        }

        $this->persist($movie);

        return $movie;
    }

    /**
     * Every key the guessed Movie form reads, filled in as the panel would
     * send it — strings throughout, the toggle as '1', the relations as
     * uuids. Overrides replace keys; a null override removes one, for the
     * tests about what a missing key means.
     *
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function body(Studio $studio, array $overrides = []): array
    {
        $body = [
            'title'       => 'Heat',
            'synopsis'    => 'A crew and the detective on their trail.',
            'year'        => '1995',
            'runtime'     => '170',
            'released'    => '1',
            'released_on' => '1995-12-15',
            'studio_id'   => $studio->getUuid()->toString(),
            'tags'        => [],
            'cast'        => [],
        ];

        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($body[$key]);
            } else {
                $body[$key] = $value;
            }
        }

        return $body;
    }

    /**
     * The movie as the database now holds it, with the identity map emptied
     * first so nothing asserted comes from the object the handler mutated.
     */
    private function reload(Movie $movie): Movie
    {
        $id = $movie->getId();
        self::assertNotNull($id, 'The movie was flushed.');

        $this->clear();

        $found = self::em()->find(Movie::class, $id);
        self::assertInstanceOf(Movie::class, $found);

        return $found;
    }

    /**
     * The cast in collection order, reduced to what the sync decides.
     *
     * @return list<array{character: string, actor: ?string, position: int}>
     */
    private function castOf(Movie $movie): array
    {
        $rows = [];

        foreach ($movie->getCast() as $member) {
            $rows[] = [
                'character' => $member->getCharacter(),
                'actor'     => $member->getActor()?->getName(),
                'position'  => $member->getPosition(),
            ];
        }

        return $rows;
    }

    /** @return list<string> tag names, sorted */
    private function tagNamesOf(Movie $movie): array
    {
        $names = [];

        foreach ($movie->getTags() as $tag) {
            $names[] = $tag->getName();
        }

        sort($names);

        return $names;
    }

    /** @param class-string $entityClass */
    private function rowsIn(string $entityClass): int
    {
        return self::em()->getRepository($entityClass)->count([]);
    }

    /**
     * A Movie resource with the given form in place of the fixture's.
     *
     * @param array<int|string, string|array<string, mixed>> $fields
     */
    private function movieWithForm(array $fields): MovieResource
    {
        return new class ($fields) extends MovieResource {
            /** @param array<int|string, string|array<string, mixed>> $fields */
            public function __construct(private readonly array $fields)
            {
            }

            public function formFields(): array
            {
                return $this->fields;
            }
        };
    }

    /** A resource over Studio with its two columns guessed, for the unique `name`. */
    private function studioResource(): PanelResource
    {
        return new class extends PanelResource {
            public function key(): string
            {
                return 'studios';
            }

            public function entityClass(): string
            {
                return Studio::class;
            }

            public function listQueryClass(): string
            {
                return StubListQuery::class;
            }

            public function formFields(): array
            {
                return ['name', 'city'];
            }

            public function present(array $entities): array
            {
                return [];
            }
        };
    }

    /**
     * A Movie form of only the keys named, guessed — for the columns the fixture resource's own form leaves out.
     *
     * @param array<int|string, mixed> $keys
     */
    private function movieResourceWith(array $keys): PanelResource
    {
        return new class($keys) extends PanelResource {
            /** @param array<int|string, mixed> $keys */
            public function __construct(private readonly array $keys) {}

            public function key(): string
            {
                return 'movies';
            }

            public function entityClass(): string
            {
                return Movie::class;
            }

            public function listQueryClass(): string
            {
                return StubListQuery::class;
            }

            public function formFields(): array
            {
                return $this->keys;
            }

            public function present(array $entities): array
            {
                return [];
            }
        };
    }

    // ── Enum columns ─────────────────────────────────────────────────────────

    /** The select submits the case's value; the setter wants the case. An empty choice is no case at all. */
    public function testAnEnumColumnIsSetFromTheSubmittedValueAndClearedByABlank(): void
    {
        // Reloaded so the movie and its studio are managed: this form does
        // not name the studio, so nothing re-resolves it the way body() does.
        $movie    = $this->reload($this->movie('Heat', $this->studio('Warner')));
        $resource = $this->movieResourceWith(['title', 'genre']);

        self::assertSame([], $this->handler()->handle($resource, $movie, ['title' => 'Heat', 'genre' => 'sci_fi']));

        $movie = $this->reload($movie);
        self::assertSame(Genre::SCI_FI, $movie->getGenre());

        self::assertSame([], $this->handler()->handle($resource, $movie, ['title' => 'Heat', 'genre' => '']));
        self::assertNull($this->reload($movie)->getGenre());
    }

    // ── Creating ─────────────────────────────────────────────────────────────

    /**
     * The body arrives as strings and identifiers; the row must hold typed
     * values and real associations. One create exercising every conversion
     * the guessed form implies, read back from the database.
     */
    public function testACompleteBodyCreatesAMovieWithTypedValuesAndItsRelations(): void
    {
        $studio = $this->studio('Warner Bros.');
        $crime  = $this->tag('Crime');
        $drama  = $this->tag('Drama');
        $deNiro = $this->actor('Robert De Niro');
        $pacino = $this->actor('Al Pacino');
        $movie  = new Movie();

        $errors = $this->handler()->handle(new MovieResource(), $movie, $this->body($studio, [
            'tags' => [$crime->getUuid()->toString(), $drama->getUuid()->toString()],
            'cast' => [
                ['character' => 'Neil McCauley', 'actor_id' => $deNiro->getUuid()->toString()],
                ['character' => 'Vincent Hanna', 'actor_id' => $pacino->getUuid()->toString()],
            ],
        ]));

        self::assertSame([], $errors);

        $saved = $this->reload($movie);

        self::assertSame('Heat', $saved->getTitle());
        self::assertSame('A crew and the detective on their trail.', $saved->getSynopsis());
        self::assertSame(1995, $saved->getYear(), 'An integer column receives an int, not the string.');
        self::assertSame(170, $saved->getRuntime());
        self::assertTrue($saved->isReleased(), "'1' is true.");
        self::assertSame('1995-12-15', $saved->getReleasedOn()?->format('Y-m-d'), 'The date string reached the DateTimeImmutable setter as a date.');
        self::assertSame('Warner Bros.', $saved->getStudio()?->getName(), 'The uuid was swapped for the studio it names.');
        self::assertSame(['Crime', 'Drama'], $this->tagNamesOf($saved));
        self::assertSame([
            ['character' => 'Neil McCauley', 'actor' => 'Robert De Niro', 'position' => 0],
            ['character' => 'Vincent Hanna', 'actor' => 'Al Pacino', 'position' => 1],
        ], $this->castOf($saved), 'Rows in submitted order, each pointing at its actor.');
    }

    /**
     * A decimal field is coerced to float so min/max compare the number, but
     * the fixture's `setRating()` takes the string Doctrine hands back for a
     * decimal column. The setter's signature decides: the number arrives as
     * a string. The first version handed the float on, which under strict
     * types was a TypeError rather than a saved rating.
     */
    public function testADecimalValueMeetsAStringSetterAsAString(): void
    {
        $studio = $this->studio('Warner Bros.');
        $movie  = new Movie();

        self::assertSame([], $this->handler()->handle(new MovieResource(), $movie, $this->body($studio, ['rating' => '8.3'])));

        self::assertSame('8.3', $this->reload($movie)->getRating());
    }

    /**
     * An optional text left empty is "no synopsis", not a synopsis that
     * happens to be empty; the setter accepting null is what decides.
     */
    public function testABlankOptionalStringIsStoredAsNull(): void
    {
        $studio = $this->studio('Warner Bros.');
        $movie  = new Movie();

        self::assertSame([], $this->handler()->handle(new MovieResource(), $movie, $this->body($studio, ['synopsis' => ''])));

        self::assertNull($this->reload($movie)->getSynopsis());
    }

    /**
     * An empty number input is a number nobody typed. Casting it would store
     * 0, which for a year is a value and a wrong one.
     */
    public function testABlankNumberMeansNotProvidedRatherThanZero(): void
    {
        $studio = $this->studio('Warner Bros.');
        $movie  = new Movie();

        self::assertSame([], $this->handler()->handle(new MovieResource(), $movie, $this->body($studio, ['year' => '', 'runtime' => ''])));

        $saved = $this->reload($movie);

        self::assertNull($saved->getYear());
        self::assertNull($saved->getRuntime());
    }

    /**
     * An unchecked switch sends nothing at all. The column behind it is a
     * non-nullable bool, so "missing" has to land as false — null would be
     * a database error and "not provided" would leave the previous value.
     */
    public function testAMissingToggleIsFalse(): void
    {
        $studio = $this->studio('Warner Bros.');
        $movie  = new Movie();

        self::assertSame([], $this->handler()->handle(new MovieResource(), $movie, $this->body($studio, ['released' => null])));

        self::assertFalse($this->reload($movie)->isReleased());
    }

    // ── Updating ─────────────────────────────────────────────────────────────

    /**
     * Editing a repeater is a diff against the children the record already
     * has, keyed by each row's `id` (the child's uuid): a row that names an
     * existing child updates it in place, one that names none is a new
     * child, and a child the submission no longer mentions is gone — which
     * orphanRemoval turns into a delete. Position is always the submitted
     * order, so a reorder is an update like any other.
     */
    public function testAnUpdateReconcilesCastRowsByTheirIdentity(): void
    {
        $studio    = $this->studio('Warner Bros.');
        $deNiro    = $this->actor('Robert De Niro');
        $pacino    = $this->actor('Al Pacino');
        $kilmer    = $this->actor('Val Kilmer');
        $brenneman = $this->actor('Amy Brenneman');

        $movie = $this->movie('Heat', $studio, [
            ['Neil', $deNiro],
            ['Vincent', $pacino],
            ['Chris', $kilmer],
        ]);

        [$neil, $vincent] = $movie->getCast()->toArray();

        $errors = $this->handler()->handle(new MovieResource(), $movie, $this->body($studio, [
            'title' => 'Heat (Director\'s Cut)',
            'cast'  => [
                // Vincent first now, and renamed.
                ['id' => $vincent->getUuid()->toString(), 'character' => 'Lt. Vincent Hanna', 'actor_id' => $pacino->getUuid()->toString()],
                // Neil kept as he was.
                ['id' => $neil->getUuid()->toString(), 'character' => 'Neil', 'actor_id' => $deNiro->getUuid()->toString()],
                // Chris is missing; Eady is new.
                ['character' => 'Eady', 'actor_id' => $brenneman->getUuid()->toString()],
            ],
        ]));

        self::assertSame([], $errors);

        $neilUuid    = $neil->getUuid()->toString();
        $vincentUuid = $vincent->getUuid()->toString();

        $saved = $this->reload($movie);

        self::assertSame('Heat (Director\'s Cut)', $saved->getTitle());
        self::assertSame([
            ['character' => 'Lt. Vincent Hanna', 'actor' => 'Al Pacino', 'position' => 0],
            ['character' => 'Neil', 'actor' => 'Robert De Niro', 'position' => 1],
            ['character' => 'Eady', 'actor' => 'Amy Brenneman', 'position' => 2],
        ], $this->castOf($saved), 'Submitted order, positions rewritten from 0.');

        $uuids = array_map(static fn (CastMember $member): string => $member->getUuid()->toString(), $saved->getCast()->toArray());
        self::assertSame($vincentUuid, $uuids[0], 'The renamed row is still the same child.');
        self::assertSame($neilUuid, $uuids[1]);

        self::assertSame(3, $this->rowsIn(CastMember::class), 'Chris was deleted, not left orphaned.');
    }

    /**
     * A to-many relation submits the whole list it should hold afterwards:
     * links not in it are removed, links new to it are added, and both sides
     * are records that already exist — only the join rows change.
     */
    public function testAnUpdateSyncsTheTagListToWhatWasSubmitted(): void
    {
        $studio = $this->studio('Warner Bros.');
        $crime  = $this->tag('Crime');
        $drama  = $this->tag('Drama');
        $heist  = $this->tag('Heist');

        $movie = $this->movie('Heat', $studio);
        $movie->addTag($crime)->addTag($drama);
        $this->persist($movie);

        $errors = $this->handler()->handle(new MovieResource(), $movie, $this->body($studio, [
            'tags' => [$drama->getUuid()->toString(), $heist->getUuid()->toString()],
        ]));

        self::assertSame([], $errors);
        self::assertSame(['Drama', 'Heist'], $this->tagNamesOf($this->reload($movie)));
        self::assertSame(3, $this->rowsIn(Tag::class), 'The dropped tag itself is untouched — a peer, not a dependent.');
    }

    // ── Declared rules ───────────────────────────────────────────────────────

    /**
     * The declared rules run first and stop everything else: an invalid body
     * reaches neither the setters nor the database.
     */
    public function testARequiredFieldLeftBlankIsAnErrorOnThatFieldAndNothingIsWritten(): void
    {
        $studio = $this->studio('Warner Bros.');

        $errors = $this->handler()->handle(new MovieResource(), new Movie(), $this->body($studio, ['title' => '']));

        self::assertSame(['title' => 'Title is required.'], $errors);
        self::assertSame(0, $this->rowsIn(Movie::class));
    }

    /**
     * A row's sub-fields carry their own guessed rules; a failure is keyed
     * to the row that caused it, `cast.0.character`, so the client can put
     * the message on that row's input rather than on the repeater.
     */
    public function testAMissingRequiredSubFieldIsKeyedToItsRow(): void
    {
        $studio = $this->studio('Warner Bros.');
        $deNiro = $this->actor('Robert De Niro');

        $errors = $this->handler()->handle(new MovieResource(), new Movie(), $this->body($studio, [
            'cast' => [['actor_id' => $deNiro->getUuid()->toString()]],
        ]));

        self::assertSame(['cast.0.character' => 'Character is required.'], $errors);
        self::assertSame(0, $this->rowsIn(Movie::class));
    }

    // ── Relation lookups ─────────────────────────────────────────────────────

    /**
     * An identifier that names nothing is a client lying about the options
     * it was shown — a to-one rejects the field, a to-many rejects the list
     * as a whole. Neither writes anything.
     */
    public function testAnUnknownStudioIsRejectedOnItsField(): void
    {
        $studio = $this->studio('Warner Bros.');

        $errors = $this->handler()->handle(new MovieResource(), new Movie(), $this->body($studio, [
            'studio_id' => Uuid::uuid4()->toString(),
        ]));

        self::assertSame(['studio_id' => 'Studio is invalid.'], $errors);
        self::assertSame(0, $this->rowsIn(Movie::class));
    }

    public function testAnUnknownTagRejectsTheWholeList(): void
    {
        $studio = $this->studio('Warner Bros.');
        $crime  = $this->tag('Crime');

        $errors = $this->handler()->handle(new MovieResource(), new Movie(), $this->body($studio, [
            'tags' => [$crime->getUuid()->toString(), Uuid::uuid4()->toString()],
        ]));

        self::assertSame(['tags' => 'Tags contains an invalid choice.'], $errors);
        self::assertSame(0, $this->rowsIn(Movie::class));
    }

    /**
     * A row's relation resolves per row, and the error carries the row's
     * index — the second row's actor, not "the cast".
     */
    public function testAnUnknownActorOnARowIsKeyedToThatRow(): void
    {
        $studio = $this->studio('Warner Bros.');
        $deNiro = $this->actor('Robert De Niro');

        $errors = $this->handler()->handle(new MovieResource(), new Movie(), $this->body($studio, [
            'cast' => [
                ['character' => 'Neil', 'actor_id' => $deNiro->getUuid()->toString()],
                ['character' => 'Vincent', 'actor_id' => Uuid::uuid4()->toString()],
            ],
        ]));

        self::assertSame(['cast.1.actor_id' => 'Actor is invalid.'], $errors);
        self::assertSame(0, $this->rowsIn(Movie::class));
        self::assertSame(0, $this->rowsIn(CastMember::class));
    }

    // ── The entity's own constraints ─────────────────────────────────────────

    /**
     * The declared rules are the form's contract; the entity's constraints
     * are the domain's, and they run last against the mapped object. A form
     * that simply does not offer the studio still cannot save a movie
     * without one — the violation surfaces under the property's name, and
     * the flush never happens.
     */
    public function testTheEntitysConstraintsAreTheFinalWord(): void
    {
        $resource = $this->movieWithForm(['title' => ['required' => true]]);

        $errors = $this->handler()->handle($resource, new Movie(), ['title' => 'Heat']);

        self::assertSame(['studio' => 'This value should not be null.'], $errors);
        self::assertSame(0, $this->rowsIn(Movie::class));
    }

    // ── Who may write what ───────────────────────────────────────────────────

    /**
     * A read-only field is dropped from the submission, not merely disabled
     * in the form: a disabled input is a suggestion, the request is what
     * actually arrives.
     */
    public function testAReadonlyFieldIgnoresTheSubmittedValue(): void
    {
        $resource = new class extends MovieResource {
            public function permissions(): Permissions
            {
                return new class extends Permissions {
                    public function writable(string $field, ?object $user, ?object $record = null): bool
                    {
                        return $field !== 'title';
                    }
                };
            }
        };

        $studio = $this->studio('Warner Bros.');
        $movie  = $this->movie('Heat', $studio);

        $errors = $this->handler()->handle($resource, $movie, $this->body($studio, ['title' => 'Renamed']));

        self::assertSame([], $errors, 'Dropped, not rejected — the rest of the body still saves.');
        self::assertSame('Heat', $this->reload($movie)->getTitle());
    }

    /**
     * The same guard on a declared form: writable() answering false for this
     * user strips the value before validation, so the field keeps what it had.
     */
    public function testAFieldTheUserMayNotWriteKeepsItsValue(): void
    {
        $resource = new class extends MovieResource {
            public function formFields(): array
            {
                return ['title' => ['required' => true], 'synopsis' => []];
            }

            public function permissions(): Permissions
            {
                return new class extends Permissions {
                    public function writable(string $field, ?object $user, ?object $record = null): bool
                    {
                        return $field !== 'synopsis';
                    }
                };
            }
        };

        $studio = $this->studio('Warner Bros.');
        $movie  = $this->movie('Heat', $studio)->setSynopsis('The original synopsis.');
        $this->persist($movie);

        $errors = $this->handler()->handle($resource, $movie, [
            'title'    => 'Heat',
            'synopsis' => 'Tampered with.',
        ]);

        self::assertSame([], $errors);
        self::assertSame('The original synopsis.', $this->reload($movie)->getSynopsis());
    }

    // ── Conditions and defaults ──────────────────────────────────────────────

    /**
     * A field whose `when` does not hold was never rendered, so a value
     * arriving under its key was not typed into this form. The condition is
     * evaluated against the coerced values — the toggle is a bool by then,
     * which is what the tuple compares against.
     */
    public function testAValueForAHiddenFieldIsDropped(): void
    {
        $resource = $this->movieWithForm([
            'title'       => ['required' => true],
            'released',
            'released_on' => ['when' => ['released', true]],
        ]);

        $studio = $this->studio('Warner Bros.');
        $movie  = $this->movie('Heat', $studio);

        $errors = $this->handler()->handle($resource, $movie, [
            'title'       => 'Heat',
            'released'    => '0',
            'released_on' => '1995-12-15',
        ]);

        self::assertSame([], $errors);
        self::assertNull($this->reload($movie)->getReleasedOn(), 'Hidden while unreleased, so the date never lands.');

        $movie = $this->reload($movie);

        $errors = $this->handler()->handle($resource, $movie, [
            'title'       => 'Heat',
            'released'    => '1',
            'released_on' => '1995-12-15',
        ]);

        self::assertSame([], $errors);
        self::assertSame('1995-12-15', $this->reload($movie)->getReleasedOn()?->format('Y-m-d'), 'Shown once released, so the same date is kept.');
    }

    /**
     * `@today` is resolved when the submission is handled, not when the
     * blueprint was built — under a worker runtime the latter would be the
     * process's boot day, stamped on every record after it.
     */
    public function testADeclaredDefaultFillsAnAbsentDateWithToday(): void
    {
        $resource = $this->movieWithForm([
            'title'       => ['required' => true],
            'released_on' => ['default' => '@today'],
        ]);

        $studio = $this->studio('Warner Bros.');
        $movie  = $this->movie('Heat', $studio);

        $errors = $this->handler()->handle($resource, $movie, ['title' => 'Heat']);

        self::assertSame([], $errors);
        self::assertSame(date('Y-m-d'), $this->reload($movie)->getReleasedOn()?->format('Y-m-d'));
    }

    public function testADeclaredDefaultDoesNotOverrideASubmittedValue(): void
    {
        $resource = $this->movieWithForm([
            'title'       => ['required' => true],
            'released_on' => ['default' => '@today'],
        ]);

        $studio = $this->studio('Warner Bros.');
        $movie  = $this->movie('Heat', $studio);

        $errors = $this->handler()->handle($resource, $movie, ['title' => 'Heat', 'released_on' => '1995-12-15']);

        self::assertSame([], $errors);
        self::assertSame('1995-12-15', $this->reload($movie)->getReleasedOn()?->format('Y-m-d'));
    }

    // ── Uniqueness ───────────────────────────────────────────────────────────

    /**
     * A unique column is checked before the flush, because a failed flush
     * closes the EntityManager and leaves nothing to re-render the form
     * with. Which columns are unique is read off the metadata — `studios.name`
     * is, and the resource declared nothing about it.
     */
    public function testAValueAnotherRecordAlreadyHoldsOnAUniqueColumnIsRejected(): void
    {
        $this->studio('Warner Bros.');

        $errors = $this->handler()->handle($this->studioResource(), new Studio(), ['name' => 'Warner Bros.']);

        self::assertSame(['name' => 'Name is already in use.'], $errors);
        self::assertSame(1, $this->rowsIn(Studio::class));
    }

    /** The record holding the value is not "another record"; re-saving it is fine. */
    public function testARecordMayKeepItsOwnUniqueValue(): void
    {
        $studio = $this->studio('Warner Bros.');

        $errors = $this->handler()->handle($this->studioResource(), $studio, ['name' => 'Warner Bros.', 'city' => 'Burbank']);

        self::assertSame([], $errors);

        $this->clear();

        $saved = self::em()->find(Studio::class, $studio->getId());
        self::assertInstanceOf(Studio::class, $saved);
        self::assertSame('Burbank', $saved->getCity());
    }

    public function testAFreshUniqueValueIsAccepted(): void
    {
        $this->studio('Warner Bros.');

        $errors = $this->handler()->handle($this->studioResource(), new Studio(), ['name' => 'Amblin']);

        self::assertSame([], $errors);
        self::assertSame(2, $this->rowsIn(Studio::class));
    }

    // ── append() ─────────────────────────────────────────────────────────────

    /**
     * The drawer's add action: one row, without the rest of the form. It
     * lands after whatever the record already has — the repeater's own
     * ordering rule applied to a single row — and the existing rows are left
     * as they were.
     */
    public function testAppendAddsOneCastRowAfterTheExistingOnes(): void
    {
        $studio = $this->studio('Warner Bros.');
        $deNiro = $this->actor('Robert De Niro');
        $pacino = $this->actor('Al Pacino');
        $kilmer = $this->actor('Val Kilmer');
        $movie  = $this->movie('Heat', $studio, [['Neil', $deNiro], ['Vincent', $pacino]]);

        $errors = $this->handler()->append(new MovieResource(), $movie, 'cast', [
            'character' => 'Chris',
            'actor_id'  => $kilmer->getUuid()->toString(),
        ]);

        self::assertSame([], $errors);
        self::assertSame([
            ['character' => 'Neil', 'actor' => 'Robert De Niro', 'position' => 0],
            ['character' => 'Vincent', 'actor' => 'Al Pacino', 'position' => 1],
            ['character' => 'Chris', 'actor' => 'Val Kilmer', 'position' => 2],
        ], $this->castOf($this->reload($movie)));
    }

    /**
     * One row means one row's errors: keyed by the sub-field alone, since
     * there is no index for the client to pin them to.
     */
    public function testAppendValidatesTheRowAndResolvesItsActor(): void
    {
        $studio = $this->studio('Warner Bros.');
        $deNiro = $this->actor('Robert De Niro');
        $movie  = $this->movie('Heat', $studio, [['Neil', $deNiro]]);

        $handler = $this->handler();

        self::assertSame(
            ['character' => 'Character is required.'],
            $handler->append(new MovieResource(), $movie, 'cast', ['actor_id' => $deNiro->getUuid()->toString()]),
        );

        self::assertSame(
            ['actor_id' => 'Actor is invalid.'],
            $handler->append(new MovieResource(), $movie, 'cast', ['character' => 'Vincent', 'actor_id' => Uuid::uuid4()->toString()]),
        );

        self::assertCount(1, $this->reload($movie)->getCast(), 'Neither attempt left a row behind.');
    }

    /**
     * For a to-many relation the add action links one existing record by
     * its identifier. Linking it twice is a no-op rather than a duplicate
     * join row, and an identifier that names nothing is rejected on the
     * field — with the to-one wording, since exactly one was submitted.
     */
    public function testAppendLinksOneTagOnceAndRejectsAnUnknownOne(): void
    {
        $studio = $this->studio('Warner Bros.');
        $crime  = $this->tag('Crime');
        $drama  = $this->tag('Drama');
        $movie  = $this->movie('Heat', $studio);
        $movie->addTag($crime);
        $this->persist($movie);

        $handler = $this->handler();

        self::assertSame([], $handler->append(new MovieResource(), $movie, 'tags', ['tags' => $drama->getUuid()->toString()]));
        self::assertSame(['Crime', 'Drama'], $this->tagNamesOf($movie));

        self::assertSame([], $handler->append(new MovieResource(), $movie, 'tags', ['tags' => $drama->getUuid()->toString()]));
        self::assertSame(['Crime', 'Drama'], $this->tagNamesOf($movie), 'Already linked: nothing added.');

        self::assertSame(
            ['tags' => 'Tags is invalid.'],
            $handler->append(new MovieResource(), $movie, 'tags', ['tags' => Uuid::uuid4()->toString()]),
        );

        self::assertSame(['Crime', 'Drama'], $this->tagNamesOf($this->reload($movie)));
    }

    /**
     * The key is a form field or nothing: a request cannot reach an
     * association the resource did not declare.
     */
    public function testAppendToAKeyThatIsNotAFieldThrows(): void
    {
        $studio = $this->studio('Warner Bros.');
        $movie  = $this->movie('Heat', $studio);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"director" is not a field of this resource.');

        $this->handler()->append(new MovieResource(), $movie, 'director', ['director' => Uuid::uuid4()->toString()]);
    }

    /** A scalar field has nothing to add to; that is a programming error, not a form error. */
    public function testAppendToAScalarFieldThrows(): void
    {
        $studio = $this->studio('Warner Bros.');
        $movie  = $this->movie('Heat', $studio);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"title" is not a relation this can add to.');

        $this->handler()->append(new MovieResource(), $movie, 'title', ['title' => 'Renamed']);
    }
}
