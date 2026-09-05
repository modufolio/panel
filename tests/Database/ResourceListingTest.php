<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Modufolio\Appkit\Security\User\UserInterface;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\QueryBuilder;
use Modufolio\Panel\Resource\Permissions;
use Modufolio\Panel\Table\BulkAction;
use Modufolio\Panel\Table\RowAction;
use Modufolio\Panel\Table\TableSchema;
use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;
use Modufolio\Panel\Tests\Fixture\Entity\Studio;
use Modufolio\Panel\Tests\Fixture\MovieResource;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The listing end to end: a resource, a request, real rows, and the props
 * the host's renderer receives.
 *
 * Everything the generic Resource/Index page relies on is derived here — the
 * rows and their total, the echoed filters, the row actions from the routes,
 * the summaries over the filtered set — and every derivation has to agree
 * with every other. The count must see the filters the page saw; the export
 * must see the scope the page saw; prev/next must walk the order the page
 * showed. So these tests read the *rendered* props rather than any internal,
 * because agreement between props is the contract.
 *
 * Anonymous subclasses of {@see MovieResource} override one hook at a time.
 * Their routes are built from `MovieResource::class` explicitly: the route
 * loader writes the class name into a config file, and an anonymous class
 * has no name that survives that.
 */
final class ResourceListingTest extends DoctrineTestCase
{
    /** @var array<string, Studio> keyed by name */
    private array $studios = [];

    /** @var array<string, Movie> keyed by title */
    private array $movies = [];

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /**
     * Five movies across two studios, with distinct years and ratings and one
     * unreleased title whose rating is still null.
     *
     * Persisted in an order that differs from the title order, so a test that
     * asserts an order is asserting the sort rather than the insert.
     *
     * Default sort (title ASC): Collateral, Heat, Jaws, Jurassic Park,
     * Untitled Spielberg.
     */
    private function seed(): void
    {
        $warner = (new Studio())->setName('Warner Bros.')->setCity('Burbank');
        $amblin = (new Studio())->setName('Amblin')->setCity('Universal City');

        $this->studios = ['Warner Bros.' => $warner, 'Amblin' => $amblin];

        $rows = [
            ['Heat', 1995, '8.3', true, '1995-12-15', $warner],
            ['Jaws', 1975, '8.1', true, '1975-06-20', $amblin],
            ['Collateral', 2004, '7.5', true, '2004-08-06', $warner],
            ['Untitled Spielberg', 2027, null, false, null, $amblin],
            ['Jurassic Park', 1993, '8.2', true, '1993-06-11', $amblin],
        ];

        $this->movies = [];

        foreach ($rows as $i => [$title, $year, $rating, $released, $releasedOn, $studio]) {
            $this->movies[$title] = (new Movie())
                ->setTitle($title)
                ->setYear($year)
                ->setRating($rating)
                ->setReleased($released)
                ->setReleasedOn($releasedOn === null ? null : new \DateTimeImmutable($releasedOn))
                ->setStudio($studio)
                // Distinct and in insert order, so `created_at` sorts predictably.
                ->setCreatedAt(new \DateTimeImmutable(sprintf('2026-01-%02d 10:00:00', $i + 1)));
        }

        $this->persist($warner, $amblin, ...array_values($this->movies));
        $this->clear();
    }

    private function movie(string $title): Movie
    {
        return $this->movies[$title] ?? self::fail(sprintf('No seeded movie "%s".', $title));
    }

    private function studio(string $name): Studio
    {
        return $this->studios[$name] ?? self::fail(sprintf('No seeded studio "%s".', $name));
    }

    private function softDelete(string $title): void
    {
        $movie = self::em()->find(Movie::class, $this->movie($title)->getId());
        self::assertInstanceOf(Movie::class, $movie);

        $movie->setDeletedAt(new \DateTimeImmutable('2026-02-01 12:00:00'));
        self::em()->flush();
        $this->clear();
    }

    /** The fixture resource with its permissions swapped for the given object. */
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

    /** Routes for the plain resource, for a listing over an anonymous subclass. */
    private function movieRoutes(): UrlGeneratorInterface
    {
        return $this->urlGenerator(MovieResource::class);
    }

    // ── Reading the props ────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed> $props
     * @return list<string>
     */
    private function titles(array $props): array
    {
        $rows = $props['movies']['data'] ?? null;
        self::assertIsArray($rows);

        $titles = [];

        foreach ($rows as $row) {
            self::assertIsArray($row);
            self::assertIsString($row['title']);
            $titles[] = $row['title'];
        }

        return $titles;
    }

    /** @param array<string, mixed> $props */
    private function total(array $props): int
    {
        $total = $props['movies']['meta']['total'] ?? null;
        self::assertIsInt($total);

        return $total;
    }

    /**
     * The `meta.summaries` block, keyed by column key.
     *
     * @param  array<string, mixed> $props
     * @return array<string, list<array{type: string, label: string, value: float|null}>>
     */
    private function summaries(array $props): array
    {
        $summaries = $props['movies']['meta']['summaries'] ?? null;
        self::assertIsArray($summaries);

        $typed = [];

        foreach ($summaries as $column => $entries) {
            self::assertIsString($column);
            self::assertIsArray($entries);

            foreach ($entries as $entry) {
                self::assertIsArray($entry);
                self::assertIsString($entry['type']);
                self::assertIsString($entry['label']);
                self::assertTrue($entry['value'] === null || is_float($entry['value']));

                $typed[$column][] = ['type' => $entry['type'], 'label' => $entry['label'], 'value' => $entry['value']];
            }
        }

        return $typed;
    }

    /**
     * Row actions as `name => behaviour`, in declaration order.
     *
     * @param  array<string, mixed> $props
     * @return array<string, string>
     */
    private function actionBehaviours(array $props): array
    {
        $actions = $props['table']['actions'] ?? null;
        self::assertIsArray($actions);

        $byName = [];

        foreach ($actions as $action) {
            self::assertIsArray($action);
            self::assertIsString($action['name']);
            self::assertIsString($action['behaviour']);
            $byName[$action['name']] = $action['behaviour'];
        }

        return $byName;
    }

