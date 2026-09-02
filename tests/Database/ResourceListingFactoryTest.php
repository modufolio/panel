<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Modufolio\Appkit\Security\Token\TokenInterface;
use Modufolio\Appkit\Security\Token\TokenStorageInterface;
use Modufolio\Appkit\Security\User\UserInterface;
use Modufolio\Panel\Contracts\PageRendererInterface;
use Modufolio\Panel\Contracts\SharedPropsInterface;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\ResourceListingFactory;
use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\CapturingRenderer;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;
use Modufolio\Panel\Tests\Fixture\Entity\Studio;
use Modufolio\Panel\Tests\Fixture\MovieResource;
use Modufolio\Panel\Tests\Fixture\StaticSharedProps;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A resource that remembers which user its edit permission was asked about,
 * and allows editing only to someone signed in. Keyed `movies` still, so the
 * routes generated for MovieResource apply to it.
 */
final class UserRecordingMovieResource extends MovieResource
{
    public bool $asked = false;

    public ?object $askedFor = null;

    public function canEdit(?object $record = null, ?object $user = null): bool
    {
        $this->asked    = true;
        $this->askedFor = $user;

        return $user !== null;
    }
}

/**
 * The factory is the one place a resource class becomes a request-bound
 * listing, for the attribute resolver and the generic controller alike.
 *
 * Two things are worth pinning. The container semantics: a registered
 * resource wins, an unregistered one with a no-argument constructor is built
 * on the spot, and anything else is refused with a message that says what to
 * do. And the user: it is read from the token storage here and handed to the
 * resource's permission hooks, which never fetch it themselves.
 */
