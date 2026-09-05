<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Table\Constraint;
use Modufolio\Panel\Table\Filter;
use Modufolio\Panel\Table\TableSchema;
use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;
use Modufolio\Panel\Tests\Fixture\Entity\Studio;
use Modufolio\Panel\Tests\Fixture\MovieResource;
use Ramsey\Uuid\Uuid;

/**
 * The predicates behind a schema's declared filters and constraints, as the
 * listing applies them.
 *
 * {@see Filter} and {@see Constraint} are declarative on purpose: a fixed
 * type paired with a hardcoded entity field, and an operator vocabulary that
 * comes from the field type rather than from a closure. What that buys is
 * only worth having if the predicates each declaration stands for are the
 * ones a user would expect — a ternary's two sides, a date range that keeps
 * its end day, a `contains` that treats `%` as a character. These tests read
 * `meta.total` from a rendered listing, because the total is the count query
 * and the count query shares every predicate with the rows.
 *
 * Every anonymous subclass swaps the fixture's filters or constraints for the
 * one under test; the list query's own soft-delete scope stays in force, so
 * the deleted fixture row is never counted unless `trashed` asks for it.
 */
final class SchemaFiltersTest extends DoctrineTestCase
{
    /** @var array<string, Studio> keyed by name */
    private array $studios = [];

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /**
     * Five live movies and one soft-deleted, shaped for filtering: two per
     * studio plus an unreleased title with no date and no rating.
     *
     *   Heat                1995  Warner  released  1995-12-15  8.3
     *   Collateral          2004  Warner  released  2004-08-06  7.5
     *   Jaws                1975  Amblin  released  1975-06-20  8.1
     *   Jurassic Park       1993  Amblin  released  1993-06-11  8.2
     *   Untitled Spielberg  2027  Amblin  —         —           —
     *   Hook (deleted)      1991  Amblin  released  1991-12-11  6.8
     */
    private function seed(): void
    {
        $warner = (new Studio())->setName('Warner Bros.');
        $amblin = (new Studio())->setName('Amblin');

        $this->studios = ['Warner Bros.' => $warner, 'Amblin' => $amblin];

        $movie = static fn (string $title, int $year, Studio $studio, bool $released, ?string $on, ?string $rating): Movie => (new Movie())
            ->setTitle($title)
            ->setYear($year)
            ->setStudio($studio)
            ->setReleased($released)
            ->setReleasedOn($on === null ? null : new \DateTimeImmutable($on))
            ->setRating($rating);

        $this->persist(
            $warner,
            $amblin,
            $movie('Heat', 1995, $warner, true, '1995-12-15', '8.3'),
            $movie('Collateral', 2004, $warner, true, '2004-08-06', '7.5'),
            $movie('Jaws', 1975, $amblin, true, '1975-06-20', '8.1'),
            $movie('Jurassic Park', 1993, $amblin, true, '1993-06-11', '8.2'),
            $movie('Untitled Spielberg', 2027, $amblin, false, null, null),
            $movie('Hook', 1991, $amblin, true, '1991-12-11', '6.8')
                ->setDeletedAt(new \DateTimeImmutable('2026-02-01 12:00:00')),
        );
        $this->clear();
    }

    private function studioUuid(string $name): string
    {
        $studio = $this->studios[$name] ?? self::fail(sprintf('No seeded studio "%s".', $name));

        return $studio->getUuid()->toString();
    }

    /** A resource whose schema declares only these filters. */
    private function withFilters(Filter ...$filters): PanelResource
    {
        return new class (array_values($filters)) extends MovieResource {
            /** @param list<Filter> $filters */
            public function __construct(private readonly array $filters)
            {
            }

            public function table(): TableSchema
            {
                return parent::table()->filters($this->filters);
            }
        };
    }

    /** A resource whose schema declares only these constraints. */
    private function withConstraints(Constraint ...$constraints): PanelResource
    {
        return new class (array_values($constraints)) extends MovieResource {
            /** @param list<Constraint> $constraints */
            public function __construct(private readonly array $constraints)
            {
            }

            public function table(): TableSchema
            {
                return parent::table()->constraints($this->constraints);
            }
        };
    }

    // ── Reading a render ─────────────────────────────────────────────────────