    /**
     * One row action by name, or a failure.
     *
     * @param  array<string, mixed> $props
     * @return array<string, mixed>
     */
    private function action(array $props, string $name): array
    {
        $actions = $props['table']['actions'] ?? null;
        self::assertIsArray($actions);

        foreach ($actions as $action) {
            self::assertIsArray($action);

            if (($action['name'] ?? null) === $name) {
                return $action;
            }
        }

        self::fail(sprintf('No row action named "%s".', $name));
    }

    /**
     * Bulk actions as `name => url`.
     *
     * @param  array<string, mixed> $props
     * @return array<string, string>
     */
    private function bulkActionUrls(array $props): array
    {
        $items = $props['table']['bulkActionItems'] ?? null;
        self::assertIsArray($items);

        $urls = [];

        foreach ($items as $item) {
            self::assertIsArray($item);
            self::assertIsString($item['name']);
            self::assertIsString($item['url']);
            $urls[$item['name']] = $item['url'];
        }

        return $urls;
    }

    /**
     * @param  list<object> $entities
     * @return list<string>
     */
    private function titlesOf(array $entities): array
    {
        return array_map(static function (object $entity): string {
            self::assertInstanceOf(Movie::class, $entity);

            return $entity->getTitle();
        }, $entities);
    }

    // ── The props contract ───────────────────────────────────────────────────

    /**
     * Everything the generic index page reads, from one render with no
     * parameters: the rows under the resource key in JSON:API collection
     * shape, the echoed (empty) filters, an empty drawer stack, the resource
     * meta with every write permission derived from routes *and* the
     * resource, the schema with sortability resolved, and the host's shared
     * props merged in.
     */
    public function testRenderHandsTheHostTheWholePropsContract(): void
    {
        $this->seed();

        $props = $this->renderProps($this->listing(new MovieResource()));

        self::assertSame('Resource/Index', $this->renderer?->component);

        self::assertSame(
            ['filters', 'movies', 'stack', 'resource', 'table', 'auth', 'flash'],
            array_keys($props),
        );

        self::assertSame([
            'search'      => null,
            'trashed'     => null,
            'sort'        => '',
            'group'       => null,
            'constraints' => [],
            'studio'      => null,
            'released'    => null,
        ], $props['filters'], 'Every declared filter is echoed, valued or not.');

        self::assertSame(
            ['Collateral', 'Heat', 'Jaws', 'Jurassic Park', 'Untitled Spielberg'],
            $this->titles($props),
            'The default sort is the list query\'s: title ASC.',
        );

        $meta = $props['movies']['meta'];
        self::assertSame(5, $meta['total']);
        self::assertSame(25, $meta['per_page']);
        self::assertSame(1, $meta['current_page']);
        self::assertSame(1, $meta['last_page']);
        self::assertSame(1, $meta['from']);
        self::assertSame(5, $meta['to']);
        self::assertArrayHasKey('summaries', $meta);
        self::assertArrayNotHasKey('links', $props['movies'], 'No base URL is given, so no pagination links.');

        $first = $props['movies']['data'][0];
        self::assertSame(['id', 'uuid', 'title', 'year', 'rating', 'released', 'studio'], array_keys($first));
        self::assertSame($this->movie('Collateral')->getUuid()->toString(), $first['uuid']);
        self::assertSame('Warner Bros.', $first['studio']);

        self::assertSame([], $props['stack']);

        self::assertSame([
            'key'        => 'movies',
            'baseUrl'    => '/panel/movies',
            'drawerType' => 'movie',
            'canCreate'  => true,
            'canEdit'    => true,
            'canDelete'  => true,
            'exportUrl'  => '/panel/movies/export',
            // A resource declaring no views gets the table alone, and the
            // client renders no switcher for a single option — so adding views
            // left every existing listing looking exactly as it did.
            'views'      => [[
                'key'   => 'table',
                'label' => 'Table',
                'icon'  => 'bars-3',
                'type'  => 'table',
            ]],
            'view'       => 'table',
            // False here because MovieResource declares no board — the move
            // route is emitted per board view, not per resource.
            'canMove'    => false,
        ], $props['resource']);

        self::assertArrayNotHasKey('board', $props, 'A table view carries no board payload.');

        self::assertSame(
            ['title' => true, 'year' => true, 'rating' => true, 'studio' => false],
            array_column($props['table']['columns'], 'sortable', 'key'),
            'Sortability comes from the list query; studio opted out.',
        );
        self::assertSame('/panel/movies/{id}', $props['table']['recordUrl']);

        self::assertSame(['user' => null], $props['auth']);
        self::assertSame([], $props['flash']);
    }

    /**
     * A resource with a show route has a record URL without saying so: the
     * fixture declares none, and the props carry the route's template. That
     * used to be two declarations — `recordUrl()` and `linksToRecord()` —
     * where forgetting either left rows that looked clickable and did nothing.
     */
    public function testTheRecordUrlIsDerivedFromTheShowRoute(): void
    {
        $this->seed();

        $props = $this->renderProps($this->listing(new MovieResource()));

        self::assertNull((new MovieResource())->tableSchema()->declaredRecordUrl(), 'Precondition: the fixture declares no record URL.');
        self::assertSame('/panel/movies/{id}', $props['table']['recordUrl']);
    }

    /** A declared record URL wins, for rows that open something other than their own drawer. */
    public function testADeclaredRecordUrlOverridesTheShowRoute(): void
    {
        $this->seed();

        $resource = new class () extends MovieResource {
            public function tableSchema(): TableSchema
            {
                return parent::tableSchema()->recordUrl('/panel/films/{id}');
            }
        };

        // The routes are the fixture's; the anonymous subclass only changes the schema.
        $props = $this->renderProps($this->listing($resource, urls: $this->movieRoutes()));

        self::assertSame('/panel/films/{id}', $props['table']['recordUrl']);
    }