final class ResourceListingFactoryTest extends DoctrineTestCase
{
    private CapturingRenderer $capturingRenderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capturingRenderer = new CapturingRenderer($this->createStub(ResponseInterface::class));
    }

    /**
     * A PSR-11 container over a fixed map, with the two host contracts every
     * listing needs already in it.
     *
     * @param array<string, object> $services by id
     */
    private function container(array $services = []): ContainerInterface
    {
        $services += [
            SharedPropsInterface::class  => new StaticSharedProps(),
            PageRendererInterface::class => $this->capturingRenderer,
        ];

        return new class($services) implements ContainerInterface {
            /** @param array<string, object> $services */
            public function __construct(private readonly array $services)
            {
            }

            public function get(string $id): object
            {
                if (!isset($this->services[$id])) {
                    throw new class(sprintf('No service "%s".', $id)) extends \RuntimeException implements NotFoundExceptionInterface {
                    };
                }

                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
    }

    private function tokenStorage(?object $user): TokenStorageInterface
    {
        $storage = $this->createStub(TokenStorageInterface::class);

        if ($user === null) {
            $storage->method('getToken')->willReturn(null);

            return $storage;
        }

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $storage->method('getToken')->willReturn($token);

        return $storage;
    }

    /** @param array<string, object> $services */
    private function factory(array $services = [], ?object $user = null): ResourceListingFactory
    {
        return new ResourceListingFactory(
            $this->container($services),
            self::em(),
            $this->urlGenerator(MovieResource::class),
            $this->tokenStorage($user),
        );
    }

    private function recordingResource(): UserRecordingMovieResource
    {
        return new UserRecordingMovieResource();
    }

    // ── make() ───────────────────────────────────────────────────────────────

    /**
     * The whole point of the factory: what it hands back renders the
     * resource's page through the host's renderer, over real rows, with the
     * shared props riding along.
     */
    public function testMakeBuildsAListingThatRendersTheResourcesProps(): void
    {
        $studio = (new Studio())->setName('Warner Bros.');
        $this->persist($studio, (new Movie())->setTitle('Heat')->setYear(1995)->setStudio($studio));
        $this->clear();

        $listing = $this->factory()->make(MovieResource::class, $this->request());
        $listing->render();

        self::assertSame('Resource/Index', $this->capturingRenderer->component);

        $props = $this->capturingRenderer->props;
        self::assertSame('movies', $props['resource']['key']);
        self::assertSame(1, $props['movies']['meta']['total']);
        self::assertSame(['Heat'], array_column($props['movies']['data'], 'title'));
        self::assertArrayHasKey('auth', $props, 'Shared props come from the container-registered contract.');
    }

    public function testMakeUsesTheRendererAndSharedPropsRegisteredInTheContainer(): void
    {
        $renderer = new CapturingRenderer($this->createStub(ResponseInterface::class));
        $shared   = new StaticSharedProps(['auth' => ['user' => 'leila'], 'flash' => ['Saved.']]);

        $factory = $this->factory([
            PageRendererInterface::class => $renderer,
            SharedPropsInterface::class  => $shared,
        ]);
        $factory->make(MovieResource::class, $this->request())->render();

        self::assertNull($this->capturingRenderer->component, 'The default renderer was never asked.');
        self::assertSame('Resource/Index', $renderer->component);
        self::assertSame(['user' => 'leila'], $renderer->props['auth']);
        self::assertSame(['Saved.'], $renderer->props['flash']);
    }

    // ── resource() ───────────────────────────────────────────────────────────

    /** The common case is zero-config: no registration, just the class. */
    public function testAResourceWithNoConstructorArgumentsIsInstantiatedWithoutRegistration(): void
    {
        $resource = $this->factory()->resource(MovieResource::class);

        self::assertInstanceOf(MovieResource::class, $resource);
        self::assertSame('movies', $resource->key());
    }

    public function testEachUnregisteredCallBuildsAFreshInstance(): void
    {
        $factory = $this->factory();

        self::assertNotSame(
            $factory->resource(MovieResource::class),
            $factory->resource(MovieResource::class),
        );
    }

    /**
     * Registration wins, so a resource wired with collaborators in
     * config/interfaces.php is the instance the container built — not a bare
     * `new` that would have skipped them.
     */
    public function testARegisteredResourceComesFromTheContainer(): void
    {
        $registered = new MovieResource();

        $resource = $this->factory([MovieResource::class => $registered])->resource(MovieResource::class);

        self::assertSame($registered, $resource);
    }

    /**
     * A resource that needs arguments cannot be built blind, and the error
     * says where the registration belongs rather than leaving a constructor
     * type error to explain itself.
     */
    public function testAResourceWithRequiredConstructorArgumentsMustBeRegistered(): void
    {
        $needy = new class('images') extends MovieResource {
            public function __construct(public readonly string $service)
            {
            }
        };
        $class = $needy::class;

        try {
            $this->factory()->resource($class);
            self::fail('An unregistered resource with required constructor arguments must be refused.');
        } catch (\LogicException $e) {
            self::assertStringContainsString($class, $e->getMessage());
            self::assertStringContainsString(
                'has required constructor arguments, so it must be registered in config/interfaces.php.',
                $e->getMessage(),
            );
        }
    }

    /** Optional arguments are fine: the class can still be built with none. */
    public function testOptionalConstructorArgumentsDoNotRequireRegistration(): void
    {
        $lenient = new class() extends MovieResource {
            public function __construct(public readonly string $service = 'default')
            {
            }
        };

        $resource = $this->factory()->resource($lenient::class);

        self::assertInstanceOf($lenient::class, $resource);
    }

    /**
     * Whatever the container hands back under a resource id has to be a
     * resource; a misconfigured binding is refused here rather than failing
     * deep inside the listing.
     */
    public function testAContainerEntryThatIsNotAPanelResourceIsRefused(): void
    {
        $factory = $this->factory([MovieResource::class => new \stdClass()]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('Expected a %s, got stdClass.', PanelResource::class));

        $factory->resource(MovieResource::class);
    }

    // ── The current user ─────────────────────────────────────────────────────

    /**
     * The user in the token is the one the permission hooks receive. The
     * recording resource allows editing only when someone is signed in, so
     * `resource.canEdit` in the rendered props is the observable end of the
     * chain, and the recorded argument is the identity check.
     */
    public function testTheTokensUserReachesTheResourcesPermissionHooks(): void
    {
        $user     = $this->createStub(UserInterface::class);
        $resource = $this->recordingResource();

        $factory = $this->factory([$resource::class => $resource], $user);
        $factory->make($resource::class, $this->request())->render();

        self::assertTrue($resource->asked);
        self::assertSame($user, $resource->askedFor);
        self::assertTrue($this->capturingRenderer->props['resource']['canEdit']);
    }

    public function testWithoutATokenTheHooksReceiveNoUser(): void
    {
        $resource = $this->recordingResource();

        $factory = $this->factory([$resource::class => $resource]);
        $factory->make($resource::class, $this->request())->render();

        self::assertTrue($resource->asked);
        self::assertNull($resource->askedFor);
        self::assertFalse($this->capturingRenderer->props['resource']['canEdit']);
    }

    /** A token without a user — anonymous, or one holding a bare identifier — is no user either. */
    public function testATokenWithoutAUserObjectYieldsNoUser(): void
    {
        $resource = $this->recordingResource();
        $storage  = $this->createStub(TokenStorageInterface::class);
        $token    = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);
        $storage->method('getToken')->willReturn($token);

        $factory = new ResourceListingFactory(
            $this->container([$resource::class => $resource]),
            self::em(),
            $this->urlGenerator(MovieResource::class),
            $storage,
        );
        $factory->make($resource::class, $this->request())->render();

        self::assertNull($resource->askedFor);
        self::assertFalse($this->capturingRenderer->props['resource']['canEdit']);
    }
}