    /**
     * Render the resource for this query and hand back what it saw.
     *
     * @param  array<string, mixed> $query
     * @return array{total: int, titles: list<string>}
     */
    private function render(array $query, ?PanelResource $resource = null): array
    {
        $props = $this->renderProps($this->listing(
            $resource ?? new MovieResource(),
            $query,
            urls: $this->urlGenerator(MovieResource::class),
        ));

        $total = $props['movies']['meta']['total'] ?? null;
        self::assertIsInt($total);

        $rows = $props['movies']['data'] ?? null;
        self::assertIsArray($rows);

        $titles = [];

        foreach ($rows as $row) {
            self::assertIsArray($row);
            self::assertIsString($row['title']);
            $titles[] = $row['title'];
        }

        self::assertCount($total, $titles, 'Everything fits on one page here, so rows and total must agree.');

        return ['total' => $total, 'titles' => $titles];
    }

    /** @param array<string, mixed> $query */
    private function total(array $query, ?PanelResource $resource = null): int
    {
        return $this->render($query, $resource)['total'];
    }

    /**
     * @param  array<string, mixed> $query
     * @return list<string>
     */
    private function titles(array $query, ?PanelResource $resource = null): array
    {
        return $this->render($query, $resource)['titles'];
    }

    /**
     * One user-composed condition, in the shape the query builder UI sends:
     * `constraints[n][key]`, `[operator]`, `[value]`, `[value2]`.
     *
     * @return array<string, string>
     */
    private function condition(string $key, string $operator, ?string $value = null, ?string $value2 = null): array
    {
        $condition = ['key' => $key, 'operator' => $operator];

        if ($value !== null) {
            $condition['value'] = $value;
        }

        if ($value2 !== null) {
            $condition['value2'] = $value2;
        }

        return $condition;
    }

    /**
     * The total under one condition against the fixture resource.
     */
    private function constrained(string $key, string $operator, ?string $value = null, ?string $value2 = null): int
    {
        return $this->total(['constraints' => [$this->condition($key, $operator, $value, $value2)]]);
    }

    // ── Select on a relation ─────────────────────────────────────────────────

    /**
     * The option values are the related entity's uuids, but the filtered
     * field is the association. Comparing the two directly would match a
     * foreign key against a uuid and find nothing; the filter resolves through
     * the relation instead, and does so in a subquery so the count builder
     * gets the same predicate without a join.
     */
    public function testASelectFilterOnARelationResolvesThroughTheRelatedUuid(): void
    {
        $this->seed();

        self::assertSame(
            ['Jaws', 'Jurassic Park', 'Untitled Spielberg'],
            $this->titles(['studio' => $this->studioUuid('Amblin')]),
        );
        self::assertSame(['Collateral', 'Heat'], $this->titles(['studio' => $this->studioUuid('Warner Bros.')]));
    }

    /** JSON:API's `filter[studio]` reaches the same filter as the bare key. */
    public function testADeclaredFilterMayArriveUnderTheJsonApiFilterKey(): void
    {
        $this->seed();

        self::assertSame(2, $this->total(['filter' => ['studio' => $this->studioUuid('Warner Bros.')]]));
    }

    public function testAnUnknownRelatedUuidMatchesNothing(): void
    {
        $this->seed();

        self::assertSame(0, $this->total(['studio' => Uuid::uuid4()->toString()]));
    }

    /** An empty control is no filter at all — the "any" option. */
    public function testAnEmptySelectValueIsANoOp(): void
    {
        $this->seed();

        self::assertSame(5, $this->total(['studio' => '']));
        self::assertSame(5, $this->total([]));
    }

    // ── Ternary ──────────────────────────────────────────────────────────────

    /**
     * Three states: the true value, the false value, and nothing. Only the
     * true value compares as true; every other non-empty value is the false
     * side, so a client cannot invent a third predicate.
     */
    public function testATernaryFilterSelectsEitherSideOrNeither(): void
    {
        $this->seed();

        self::assertSame(['Collateral', 'Heat', 'Jaws', 'Jurassic Park'], $this->titles(['released' => '1']));
        self::assertSame(['Untitled Spielberg'], $this->titles(['released' => '0']));
        self::assertSame(5, $this->total(['released' => '']));
    }

    // ── Trashed ──────────────────────────────────────────────────────────────