    /**
     * A linked cell with nowhere to go is a declaration error, not a row that
     * silently does nothing — and the message says which column and what to do.
     */
    public function testALinkingColumnWithNoRecordUrlIsRefused(): void
    {
        $this->seed();

        $urls = $this->urlGeneratorFromConfig(
            'function (PanelResourceConfigurator $panel): void { '
            . '$panel->resource(\\' . MovieResource::class . '::class)->only([\'index\']); }',
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Column "title" links to the record, but ' . MovieResource::class . '\'s table has no record URL: generate its show route, or declare ->recordUrl() on the TableSchema.');

        $this->listing(new MovieResource(), urls: $urls)->render();
    }

    /**
     * The client re-hydrates its controls from `filters`, so every parameter
     * it can send must come back in the shape it sent.
     */
    public function testTheFiltersPropEchoesEveryActiveParameter(): void
    {
        $this->seed();

        $amblin      = $this->studio('Amblin')->getUuid()->toString();
        $constraints = [['key' => 'year', 'operator' => 'gte', 'value' => '1990']];

        $props = $this->renderProps($this->listing(new MovieResource(), [
            'search'      => 'j',
            'trashed'     => 'with',
            'sort'        => '-year',
            'group'       => 'year',
            'constraints' => $constraints,
            'studio'      => $amblin,
            'released'    => '1',
        ]));

        self::assertSame([
            'search'      => 'j',
            'trashed'     => 'with',
            'sort'        => '-year',
            'group'       => 'year',
            'constraints' => $constraints,
            'studio'      => $amblin,
            'released'    => '1',
        ], $props['filters']);

        self::assertSame(['Jurassic Park'], $this->titles($props), 'And they all applied at once.');
    }

    /** JSON:API's `filter[search]` is accepted alongside the bare key. */
    public function testSearchMayArriveUnderTheJsonApiFilterKey(): void
    {
        $this->seed();

        $props = $this->renderProps($this->listing(new MovieResource(), ['filter' => ['search' => 'jaws']]));

        self::assertSame('jaws', $props['filters']['search']);
        self::assertSame(['Jaws'], $this->titles($props));
    }

    public function testTheComponentComesFromIndexComponent(): void
    {
        $this->seed();

        $resource = new class extends MovieResource {
            public function indexComponent(): string
            {
                return 'Movies/Index';
            }
        };

        $this->renderProps($this->listing($resource, urls: $this->movieRoutes()));

        self::assertSame('Movies/Index', $this->renderer?->component);
    }

    // ── Pagination and search ────────────────────────────────────────────────

    /**
     * The page narrows; the total does not. A pager that reports the page's
     * own size as the total would never offer a second page.
     */
    public function testPaginationNarrowsThePageWhileTheTotalStaysTheFullCount(): void
    {
        $this->seed();

        $props = $this->renderProps($this->listing(new MovieResource(), [
            'page' => ['size' => '2', 'number' => '2'],
        ]));

        self::assertSame(['Jaws', 'Jurassic Park'], $this->titles($props));
        self::assertSame(5, $this->total($props));

        $meta = $props['movies']['meta'];
        self::assertSame(2, $meta['per_page']);
        self::assertSame(2, $meta['current_page']);
        self::assertSame(3, $meta['last_page']);
        self::assertSame(3, $meta['from']);
        self::assertSame(4, $meta['to']);
    }

    public function testThePageSizeDefaultsToTwentyFive(): void
    {
        $movies = [];

        for ($i = 1; $i <= 30; ++$i) {
            $movies[] = (new Movie())->setTitle(sprintf('Movie %02d', $i));
        }

        $this->persist(...$movies);
        $this->clear();

        $props  = $this->renderProps($this->listing(new MovieResource()));
        $titles = $this->titles($props);

        self::assertCount(25, $titles);
        self::assertSame('Movie 01', $titles[0]);
        self::assertSame('Movie 25', $titles[24]);
        self::assertSame(30, $this->total($props));
        self::assertSame(25, $props['movies']['meta']['per_page']);
        self::assertSame(2, $props['movies']['meta']['last_page']);
    }

    /**
     * The count runs through the same list query and the same schema filters
     * as the rows, so a search cannot leave the total advertising pages of
     * rows that will never appear.
     */
    public function testSearchNarrowsTheRowsAndTheTotalTogether(): void
    {
        $this->seed();

        $props = $this->renderProps($this->listing(new MovieResource(), ['search' => 'JAWS']));

        self::assertSame(['Jaws'], $this->titles($props), 'The fixture query searches case-insensitively.');
        self::assertSame(1, $this->total($props));
        self::assertSame(1, $props['movies']['meta']['last_page']);
    }

    // ── Sorting ──────────────────────────────────────────────────────────────

    public function testALeadingMinusSortsDescending(): void
    {
        $this->seed();

        $props = $this->renderProps($this->listing(new MovieResource(), ['sort' => '-year']));

        self::assertSame(
            ['Untitled Spielberg', 'Collateral', 'Heat', 'Jurassic Park', 'Jaws'],
            $this->titles($props),
        );
        self::assertSame('-year', $props['filters']['sort']);
    }

    /** `created_at` is the public name; the list query maps it onto `createdAt`. */
    public function testAMappedSortFieldOrdersByTheEntityProperty(): void
    {
        $this->seed();

        $props = $this->renderProps($this->listing(new MovieResource(), ['sort' => '-created_at']));

        self::assertSame(
            ['Jurassic Park', 'Untitled Spielberg', 'Collateral', 'Jaws', 'Heat'],
            $this->titles($props),
            'Reverse insert order, which is how createdAt was seeded.',
        );
    }

    /**
     * A field outside the list query's allowlist is dropped silently, and the
     * default sort takes over — the request may choose among sorts, never
     * invent one. The `filters.sort` prop still echoes what was *asked*, which
     * is what the client's sort indicator reads; rows follow the default.
     */
    public function testAnUnknownSortFieldFallsBackToTheDefaultSort(): void
    {
        $this->seed();

        $props = $this->renderProps($this->listing(new MovieResource(), ['sort' => '-synopsis']));

        self::assertSame(
            ['Collateral', 'Heat', 'Jaws', 'Jurassic Park', 'Untitled Spielberg'],
            $this->titles($props),
        );
        self::assertSame('-synopsis', $props['filters']['sort']);
    }

