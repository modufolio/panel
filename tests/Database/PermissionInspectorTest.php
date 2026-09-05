<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Modufolio\Panel\Field\TextType;
use Modufolio\Panel\Form\FormResolver;
use Modufolio\Panel\Inspection\PermissionInspector;
use Modufolio\Panel\Inspection\PermissionReport;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\Permissions;
use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\AdminMovieResource;
use Modufolio\Panel\Tests\Fixture\MovieResource;
use Modufolio\Panel\Tests\Fixture\UserMovieResource;
use Modufolio\Panel\Tests\Routing\ReadOnlyResource;
use Symfony\Component\Routing\RouteCollection;
use Modufolio\Panel\Form\Form;

/**
 * The four permission layers read back together, for a stand-in user per
 * role, without a request. Anonymous MovieResource subclasses stand in for
 * resources that override one hook at a time; routes come from the package's
 * own loader for the named class.
 */
final class PermissionInspectorTest extends DoctrineTestCase
{
    private const ADMIN = 'ROLE_ADMIN';
    private const USER  = 'ROLE_USER';
    private const SUPER = 'ROLE_SUPER_ADMIN';

    /** A user carrying exactly the roles given — what a stored user's getRoles() returns. */
    private static function user(string ...$roles): object
    {
        return new class(array_values($roles)) {
            /** @param list<string> $roles */
            public function __construct(private readonly array $roles)
            {
            }

            /** @return list<string> */
            public function getRoles(): array
            {
                return $this->roles;
            }
        };
    }

    /** @return \Closure(string): object */
    private static function literalUsers(): \Closure
    {
        return static fn (string $role): object => self::user($role);
    }

    /** The playground's hierarchy: a super admin reaches admin, an admin reaches user. */
    private static function hierarchy(): \Closure
    {
        return static fn (string $role): array => match ($role) {
            self::SUPER => [self::SUPER, self::ADMIN, self::USER],
            self::ADMIN => [self::ADMIN, self::USER],
            default     => [$role],
        };
    }