    /**
     * Filter::trashed() declares the control only; the predicate belongs to
     * the list query, which already owns `trashed`. Were the filter to apply
     * itself as well, `deletedAt = 'only'` would match nothing — so a count
     * of exactly the deleted rows is the proof it stayed out of the way.
     */
    public function testTheTrashedFilterLeavesItsPredicateToTheListQuery(): void
    {
        $this->seed();

        self::assertSame(['Hook'], $this->titles(['trashed' => 'only']));
        self::assertSame(6, $this->total(['trashed' => 'with']));
        self::assertSame(5, $this->total([]));
    }

    // ── Multi-select ─────────────────────────────────────────────────────────

    /**
     * A multi-select arrives either as one comma-separated string or as a
     * repeated `key[]` param; both mean the same IN list. Blanks are dropped,
     * and a list of nothing but blanks is no filter.
     */
    public function testAMultiSelectFilterAcceptsCommaSeparatedAndArrayValues(): void
    {
        $this->seed();

        $resource = $this->withFilters(Filter::multiSelect('year'));

        self::assertSame(['Collateral', 'Heat'], $this->titles(['year' => '1995,2004'], $resource));
        self::assertSame(['Jaws', 'Jurassic Park'], $this->titles(['year' => ['1975', '1993']], $resource));
        self::assertSame(['Heat'], $this->titles(['year' => ' 1995 , '], $resource), 'Whitespace and blanks trimmed away.');

        self::assertSame(5, $this->total(['year' => ''], $resource));
        self::assertSame(5, $this->total(['year' => ['', '']], $resource));
    }

    /** The relation resolution a select does, over an IN list. */
    public function testAMultiSelectFilterOnARelationResolvesEveryUuid(): void
    {
        $this->seed();

        $resource = $this->withFilters(
            Filter::multiSelect('studio')->relationship(Studio::class, 'name', 'uuid'),
        );

        self::assertSame(5, $this->total([
            'studio' => [$this->studioUuid('Amblin'), $this->studioUuid('Warner Bros.')],
        ], $resource));
        self::assertSame(3, $this->total(['studio' => $this->studioUuid('Amblin')], $resource));
        self::assertSame(0, $this->total(['studio' => [Uuid::uuid4()->toString()]], $resource));
    }

    // ── Date range ───────────────────────────────────────────────────────────

    /**
     * A date picker names days, not instants, so `until` keeps the whole end
     * day: the bound is `< until + 1 day`, not `<= until`. Either side may be
     * omitted, and `start`/`end` are accepted as aliases for `from`/`until`.
     *
     * Bounds are kept a day clear of the fixture dates except where the
     * inclusion is the point, because the exact-day behaviour differs by
     * engine — see {@see testTheExactDayBoundaryOfADateBoundDependsOnTheEngine()}.
     */
    public function testADateRangeFilterIsInclusiveOfTheEndDay(): void
    {
        $this->seed();

        $resource = $this->withFilters(Filter::dateRange('released_on', 'releasedOn'));

        self::assertSame(
            ['Heat', 'Jurassic Park'],
            $this->titles(['released_on' => ['from' => '1993-06-10', 'until' => '1995-12-15']], $resource),
            'Heat was released on the until day itself.',
        );
        self::assertSame(
            ['Jurassic Park'],
            $this->titles(['released_on' => ['from' => '1993-06-10', 'until' => '1995-12-13']], $resource),
        );

        self::assertSame(
            ['Collateral', 'Heat'],
            $this->titles(['released_on' => ['from' => '1995-01-01']], $resource),
            'An open end.',
        );
        self::assertSame(
            ['Jaws', 'Jurassic Park'],
            $this->titles(['released_on' => ['until' => '1993-06-11']], $resource),
            'An open start; the unreleased title has no date and never matches a range.',
        );

        self::assertSame(
            ['Heat', 'Jurassic Park'],
            $this->titles(['released_on' => ['start' => '1993-06-10', 'end' => '1995-12-15']], $resource),
        );

        self::assertSame(5, $this->total(['released_on' => ['from' => '', 'until' => '']], $resource));
    }

    // ── Text constraints ─────────────────────────────────────────────────────