    /**
     * Rows that tie on the sort field are ordered by id, in the sort's own
     * direction. Without it the order among equals is whatever the engine
     * feels like, which is what makes rows repeat or vanish across pages and
     * makes prev/next disagree with the page.
     *
     * Persisted so that title order, id order and year all disagree: with
     * three equal years, only the id tiebreak explains the result.
     */
    public function testTiesOnTheSortFieldAreBrokenOnIdInTheSortDirection(): void
    {
        $this->persist(
            (new Movie())->setTitle('Zulu')->setYear(1964),
            (new Movie())->setTitle('Alpha')->setYear(1964),
            (new Movie())->setTitle('Omega')->setYear(1964),
        );
        $this->clear();

        $ascending  = $this->renderProps($this->listing(new MovieResource(), ['sort' => 'year']));
        $descending = $this->renderProps($this->listing(new MovieResource(), ['sort' => '-year']));

        self::assertSame(['Zulu', 'Alpha', 'Omega'], $this->titles($ascending), 'Equal years: id ascending.');
        self::assertSame(['Omega', 'Alpha', 'Zulu'], $this->titles($descending), 'Equal years: id descending.');
    }

    // ── Trashed ──────────────────────────────────────────────────────────────

    /**
     * The predicate is the list query's, selected by the `trashed` param the
     * listing passes through: absent hides deleted rows, `with` lifts the
     * scope, `only` inverts it.
     */
    public function testSoftDeletedRowsAreHiddenUnlessTrashedAsksForThem(): void
    {
        $this->seed();
        $this->softDelete('Jaws');

        $default = $this->renderProps($this->listing(new MovieResource()));
        $with    = $this->renderProps($this->listing(new MovieResource(), ['trashed' => 'with']));
        $only    = $this->renderProps($this->listing(new MovieResource(), ['trashed' => 'only']));

        self::assertSame(['Collateral', 'Heat', 'Jurassic Park', 'Untitled Spielberg'], $this->titles($default));
        self::assertSame(4, $this->total($default));

        self::assertSame(['Collateral', 'Heat', 'Jaws', 'Jurassic Park', 'Untitled Spielberg'], $this->titles($with));
        self::assertSame(5, $this->total($with));
        self::assertSame('with', $with['filters']['trashed']);

        self::assertSame(['Jaws'], $this->titles($only));
        self::assertSame(1, $this->total($only));
        self::assertSame('only', $only['filters']['trashed']);
    }

    /**
     * A resource may choose what "no trashed param" means — an archive that
     * lists deleted rows by default — and the request still overrides it.
     */
    public function testDefaultTrashedIsHonouredWhenTheRequestNamesNone(): void
    {
        $this->seed();
        $this->softDelete('Jaws');

        $resource = new class extends MovieResource {
            public function defaultTrashed(): string
            {
                return 'with';
            }
        };

        $default  = $this->renderProps($this->listing($resource, urls: $this->movieRoutes()));
        $explicit = $this->renderProps($this->listing($resource, ['trashed' => 'only'], urls: $this->movieRoutes()));

        self::assertSame(5, $this->total($default));
        self::assertSame('with', $default['filters']['trashed'], 'The effective value is echoed, not the absent one.');

        self::assertSame(['Jaws'], $this->titles($explicit));
        self::assertSame('only', $explicit['filters']['trashed']);
    }

    // ── Scope ────────────────────────────────────────────────────────────────

    /** A resource whose listing shows only released movies. */
    private function releasedOnlyResource(): MovieResource
    {
        return $this->withPermissions(new class extends Permissions {
            public function scope(QueryBuilder $qb, string $alias, ?object $user): void
            {
                $qb->andWhere("{$alias}.released = :scopeReleased")->setParameter('scopeReleased', true);
            }
        });
    }

    /**
     * The scope rides along with the schema filters, so it reaches every
     * query that has to agree: the rows, their count and the footer
     * aggregates. A scope applied to only some of them advertises rows that
     * cannot be opened, or a total the page contradicts.
     */
    public function testTheScopeNarrowsRowsTotalAndSummaries(): void
    {
        $this->seed();

        $props = $this->renderProps($this->listing($this->releasedOnlyResource(), urls: $this->movieRoutes()));

        self::assertSame(['Collateral', 'Heat', 'Jaws', 'Jurassic Park'], $this->titles($props));
        self::assertSame(4, $this->total($props));

        $summaries = $this->summaries($props);
        self::assertSame(4.0, $summaries['title'][0]['value']);
        self::assertSame(1975.0, $summaries['year'][0]['value']);
        self::assertSame(2004.0, $summaries['year'][1]['value'], 'The unreleased 2027 title is outside the scope.');
        self::assertEqualsWithDelta(8.025, $summaries['rating'][0]['value'], 0.0001);
    }

    /** The scope is the resource's chance to ask *who* is looking. */
    public function testTheScopeReceivesTheViewer(): void
    {
        $this->seed();

        $permissions = new class extends Permissions {
            public ?object $seenUser = null;

            public function scope(QueryBuilder $qb, string $alias, ?object $user): void
            {
                $this->seenUser = $user;
            }
        };

        $viewer = $this->createStub(UserInterface::class);

        $this->renderProps($this->listing($this->withPermissions($permissions), [], $viewer, $this->movieRoutes()));

        self::assertSame($viewer, $permissions->seenUser);
    }

    // ── Summaries ────────────────────────────────────────────────────────────