    /**
     * Routes for the movie resource — the plain fixture, or one whose
     * permissions name a role, since roles reach the routes from there.
     *
     * @param class-string<MovieResource> $resourceClass
     */
    private function movieRoutes(string $resourceClass = MovieResource::class, string $options = ''): RouteCollection
    {
        return $this->routesFromConfig(
            'function (PanelResourceConfigurator $panel): void { $panel->resource(\\' . $resourceClass . '::class)' . $options . '; }',
        );
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

    /**
     * @param list<string> $roles
     */
    private function inspect(
        RouteCollection $routes,
        PanelResource $resource,
        array $roles,
        ?\Closure $reachable = null,
    ): PermissionReport {
        $inspector = new PermissionInspector(
            $routes,
            static fn (string $class): PanelResource => $resource,
            new FormResolver(self::em()),
            $reachable,
        );

        return $inspector->inspect([MovieResource::class], $roles, self::literalUsers());
    }

    /** @return array<string, mixed> */
    private function movies(PermissionReport $report): array
    {
        return $report->resources['movies'];
    }

    /** @return list<string> */
    private static function kinds(PermissionReport $report): array
    {
        return array_values(array_unique(array_column($report->notes, 'kind')));
    }

    // ── Routes ───────────────────────────────────────────────────────────────

    public function testRouteAdmissionFollowsTheDeclaredRoles(): void
    {
        $report = $this->inspect(
            $this->movieRoutes(AdminMovieResource::class, "->only(['index', 'edit'])"),
            new MovieResource(),
            [self::ADMIN, self::USER],
        );

        $movies = $this->movies($report);

        self::assertSame(
            ['movies', 'movies_export', 'movies_edit', 'movies_update', 'movies_relation_options', 'movies_relation_create', 'movies_relation_store'],
            $movies['routes'],
        );
        self::assertSame('/panel', $movies['prefix']);
        self::assertSame(MovieResource::class, $movies['class']);

        self::assertSame([true], array_values(array_unique($movies['roles'][self::ADMIN]['routes'])), 'Admin reaches every route.');
        self::assertSame([false], array_values(array_unique($movies['roles'][self::USER]['routes'])), 'User reaches none.');
    }

    /** Route roles are checked against what a role reaches, not the literal role alone. */
    public function testReachableRolesWidenRouteAdmission(): void
    {
        $report = $this->inspect(
            $this->movieRoutes(AdminMovieResource::class),
            new MovieResource(),
            [self::SUPER],
            self::hierarchy(),
        );

        self::assertTrue($this->movies($report)['roles'][self::SUPER]['routes']['movies']);
    }

    public function testUnguardedRoutesAreNoted(): void
    {
        $report = $this->inspect($this->movieRoutes(), new MovieResource(), [self::USER]);

        self::assertTrue($this->movies($report)['roles'][self::USER]['routes']['movies_destroy']);
        self::assertContains('unguarded', self::kinds($report));
    }

    // ── Hooks ────────────────────────────────────────────────────────────────

    /** A resource whose edit rule reads the literal role. */
    private function adminOnlyEdits(): MovieResource
    {
        return $this->withPermissions(new class extends Permissions {
            public function edit(?object $record, ?object $user): bool
            {
                return $user !== null && method_exists($user, 'getRoles') && in_array('ROLE_ADMIN', (array) $user->getRoles(), true);
            }
        });
    }

    public function testHookVerdictsAreTakenPerRoleAndOverridesAreMarked(): void
    {
        $report = $this->inspect($this->movieRoutes(UserMovieResource::class), $this->adminOnlyEdits(), [self::ADMIN, self::USER]);
        $movies = $this->movies($report);

        self::assertSame(['view' => true, 'create' => true, 'edit' => true, 'delete' => true], $movies['roles'][self::ADMIN]['can']);
        self::assertSame(['view' => true, 'create' => true, 'edit' => false, 'delete' => true], $movies['roles'][self::USER]['can']);

        self::assertTrue($movies['overrides']['edit']);
        self::assertFalse($movies['overrides']['delete']);
        self::assertFalse($movies['overrides']['scope']);
        self::assertStringContainsString('@anonymous', $movies['permissions'], 'The report names the permissions class.');

        $recordDependent = array_filter($report->notes, static fn (array $note): bool => $note['kind'] === 'record_dependent');
        self::assertCount(1, $recordDependent);
        self::assertStringContainsString('edit()', array_values($recordDependent)[0]['message']);
    }

    /**
     * The route admits ROLE_USER to the edit pages while the hook refuses it:
     * the request reaches the controller only to be turned away.
     */
    public function testARouteThatAdmitsWhatTheHookDeniesIsNoted(): void
    {
        $report = $this->inspect($this->movieRoutes(UserMovieResource::class), $this->adminOnlyEdits(), [self::USER]);

        $notes = array_values(array_filter($report->notes, static fn (array $note): bool => $note['kind'] === 'route_admits_hook_denies'));

        self::assertCount(1, $notes);
        self::assertSame(self::USER, $notes[0]['role']);
        self::assertStringContainsString('movies_edit', $notes[0]['message']);
        self::assertStringContainsString('edit()', $notes[0]['message']);
    }

    /**
     * A super admin reaches admin through the hierarchy, so the route lets
     * them in — and the hook, reading the literal role, refuses them. That
     * gap is the note's whole reason to exist.
     */
    public function testAHookReadingTheLiteralRoleShowsAsAHierarchyDivergence(): void
    {
        $report = $this->inspect(
            $this->movieRoutes(AdminMovieResource::class),
            $this->adminOnlyEdits(),
            [self::SUPER, self::ADMIN],
            self::hierarchy(),
        );

        $movies = $this->movies($report);
        self::assertTrue($movies['roles'][self::SUPER]['routes']['movies_edit'], 'The route honours the hierarchy…');
        self::assertFalse($movies['roles'][self::SUPER]['can']['edit'], '…the rule does not.');

        $notes = array_values(array_filter($report->notes, static fn (array $note): bool => $note['kind'] === 'hierarchy_divergence'));
        self::assertCount(1, $notes);
        self::assertSame(self::SUPER, $notes[0]['role']);
        self::assertStringContainsString('edit()', $notes[0]['message']);
    }

    public function testAScopeOverrideIsFlagged(): void
    {
        $scoped = $this->withPermissions(new class extends Permissions {
            public function scope(\Doctrine\ORM\QueryBuilder $qb, string $alias, ?object $user): void
            {
            }
        });

        $report = $this->inspect($this->movieRoutes(), $scoped, [self::USER]);

        self::assertTrue($this->movies($report)['overrides']['scope']);
    }

    // ── Fields ───────────────────────────────────────────────────────────────

    /** A hand-written form whose permissions gate one field's reading, one's writing for a role, and one's writing for everyone. */
    private function gatedForm(): MovieResource
    {
        $permissions = new class extends Permissions {
            private static function isAdmin(?object $user): bool
            {
                return $user !== null
                    && method_exists($user, 'getRoles')
                    && in_array('ROLE_ADMIN', (array) $user->getRoles(), true);
            }

            public function readable(string $field, ?object $user, ?object $record = null): bool
            {
                return $field !== 'secret' || self::isAdmin($user);
            }

            public function writable(string $field, ?object $user, ?object $record = null): bool
            {
                return match ($field) {
                    'rating' => self::isAdmin($user),
                    'year'   => false,
                    default  => true,
                };
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

            public function form(): Form
            {
                return Form::make()->fields([
                    'title'  => ['type' => TextType::class],
                    'rating' => ['type' => TextType::class],
                    'secret' => ['type' => TextType::class],
                    'year'   => ['type' => TextType::class],
                ]);
            }
        };
    }

    public function testFieldVerdictsPerRole(): void
    {
        $report = $this->inspect($this->movieRoutes(), $this->gatedForm(), [self::ADMIN, self::USER]);
        $movies = $this->movies($report);

        self::assertSame([
            'readable'    => ['title', 'rating', 'secret', 'year'],
            'readDenied'  => [],
            'writeDenied' => ['year'],
        ], $movies['roles'][self::ADMIN]['fields']);

        self::assertSame([
            'readable'    => ['title', 'rating', 'year'],
            'readDenied'  => ['secret'],
            'writeDenied' => ['rating', 'year'],
        ], $movies['roles'][self::USER]['fields']);

        self::assertTrue($movies['overrides']['readable']);
        self::assertTrue($movies['overrides']['writable']);
    }

    /** A guessed form lists what the resource named, nothing gated. */
    public function testAGuessedFormIsFullyReadable(): void
    {
        $report = $this->inspect($this->movieRoutes(), new MovieResource(), [self::USER]);

        self::assertSame(
            ['title', 'synopsis', 'year', 'runtime', 'rating', 'released', 'released_on', 'studio_id', 'tags', 'cast'],
            $this->movies($report)['roles'][self::USER]['fields']['readable'],
        );
    }

    /** A field gate a super admin loses by not carrying the literal admin role. */
    public function testFieldGatesReadingTheLiteralRoleDivergeToo(): void
    {
        $report = $this->inspect($this->movieRoutes(), $this->gatedForm(), [self::SUPER, self::ADMIN], self::hierarchy());

        $notes = array_values(array_filter($report->notes, static fn (array $note): bool => $note['kind'] === 'hierarchy_divergence'));
        self::assertCount(1, $notes);
        self::assertStringContainsString('reading secret', $notes[0]['message']);
        self::assertStringContainsString('writing rating', $notes[0]['message']);
    }

    /** A field rule that needs a record cannot be asked about the type; say so rather than fail. */
    public function testAFieldRuleNeedingARecordIsReportedNotFatal(): void
    {
        $needsRecord = $this->withPermissions(new class extends Permissions {
            public function readable(string $field, ?object $user, ?object $record = null): bool
            {
                if ($record === null) {
                    throw new \LogicException('needs a record');
                }

                return true;
            }
        });

        $report = $this->inspect($this->movieRoutes(), $needsRecord, [self::USER]);

        self::assertSame(['readable' => [], 'readDenied' => [], 'writeDenied' => []], $this->movies($report)['roles'][self::USER]['fields']);
        self::assertContains('access_threw', self::kinds($report));
    }

    // ── Discovery ────────────────────────────────────────────────────────────

    public function testResourceClassesAreDiscoveredFromTheRoutesInOrder(): void
    {
        $routes = $this->routesFromConfig(
            'function (PanelResourceConfigurator $panel): void {'
            . ' $panel->resource(\\' . ReadOnlyResource::class . '::class);'
            . ' $panel->resource(\\' . MovieResource::class . '::class);'
            . ' }',
        );

        self::assertSame([ReadOnlyResource::class, MovieResource::class], PermissionInspector::resourceClassesIn($routes));
    }

    public function testTheReportSerialisesWithItsThreeParts(): void
    {
        $report = $this->inspect($this->movieRoutes(), new MovieResource(), [self::USER]);

        self::assertSame(['roles', 'resources', 'notes'], array_keys($report->toArray()));
        self::assertSame([self::USER], $report->toArray()['roles']);
        self::assertSame(['movies'], array_keys($report->toArray()['resources']));
    }
}