    /**
     * The text vocabulary is FiltersText's, under the panel's names. Each
     * operator is checked against a title it should and should not match.
     */
    public function testTextConstraintsSpeakTheTextTypesOperators(): void
    {
        $this->seed();

        self::assertSame(1, $this->constrained('title', 'contains', 'ea'), 'Heat');
        self::assertSame(0, $this->constrained('title', 'contains', 'Rotterdam'));

        self::assertSame(2, $this->constrained('title', 'starts_with', 'J'), 'Jaws, Jurassic Park');
        self::assertSame(0, $this->constrained('title', 'starts_with', 'aws'));

        self::assertSame(1, $this->constrained('title', 'ends_with', 'Park'));
        self::assertSame(0, $this->constrained('title', 'ends_with', 'Jurassic'));

        self::assertSame(1, $this->constrained('title', 'equals', 'Heat'));
        self::assertSame(0, $this->constrained('title', 'equals', 'Hea'));

        self::assertSame(4, $this->constrained('title', 'not_equals', 'Heat'));

        self::assertSame(1, $this->constrained('title', 'not_contains', 'a'), 'Only Untitled Spielberg has no "a".');
    }

    /**
     * `empty` and `not_empty` take no value, and "empty" means null *or* the
     * empty string — a text column's two ways of holding nothing.
     */
    public function testTextEmptinessCoversNullAndTheEmptyString(): void
    {
        $this->seed();
        $this->persist(
            (new Movie())->setTitle('')->setSynopsis(null),
            (new Movie())->setTitle('Blank synopsis')->setSynopsis(''),
        );
        $this->clear();

        $resource = $this->withConstraints(Constraint::text('title'), Constraint::text('synopsis'));

        self::assertSame(1, $this->total(['constraints' => [$this->condition('title', 'empty')]], $resource));
        self::assertSame(6, $this->total(['constraints' => [$this->condition('title', 'not_empty')]], $resource));

        self::assertSame(
            7,
            $this->total(['constraints' => [$this->condition('synopsis', 'empty')]], $resource),
            'Every fixture row has a null synopsis; one has an empty one. Both are empty.',
        );
        self::assertSame(0, $this->total(['constraints' => [$this->condition('synopsis', 'not_empty')]], $resource));
    }

    /**
     * A user typing `%` or `_` means those characters. Unescaped, `_` alone
     * would match every non-empty title and `%` would match everything; no
     * seeded title contains either, so both must match nothing.
     */
    public function testLikeWildcardsInAConstraintValueDoNotWiden(): void
    {
        $this->seed();

        self::assertSame(0, $this->constrained('title', 'contains', '%'));
        self::assertSame(0, $this->constrained('title', 'contains', '_'));
        self::assertSame(0, $this->constrained('title', 'starts_with', '_'));
        self::assertSame(0, $this->constrained('title', 'ends_with', '%'));
        self::assertSame(5, $this->constrained('title', 'not_contains', '%'), 'Nothing excluded either.');
    }

    /**
     * The other half of escaping: a title that genuinely contains `%` or `_`
     * is found by the escaped pattern — on every engine. The first version
     * escaped with a backslash and emitted no ESCAPE clause, relying on the
     * engine's default; SQLite has none, so there `contains %` searched for a
     * literal `\%` and never matched. The predicate now names its escape
     * character, and that character is itself searchable.
     */
    public function testATitleContainingAWildcardCharacterIsFoundByTheEscapedPattern(): void
    {
        $this->seed();
        $this->persist(
            (new Movie())->setTitle('100% Pure'),
            (new Movie())->setTitle('snake_case'),
            (new Movie())->setTitle('Hello!'),
        );
        $this->clear();

        self::assertSame(1, $this->constrained('title', 'contains', '%'), '100% Pure');
        self::assertSame(1, $this->constrained('title', 'contains', '_'), 'snake_case');
        self::assertSame(1, $this->constrained('title', 'starts_with', '100%'));
        self::assertSame(1, $this->constrained('title', 'ends_with', '_case'));
        self::assertSame(1, $this->constrained('title', 'contains', '!'), 'The escape character is an ordinary character to search for.');
        self::assertSame(7, $this->constrained('title', 'not_contains', '!'), 'Everything live but Hello!');
    }

    // ── Number constraints ───────────────────────────────────────────────────