    /**
     * A footer aggregate answers for what the user is looking at — the whole
     * filtered set — not for the handful of rows on the current page, which
     * is the only thing a client-side sum could see.
     */
    public function testSummariesAggregateTheFilteredSetNotThePage(): void
    {
        $this->seed();

        $props = $this->renderProps($this->listing(new MovieResource(), [
            'studio' => $this->studio('Amblin')->getUuid()->toString(),
            'page'   => ['size' => '2'],
        ]));

        self::assertSame(['Jaws', 'Jurassic Park'], $this->titles($props), 'Two of Amblin\'s three on this page.');

        $summaries = $this->summaries($props);

        self::assertSame(['title', 'year', 'rating'], array_keys($summaries), 'Keyed by column key.');
        self::assertSame([['type' => 'count', 'label' => 'Movies', 'value' => 3.0]], $summaries['title']);
        self::assertSame([
            ['type' => 'min', 'label' => 'Min', 'value' => 1975.0],
            ['type' => 'max', 'label' => 'Max', 'value' => 2027.0],
        ], $summaries['year']);

        self::assertSame('avg', $summaries['rating'][0]['type']);
        self::assertSame('Avg', $summaries['rating'][0]['label']);
        // AVG skips the unreleased title's null rating rather than counting it as zero.
        self::assertEqualsWithDelta(8.15, $summaries['rating'][0]['value'], 0.0001);
    }

    /**
     * With no matching rows MIN, MAX and AVG have nothing to say and come
     * back null; COUNT of nothing is zero, which is an answer.
     */
    public function testSummariesAreNullWhenNothingMatches(): void
    {
        $this->seed();

        $props = $this->renderProps($this->listing(new MovieResource(), ['search' => 'no such movie']));

        self::assertSame(0, $this->total($props));

        $summaries = $this->summaries($props);
        self::assertSame(0.0, $summaries['title'][0]['value']);
        self::assertNull($summaries['year'][0]['value']);
        self::assertNull($summaries['year'][1]['value']);
        self::assertNull($summaries['rating'][0]['value']);
    }

    // ── Grouping ─────────────────────────────────────────────────────────────

    /**
     * The group's ordering goes *first*, ahead of the requested sort, or the
     * rows of one group would be scattered through the page and the client's
     * heading rows would repeat. Within a group the requested sort still
     * decides.
     */
    public function testGroupingClustersRowsAheadOfTheSort(): void
    {
        $this->seed();
        $this->persist(
            (new Movie())->setTitle('Casino')->setYear(1995),
            (new Movie())->setTitle('Se7en')->setYear(1995),
        );
        $this->clear();

        $grouped = $this->renderProps($this->listing(new MovieResource(), ['group' => 'year']));

        self::assertSame(
            ['Jaws', 'Jurassic Park', 'Casino', 'Heat', 'Se7en', 'Collateral', 'Untitled Spielberg'],
            $this->titles($grouped),
            'Year ascending, then the default title sort inside 1995.',
        );
        self::assertSame('year', $grouped['filters']['group']);

        $reversed = $this->renderProps($this->listing(new MovieResource(), ['group' => 'year', 'sort' => '-title']));

        self::assertSame(
            ['Jaws', 'Jurassic Park', 'Se7en', 'Heat', 'Casino', 'Collateral', 'Untitled Spielberg'],
            $this->titles($reversed),
            'Still clustered by year; title descending inside 1995.',
        );
    }

    /** A group the schema does not declare is not a grouping. */
    public function testAnUndeclaredGroupIsIgnored(): void
    {
        $this->seed();

        $props = $this->renderProps($this->listing(new MovieResource(), ['group' => 'studio']));

        self::assertSame(
            ['Collateral', 'Heat', 'Jaws', 'Jurassic Park', 'Untitled Spielberg'],
            $this->titles($props),
        );
    }

    // ── Filter options ───────────────────────────────────────────────────────

    /**
     * A relation-backed filter ships as plain options — the related field as
     * value, the label field as label, ordered by label — so the client never
     * learns the filter was a relation at all.
     */
    public function testRelationBackedFilterOptionsAreResolvedOrderedByLabel(): void
    {
        $this->seed();

        $props   = $this->renderProps($this->listing(new MovieResource()));
        $filters = array_column($props['table']['filters'], null, 'key');

        self::assertSame([
            ['value' => $this->studio('Amblin')->getUuid()->toString(), 'label' => 'Amblin'],
            ['value' => $this->studio('Warner Bros.')->getUuid()->toString(), 'label' => 'Warner Bros.'],
        ], $filters['studio']['options']);
        self::assertArrayNotHasKey('optionsTruncated', $filters['studio'], 'Only emitted when the bound was hit.');

        self::assertArrayNotHasKey('options', $filters['released'], 'A ternary has its two labels, not options.');
        self::assertSame('Yes', $filters['released']['trueLabel']);
        self::assertSame('With Deleted', $filters['trashed']['trueLabel']);
    }

    /**
     * Past the threshold the list is cut and the cut is *reported* — the
     * panel's rule that a bound it imposes must be visible, rather than the
     * dropdown silently ending early.
     */
    public function testFilterOptionsBeyondTheThresholdAreTruncatedAndSaySo(): void
    {
        $studios = [];

        for ($i = 1; $i <= 101; ++$i) {
            $studios[] = (new Studio())->setName(sprintf('Studio %03d', $i));
        }

        $this->persist(...$studios);
        $this->clear();

        $props   = $this->renderProps($this->listing(new MovieResource()));
        $filters = array_column($props['table']['filters'], null, 'key');

        self::assertCount(100, $filters['studio']['options']);
        self::assertSame('Studio 001', $filters['studio']['options'][0]['label']);
        self::assertSame('Studio 100', $filters['studio']['options'][99]['label']);
        self::assertTrue($filters['studio']['optionsTruncated']);
    }

    // ── Row and bulk actions ─────────────────────────────────────────────────

