<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Resource;

use Modufolio\Appkit\Security\User\UserInterface;
use Modufolio\Panel\Resource\Permissions;
use Modufolio\Panel\Resource\ResourceCapabilities;
use Modufolio\Panel\Tests\Fixture\MovieResource;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * A capability is the conjunction of a route that exists and a permission
 * that allows — asked in one place, so no surface can ask half of it.
 */
final class ResourceCapabilitiesTest extends TestCase
{
    /** @param list<string> $operations route-name suffixes, `index` for the bare key */
    private static function routes(array $operations): UrlGenerator
    {
        $routes = new RouteCollection();

        foreach ($operations as $operation) {
            $name = $operation === 'index' ? 'movies' : 'movies_' . $operation;
            $path = match ($operation) {
                'index', 'create', 'store', 'bulk_destroy', 'export' => '/panel/movies/' . $operation,
                default => '/panel/movies/{uuid}/' . $operation,
            };

            $routes->add($name, new Route($path));
        }

        return new UrlGenerator($routes, new RequestContext());
    }

    private static function denying(): Permissions
    {
        return new class extends Permissions {
            public function create(?UserInterface $user): bool
            {
                return false;
            }

            public function edit(?object $record, ?UserInterface $user): bool
            {
                return $record === null;
            }

            public function delete(?object $record, ?UserInterface $user): bool
            {
                return false;
            }
        };
    }

    private static function resourceWith(Permissions $permissions): MovieResource
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

    public function testARouteThatExistsAndAPermissionThatAllowsIsACapability(): void
    {
        $capabilities = new ResourceCapabilities(new MovieResource(), self::routes(['index', 'create', 'edit', 'destroy', 'board_move', 'export']));

        self::assertTrue($capabilities->create());
        self::assertTrue($capabilities->edit());
        self::assertTrue($capabilities->delete());
        self::assertTrue($capabilities->move());
        self::assertTrue($capabilities->export());
    }

    public function testAMissingRouteRefusesWhateverThePermissionsSay(): void
    {
        $capabilities = new ResourceCapabilities(new MovieResource(), self::routes(['index', 'show']));

        self::assertFalse($capabilities->create());
        self::assertFalse($capabilities->edit());
        self::assertFalse($capabilities->delete());
        self::assertFalse($capabilities->move());
        self::assertFalse($capabilities->export());
        self::assertTrue($capabilities->has('show'));
    }

    public function testARefusingPermissionWinsOverAnExistingRoute(): void
    {
        $capabilities = new ResourceCapabilities(
            self::resourceWith(self::denying()),
            self::routes(['index', 'create', 'edit', 'destroy', 'board_move']),
        );

        self::assertFalse($capabilities->create());
        self::assertTrue($capabilities->edit(), 'The type may be edited.');
        self::assertFalse($capabilities->edit(new \stdClass()), 'This record may not.');
        self::assertFalse($capabilities->delete());
        self::assertTrue($capabilities->move(), 'A move needs the move route and the type-level edit permission, nothing else.');
    }

    public function testTemplatesCarryAnIdPlaceholderAndUrlsDoNot(): void
    {
        $capabilities = new ResourceCapabilities(new MovieResource(), self::routes(['index', 'edit', 'bulk_destroy']));

        self::assertSame('/panel/movies/{id}/edit', $capabilities->template('edit'));
        self::assertSame('/panel/movies/bulk_destroy', $capabilities->url('bulk_destroy'));
        self::assertSame('/panel/movies/index', $capabilities->url('index'));
        self::assertNull($capabilities->template('destroy'));
        self::assertNull($capabilities->url('export'));
    }

    public function testTheUrlMapNamesEveryOperationWithNullForTheMissingOnes(): void
    {
        $urls = (new ResourceCapabilities(new MovieResource(), self::routes(['index', 'show'])))->urls();

        self::assertSame(
            ['index', 'create', 'store', 'show', 'edit', 'update', 'patch', 'destroy', 'deletePreview', 'bulkDestroy', 'export', 'boardMove'],
            array_keys($urls),
        );
        self::assertSame('/panel/movies/{id}/show', $urls['show']);
        self::assertNull($urls['edit']);
    }

    public function testRouteLookupsAreAskedOfTheRouterOnce(): void
    {
        $generator = new class (self::routes(['index', 'edit'])) implements \Symfony\Component\Routing\Generator\UrlGeneratorInterface {
            public int $generated = 0;

            public function __construct(private readonly UrlGenerator $inner)
            {
            }

            /** @param array<string, mixed> $parameters */
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                $this->generated++;

                return $this->inner->generate($name, $parameters, $referenceType);
            }

            public function setContext(RequestContext $context): void
            {
            }

            public function getContext(): RequestContext
            {
                return $this->inner->getContext();
            }
        };

        $capabilities = new ResourceCapabilities(new MovieResource(), $generator);

        $capabilities->edit();
        $capabilities->edit();
        $capabilities->has('edit');
        $capabilities->template('edit');

        self::assertSame(1, $generator->generated, 'Four questions about one route cost one generation.');
    }
}