    /**
     * FiltersComparable's vocabulary, with `between` inclusive at both ends.
     */
    public function testNumberConstraintsCompareAndBound(): void
    {
        $this->seed();

        self::assertSame(2, $this->constrained('year', 'gt', '1995'), '2004, 2027');
        self::assertSame(3, $this->constrained('year', 'gte', '1995'), '1995, 2004, 2027');
        self::assertSame(1, $this->constrained('year', 'lt', '1993'), '1975');
        self::assertSame(2, $this->constrained('year', 'lte', '1993'), '1975, 1993');

        self::assertSame(1, $this->constrained('year', 'equals', '1995'));
        self::assertSame(4, $this->constrained('year', 'not_equals', '1995'));

        self::assertSame(2, $this->constrained('year', 'between', '1993', '1995'), 'Both bounds inclusive.');
        self::assertSame(0, $this->constrained('year', 'between', '1996', '2003'));
    }

    /** `between` takes two values; with one it is a malformed condition and narrows nothing. */
    public function testBetweenWithoutASecondValueIsANoOp(): void
    {
        $this->seed();

        self::assertSame(5, $this->constrained('year', 'between', '1993'));
        self::assertSame(5, $this->constrained('year', 'between', '1993', ''));
    }

    // ── Boolean constraints ──────────────────────────────────────────────────

    /**
     * One operator, `is`, and the value is read the way a query string spells
     * booleans: `true`/`1` and `false`/`0`.
     */
    public function testABooleanConstraintReadsQueryStringBooleans(): void
    {
        $this->seed();

        self::assertSame(4, $this->constrained('released', 'is', 'true'));
        self::assertSame(4, $this->constrained('released', 'is', '1'));
        self::assertSame(1, $this->constrained('released', 'is', 'false'));
        self::assertSame(1, $this->constrained('released', 'is', '0'));
    }

    // ── Date constraints ─────────────────────────────────────────────────────

    /**
     * `on` is a day, spelled out as the half-open interval
     * `[midnight, next midnight)` so the same declaration works against a
     * datetime column, where plain equality would match only the stroke of
     * midnight. `after` and `before` map onto the comparable `gte`/`lte`, so
     * both keep the named day; `between` keeps both of its.
     *
     * Bounds sit a day clear of the fixture dates except where the inclusion
     * is the point and every engine agrees — the exact-day cases are in
     * {@see testTheExactDayBoundaryOfADateBoundDependsOnTheEngine()}.
     */
    public function testDateConstraintsSpeakTheDateTypesOperators(): void
    {
        $this->seed();

        self::assertSame(0, $this->constrained('released_on', 'on', '1995-12-16'), 'The day after Heat is outside its interval.');
        self::assertSame(0, $this->constrained('released_on', 'on', '2000-01-01'));

        self::assertSame(2, $this->constrained('released_on', 'after', '1995-12-14'), 'Heat, Collateral');
        self::assertSame(1, $this->constrained('released_on', 'after', '1995-12-16'), 'Collateral');

        self::assertSame(2, $this->constrained('released_on', 'before', '1993-06-11'), 'Jaws, Jurassic Park — the day itself kept.');
        self::assertSame(1, $this->constrained('released_on', 'before', '1993-06-10'), 'Jaws');

        self::assertSame(2, $this->constrained('released_on', 'between', '1993-01-01', '1995-12-31'), 'Jurassic Park, Heat');
        self::assertSame(5, $this->constrained('released_on', 'between', '1993-01-01'), 'One bound is not a range.');

        self::assertSame(
            1,
            $this->constrained('released_on', 'empty'),
            'The unreleased title has no date; it never matches a comparison.',
        );
        self::assertSame(4, $this->constrained('released_on', 'not_empty'));
    }