    /**
     * A resource that declares no actions gets the standard trio, each
     * derived from whether its route exists — and the URL templates come
     * from the router, not from string-building on the key.
     */
    public function testRowActionsDeriveFromTheRoutesWhenNoneAreDeclared(): void
    {
        $this->seed();

        $props = $this->renderProps($this->listing(new MovieResource()));

        self::assertSame(
            ['view' => 'drawer', 'edit' => 'visit', 'delete' => 'delete'],
            $this->actionBehaviours($props),
        );

        self::assertSame('/panel/movies/{id}/edit', $this->action($props, 'edit')['urlTemplate']);

        $delete = $this->action($props, 'delete');
        self::assertSame('/panel/movies/{id}', $delete['urlTemplate']);
        self::assertSame('/panel/movies/{id}/delete-preview', $delete['previewUrl']);
        self::assertSame('danger', $delete['color']);

        self::assertArrayNotHasKey('urlTemplate', $this->action($props, 'view'), 'View opens the row\'s own recordUrl.');
    }

    /** Deleting many rows is the same permission as deleting one, at its own route. */
    public function testTheBulkDeleteIsOfferedWhenItsRouteExistsAndTheViewerMayDelete(): void
    {
        $this->seed();

        $props = $this->renderProps($this->listing(new MovieResource()));

        self::assertSame(['delete' => '/panel/movies/bulk-delete'], $this->bulkActionUrls($props));
        self::assertSame('post', $props['table']['bulkActionItems'][0]['behaviour']);
        self::assertTrue($props['table']['bulkActionItems'][0]['confirm']);
        self::assertTrue($props['table']['bulkActions'], 'Selection is what a bulk action needs.');
    }

    /**
     * With only the read routes generated there is nothing to edit or delete
     * with, so the listing offers neither — and says so in the resource meta,
     * which is what the page reads for its Create button.
     */
    public function testOnlyTheViewActionSurvivesWhenJustIndexAndShowAreRouted(): void
    {
        $this->seed();

        $urls = $this->urlGeneratorFromConfig(
            'function (PanelResourceConfigurator $panel): void { '
            . '$panel->resource(\\' . MovieResource::class . '::class)->only([\'index\', \'show\']); }',
        );

        $props = $this->renderProps($this->listing(new MovieResource(), urls: $urls));

        self::assertSame(['view' => 'drawer'], $this->actionBehaviours($props));
        self::assertSame([], $this->bulkActionUrls($props));

        self::assertFalse($props['resource']['canCreate']);
        self::assertFalse($props['resource']['canEdit']);
        self::assertFalse($props['resource']['canDelete']);
        self::assertSame('/panel/movies/export', $props['resource']['exportUrl'], 'Export rides the index opt-in.');
    }

    /**
     * The base URL is asked of the router: every write URL the client builds
     * hangs off it, so a `->prefix('/admin')` resource must send /admin.
     */
    public function testThePrefixedResourceSendsItsPrefixedBaseUrl(): void
    {
        $this->seed();

        $urls = $this->urlGeneratorFromConfig(
            'function (PanelResourceConfigurator $panel): void { '
            . '$panel->resource(\\' . MovieResource::class . '::class)->prefix(\'/admin\'); }',
        );

        $props = $this->renderProps($this->listing(new MovieResource(), urls: $urls));

        self::assertSame('/admin/movies', $props['resource']['baseUrl']);
        self::assertSame('/admin/movies/export', $props['resource']['exportUrl']);
    }

    /** Route existence says what the resource supports; the permission says what this viewer may do. */
    public function testAViewerWhoMayNotEditLosesTheEditAction(): void
    {
        $this->seed();

        $resource = $this->withPermissions(new class extends Permissions {
            public function edit(?object $record, ?object $user): bool
            {
                return false;
            }
        });

        $props = $this->renderProps($this->listing($resource, urls: $this->movieRoutes()));

        self::assertSame(['view' => 'drawer', 'delete' => 'delete'], $this->actionBehaviours($props));
        self::assertFalse($props['resource']['canEdit']);
        self::assertTrue($props['resource']['canDelete']);
        self::assertTrue($props['resource']['canCreate']);
    }

    public function testAViewerWhoMayNotDeleteLosesDeleteAndTheBulkDelete(): void
    {
        $this->seed();

        $resource = $this->withPermissions(new class extends Permissions {
            public function delete(?object $record, ?object $user): bool
            {
                return false;
            }
        });

        $props = $this->renderProps($this->listing($resource, urls: $this->movieRoutes()));

        self::assertSame(['view' => 'drawer', 'edit' => 'visit'], $this->actionBehaviours($props));
        self::assertSame([], $this->bulkActionUrls($props));
        self::assertFalse($props['resource']['canDelete']);
        self::assertTrue($props['table']['bulkActions'], 'The schema still asked for selection; only the button is gone.');
    }

    /**
     * A resource that names its own actions, with its own URLs and its own
     * permissions. Built with a constructor so one class serves both
     * permission outcomes.
     */
    private function declaringResource(bool $mayEdit, bool $mayDelete): MovieResource
    {
        $permissions = new class ($mayEdit, $mayDelete) extends Permissions {
            public function __construct(
                private readonly bool $mayEdit,
                private readonly bool $mayDelete,
            ) {
                parent::__construct();
            }

            public function edit(?object $record, ?object $user): bool
            {
                return $this->mayEdit;
            }

            public function delete(?object $record, ?object $user): bool
            {
                return $this->mayDelete;
            }
        };

        return new class ($permissions) extends MovieResource {
            public function __construct(private readonly Permissions $permissions)
            {
            }

            public function permissions(): Permissions
            {
                return $this->permissions;
            }

            public function tableSchema(): TableSchema
            {
                return parent::tableSchema()->actions([
                    RowAction::view(),
                    RowAction::edit('/custom/movies/{id}/edit'),
                    RowAction::delete('/custom/movies/{id}'),
                    RowAction::make('restore'),
                    RowAction::make('archive'),
                ]);
            }
        };
    }