    /**
     * The named day itself: `on D`, `after D`, `before D` and a range
     * `from D` / `until D` all include a row dated D, and `on D-1` does not —
     * on every engine. The first version bound a DateTimeImmutable untyped,
     * which Doctrine formats with a time part; a DATE column stored as text
     * (SQLite) compares byte-wise, and '1995-12-15' sorts before
     * '1995-12-15 00:00:00', so every lower bound skipped its own day and
     * every upper bound leaked one. Engines that coerce hid it; the bounds
     * are now typed as dates.
     */
    public function testADateBoundIncludesItsOwnDayOnEveryEngine(): void
    {
        $this->seed();

        $range = $this->withFilters(Filter::dateRange('released_on', 'releasedOn'));

        self::assertSame(1, $this->constrained('released_on', 'on', '1995-12-15'), 'Heat');
        self::assertSame(0, $this->constrained('released_on', 'on', '1995-12-14'));
        self::assertSame(0, $this->constrained('released_on', 'on', '1995-12-16'));
        self::assertSame(2, $this->constrained('released_on', 'after', '1995-12-15'), 'Heat, Collateral');
        self::assertSame(3, $this->constrained('released_on', 'before', '1995-12-15'), 'Jaws, Jurassic Park, Heat');
        self::assertSame(2, $this->total(['released_on' => ['from' => '1995-12-15']], $range), 'Heat, Collateral');
        self::assertSame(3, $this->total(['released_on' => ['until' => '1995-12-15']], $range), 'Jaws, Jurassic Park, Heat');
    }

    // ── Malformed and combined conditions ────────────────────────────────────

    /**
     * The operator must be one the field type declares. The old vocabulary
     * (`notContains`, `isEmpty`) is not, and a number type does not know
     * `contains` — either narrows nothing rather than erroring the page.
     */
    public function testAnOperatorTheTypeDoesNotDeclareNarrowsNothing(): void
    {
        $this->seed();

        self::assertSame(5, $this->constrained('title', 'notContains', 'Heat'));
        self::assertSame(5, $this->constrained('title', 'isEmpty'));
        self::assertSame(5, $this->constrained('year', 'contains', '19'));
        self::assertSame(5, $this->constrained('released', 'equals', 'true'));
        self::assertSame(5, $this->constrained('title', '', 'Heat'));
    }

    /** The request chooses among declared constraints; it cannot invent one. */
    public function testAKeyTheSchemaDoesNotDeclareIsIgnored(): void
    {
        $this->seed();

        self::assertSame(5, $this->constrained('synopsis', 'contains', 'anything'));
        self::assertSame(5, $this->constrained('deletedAt', 'not_empty'));
        self::assertSame(5, $this->total(['constraints' => [['operator' => 'contains', 'value' => 'Heat']]]), 'No key at all.');
    }

    /** An operator that takes a value, given none, is not a condition yet. */
    public function testAValueLessConditionForAOneValueOperatorIsANoOp(): void
    {
        $this->seed();

        self::assertSame(5, $this->constrained('title', 'contains'));
        self::assertSame(5, $this->constrained('title', 'contains', ''));
        self::assertSame(5, $this->constrained('year', 'gt'));
        self::assertSame(5, $this->constrained('released', 'is', ''));
    }

    /** A condition that is not even an array is skipped, not fatal. */
    public function testAConditionThatIsNotAnArrayIsSkipped(): void
    {
        $this->seed();

        self::assertSame(5, $this->total(['constraints' => ['garbage', 42]]));
        self::assertSame(5, $this->total(['constraints' => 'garbage']));
    }

    /**
     * Conditions AND together, each with its own bound parameter so two on
     * the same field do not collide.
     */
    public function testSeveralConstraintsNarrowTogether(): void
    {
        $this->seed();

        self::assertSame(['Collateral', 'Heat'], $this->titles(['constraints' => [
            $this->condition('title', 'contains', 'a'),
            $this->condition('year', 'gte', '1995'),
        ]]), 'Four titles contain an "a"; two of those are 1995 or later.');

        self::assertSame(['Heat'], $this->titles(['constraints' => [
            $this->condition('year', 'gte', '1990'),
            $this->condition('year', 'lt', '2000'),
            $this->condition('title', 'starts_with', 'H'),
        ]]), 'Two conditions on year, one on title.');

        self::assertSame([], $this->titles(['constraints' => [
            $this->condition('released', 'is', 'false'),
            $this->condition('released_on', 'not_empty'),
        ]]), 'Contradictory conditions leave nothing.');
    }

    /** Declared filters and constraints stack with each other and with search. */
    public function testFiltersConstraintsAndSearchAllApplyAtOnce(): void
    {
        $this->seed();

        self::assertSame(['Jurassic Park'], $this->titles([
            'studio'      => $this->studioUuid('Amblin'),
            'released'    => '1',
            'search'      => 'j',
            'constraints' => [$this->condition('year', 'gt', '1980')],
        ]));
    }
}