    /**
     * Declared actions are the resource's answer, so the routes are not
     * consulted — the URLs stay exactly as declared. Permission is the only
     * thing that still removes one: edit rides edit(), delete *and*
     * restore ride delete(), and anything else is untouched.
     */
    public function testDeclaredActionsAreKeptWithTheirOwnUrlsButPermissionGated(): void
    {
        $this->seed();

        $allowed = $this->renderProps($this->listing($this->declaringResource(true, true), urls: $this->movieRoutes()));

        self::assertSame(
            ['view' => 'drawer', 'edit' => 'visit', 'delete' => 'delete', 'restore' => 'handler', 'archive' => 'handler'],
            $this->actionBehaviours($allowed),
        );
        self::assertSame('/custom/movies/{id}/edit', $this->action($allowed, 'edit')['urlTemplate']);
        self::assertSame('/custom/movies/{id}', $this->action($allowed, 'delete')['urlTemplate']);
        self::assertArrayNotHasKey('previewUrl', $this->action($allowed, 'delete'), 'Not derived: the resource declared none.');

        $denied = $this->renderProps($this->listing($this->declaringResource(false, false), urls: $this->movieRoutes()));

        self::assertSame(['view' => 'drawer', 'archive' => 'handler'], $this->actionBehaviours($denied));
    }

    /** Declared bulk actions replace the derived delete rather than joining it. */
    public function testDeclaredBulkActionsReplaceTheDerivedDelete(): void
    {
        $this->seed();

        $resource = new class extends MovieResource {
            public function tableSchema(): TableSchema
            {
                return parent::tableSchema()->bulkActions(BulkAction::post('archive', '/panel/movies/bulk-archive'));
            }
        };

        $props = $this->renderProps($this->listing($resource, urls: $this->movieRoutes()));

        self::assertSame(['archive' => '/panel/movies/bulk-archive'], $this->bulkActionUrls($props));
    }

    // ── Clones ───────────────────────────────────────────────────────────────

    /**
     * An action layers a drawer or an extra prop onto a listing it did not
     * build, so both must leave the original alone — the same listing may be
     * rendered again without the overlay.
     */
    public function testWithPropsAndWithDrawerReturnClonesLeavingTheOriginalUntouched(): void
    {
        $this->seed();

        $original = $this->listing(new MovieResource());
        $stack    = [['type' => 'movie', 'id' => $this->movie('Heat')->getUuid()->toString()]];

        $overlaid = $original
            ->withProps(['banner' => 'Now showing'])
            ->withDrawer($stack);

        self::assertNotSame($original, $overlaid);

        $overlaidProps = $this->renderProps($overlaid);
        self::assertSame($stack, $overlaidProps['stack']);
        self::assertSame('Now showing', $overlaidProps['banner']);

        $originalProps = $this->renderProps($original);
        self::assertSame([], $originalProps['stack']);
        self::assertArrayNotHasKey('banner', $originalProps);

        self::assertSame(
            $this->titles($overlaidProps),
            $this->titles($originalProps),
            'Neither overlay touches the rows.',
        );
    }

    /** Successive withProps() calls accumulate; later keys win. */
    public function testWithPropsAccumulatesAcrossCalls(): void
    {
        $this->seed();

        $props = $this->renderProps(
            $this->listing(new MovieResource())
                ->withProps(['banner' => 'first', 'notice' => 'kept'])
                ->withProps(['banner' => 'second']),
        );

        self::assertSame('second', $props['banner']);
        self::assertSame('kept', $props['notice']);
    }

    // ── Navigation ───────────────────────────────────────────────────────────

    private function showUrl(string $title): string
    {
        return '/panel/movies/' . $this->movie($title)->getUuid()->toString();
    }

    /**
     * From the middle of the default order the neighbours are the adjacent
     * titles; from either end the missing side is null rather than wrapping.
     * With no query string the URL is the bare show route.
     */
    /**
     * A sort on a field the query does not allow is dropped, and the listing
     * orders by the query's default; the neighbours must follow that same
     * order. Reversing the dropped entry used to reverse nothing, so
     * "previous" ran ascending and returned the first row below instead of
     * the nearest one: from Jaws it answered Collateral, skipping Heat.
     */
    public function testNavigationUrlsFollowTheDefaultSortWhenTheRequestedFieldIsNotSortable(): void
    {
        $this->seed();

        $listing = $this->listing(new MovieResource(), ['sort' => 'nonsense']);
        $urls    = $listing->navigationUrls($this->movie('Jaws'));

        // The request's own query string rides along on the links unchanged.
        self::assertSame($this->showUrl('Jurassic Park') . '?sort=nonsense', $urls['next']);
        self::assertSame($this->showUrl('Heat') . '?sort=nonsense', $urls['previous'], 'The nearest predecessor in the default order, not the first row.');
    }

    public function testNavigationUrlsStepThroughTheDefaultSort(): void
    {
        $this->seed();

        $listing = $this->listing(new MovieResource());

        self::assertSame(
            ['next' => $this->showUrl('Jurassic Park'), 'previous' => $this->showUrl('Heat')],
            $listing->navigationUrls($this->movie('Jaws')),
        );

        self::assertSame(
            ['next' => $this->showUrl('Heat'), 'previous' => null],
            $listing->navigationUrls($this->movie('Collateral')),
        );

        self::assertSame(
            ['next' => null, 'previous' => $this->showUrl('Jurassic Park')],
            $listing->navigationUrls($this->movie('Untitled Spielberg')),
        );
    }

    /**
     * Prev/next walk the list the user is looking at: the same sort, the same
     * filters, and the query string carried along so the neighbour's page
     * keeps them. A row the filter hides is stepped over, not landed on.
     */
    public function testNavigationUrlsRespectTheSortAndFiltersAndCarryTheQueryString(): void
    {
        $this->seed();

        // Amblin by year descending: Untitled Spielberg (2027), Jurassic Park
        // (1993), Jaws (1975). Heat (1995) sits between the last two by year
        // but belongs to Warner, so it must be skipped.
        $query   = ['sort' => '-year', 'studio' => $this->studio('Amblin')->getUuid()->toString()];
        $listing = $this->listing(new MovieResource(), $query);
        $suffix  = '?' . http_build_query($query);

        self::assertSame(
            [
                'next'     => $this->showUrl('Jaws') . $suffix,
                'previous' => $this->showUrl('Untitled Spielberg') . $suffix,
            ],
            $listing->navigationUrls($this->movie('Jurassic Park')),
        );

        self::assertSame(
            ['next' => $this->showUrl('Jurassic Park') . $suffix, 'previous' => null],
            $listing->navigationUrls($this->movie('Untitled Spielberg')),
            'First under DESC: nothing before it.',
        );
    }

    /** A search narrows the walk the same way a declared filter does. */
    public function testNavigationUrlsHonourTheSearch(): void
    {
        $this->seed();

        $listing = $this->listing(new MovieResource(), ['search' => 'j']);

        self::assertSame(
            ['next' => $this->showUrl('Jurassic Park') . '?search=j', 'previous' => null],
            $listing->navigationUrls($this->movie('Jaws')),
            'Collateral and Heat precede Jaws by title but do not match the search.',
        );
    }

    /**
     * Ties on the sort field are walked in id order, matching the page's
     * tiebreak — otherwise stepping "next" from one of three equal rows could
     * land on a row the page showed *before* it.
     */
    public function testNavigationUrlsBreakTiesTheWayThePageDoes(): void
    {
        $zulu  = (new Movie())->setTitle('Zulu')->setYear(1964);
        $alpha = (new Movie())->setTitle('Alpha')->setYear(1964);
        $omega = (new Movie())->setTitle('Omega')->setYear(1964);

        $this->persist($zulu, $alpha, $omega);
        $this->clear();

        $listing = $this->listing(new MovieResource(), ['sort' => '-year']);

        // Page order under -year: Omega, Alpha, Zulu (id descending).
        self::assertSame(
            [
                'next'     => '/panel/movies/' . $zulu->getUuid()->toString() . '?sort=-year',
                'previous' => '/panel/movies/' . $omega->getUuid()->toString() . '?sort=-year',
            ],
            $listing->navigationUrls($alpha),
        );
    }

    // ── allMatching ──────────────────────────────────────────────────────────

    /**
     * The export's question is "what am I looking at", so the answer honours
     * the filters and ignores the page — the same filters the rows and the
     * count saw, through the same code path.
     */
    public function testAllMatchingReturnsEveryFilteredRowUnpaginated(): void
    {
        $this->seed();

        $listing = $this->listing(new MovieResource(), [
            'studio' => $this->studio('Amblin')->getUuid()->toString(),
            'page'   => ['size' => '1'],
        ]);

        self::assertSame(
            ['Jaws', 'Jurassic Park', 'Untitled Spielberg'],
            $this->titlesOf($listing->allMatching()),
            'All three of Amblin\'s, in list order, despite a page size of one.',
        );
    }

    /**
     * A selection narrows the export to those ids. Ids are canonicalised
     * first, so an upper-case uuid still matches and a malformed one is
     * dropped rather than failing the whole file.
     */
    public function testAllMatchingNarrowsToTheGivenUuidsAndDropsMalformedOnes(): void
    {
        $this->seed();

        $listing = $this->listing(new MovieResource());

        self::assertSame(
            ['Heat', 'Jaws'],
            $this->titlesOf($listing->allMatching([
                strtoupper($this->movie('Heat')->getUuid()->toString()),
                'not-a-uuid',
                $this->movie('Jaws')->getUuid()->toString(),
            ])),
        );
    }

    /**
     * When no usable id survives canonicalisation the result is empty and
     * no query runs. Proven with a scope that would make any executed query
     * throw: the same listing throws when asked for everything, and returns
     * `[]` when asked for nothing usable.
     */
    public function testAllMatchingWithNoUsableUuidReturnsEmptyWithoutQuerying(): void
    {
        $this->seed();

        $trapped = $this->withPermissions(new class extends Permissions {
            public function scope(QueryBuilder $qb, string $alias, ?object $user): void
            {
                // Valid to build, invalid to execute: Movie has no such field.
                $qb->andWhere("{$alias}.noSuchField = 1");
            }
        });

        $listing = $this->listing($trapped, urls: $this->movieRoutes());

        self::assertSame([], $listing->allMatching(['not-a-uuid', '']));
        self::assertSame([], $listing->allMatching([]));

        $this->expectException(QueryException::class);
        $listing->allMatching();
    }

    /**
     * A hand-crafted id list cannot reach past the scope: the selection is
     * applied *on top of* the scoped query, never instead of it.
     */
    public function testAllMatchingStillAppliesTheScope(): void
    {
        $this->seed();

        $listing = $this->listing($this->releasedOnlyResource(), urls: $this->movieRoutes());

        self::assertSame(
            ['Heat'],
            $this->titlesOf($listing->allMatching([
                $this->movie('Untitled Spielberg')->getUuid()->toString(),
                $this->movie('Heat')->getUuid()->toString(),
            ])),
            'The unreleased title was asked for by id and is still not returned.',
        );

        self::assertCount(4, $listing->allMatching(), 'Unselected: the scope alone.');
    }

    /**
     * The trashed control's default is the resource's decision. A resource
     * listing deleted rows by default hands the client that value, so the
     * control shows it without counting as a filter the viewer applied; a
     * resource without a default sends none.
     */
    public function testTheTrashedFilterCarriesTheResourcesDefault(): void
    {
        $this->seed();

        $plain = $this->renderProps($this->listing(new MovieResource()));
        $trashedFilter = array_values(array_filter($plain['table']['filters'], static fn (array $f): bool => $f['key'] === 'trashed'))[0];
        self::assertArrayNotHasKey('default', $trashedFilter);

        $withDeleted = new class extends MovieResource {
            public function defaultTrashed(): string
            {
                return 'with';
            }
        };

        $props = $this->renderProps($this->listing($withDeleted, urls: $this->movieRoutes()));
        $trashedFilter = array_values(array_filter($props['table']['filters'], static fn (array $f): bool => $f['key'] === 'trashed'))[0];

        self::assertSame('with', $trashedFilter['default']);
        self::assertSame('with', $props['filters']['trashed'], 'The effective value is still echoed.');
    }
}
