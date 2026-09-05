<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Modufolio\Appkit\Core\AppInterface;
use Modufolio\Appkit\Security\Token\TokenStorageInterface;
use Modufolio\Panel\Http\ResourceController;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\Permissions;
use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\CapturingRenderer;
use Modufolio\Panel\Tests\Fixture\DerivedMovieResource;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;
use Modufolio\Panel\Tests\Fixture\Entity\Studio;
use Modufolio\Panel\Tests\Fixture\StaticSharedProps;
use Modufolio\Psr7\Http\ServerRequest;
use Modufolio\Panel\Contracts\PageRendererInterface;
use Modufolio\Panel\Contracts\SharedPropsInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Validator\Validation;

/**
 * The controller the package ships for every generated route: what each
 * operation answers, and how a refusal or a success reaches the caller.
 */
final class ResourceControllerTest extends DoctrineTestCase
{
    private FlashBag $flash;
    private CapturingRenderer $pages;

    private function seed(): Movie
    {
        $studio = (new Studio())->setName('Warner Bros.')->setCity('Burbank');
        $heat   = (new Movie())->setTitle('Heat')->setYear(1995)->setRating('8.3')->setStudio($studio)
            ->setCreatedAt(new \DateTimeImmutable('2026-01-01 10:00:00'));
        $jaws   = (new Movie())->setTitle('Jaws')->setYear(1975)->setRating('8.1')->setStudio($studio)
            ->setCreatedAt(new \DateTimeImmutable('2026-01-02 10:00:00'));

        $this->persist($studio, $heat, $jaws);
        $this->clear();

        return $heat;
    }

    private function controller(PanelResource $resource): ResourceController
    {
        $this->flash = new FlashBag();
        $this->pages = new CapturingRenderer($this->createStub(ResponseInterface::class));

        // The kernel hands the application over after construction; here a
        // stub answers with the package's own test doubles.
        $services = [
            SharedPropsInterface::class  => new StaticSharedProps(),
            PageRendererInterface::class => $this->pages,
            DerivedMovieResource::class  => $resource,
        ];

        $session = $this->createStub(FlashBagAwareSessionInterface::class);
        $session->method('getFlashBag')->willReturn($this->flash);

        $app = $this->createStub(AppInterface::class);
        $app->method('entityManager')->willReturn(self::em());
        $app->method('urlGenerator')->willReturn($this->urlGenerator(DerivedMovieResource::class));
        $app->method('validator')->willReturn(Validation::createValidator());
        $app->method('tokenStorage')->willReturn($this->createStub(TokenStorageInterface::class));
        $app->method('session')->willReturn($session);
        $app->method('has')->willReturnCallback(static fn (string $id): bool => isset($services[$id]));
        $app->method('get')->willReturnCallback(static fn (string $id): object => $services[$id]);

        $controller = new ResourceController();
        $controller->setSubscribedServices($app);

        return $controller;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    private function http(string $method, string $uri, array $body = [], array $headers = []): ServerRequest
    {
        return (new ServerRequest($method, $uri, $headers))->withParsedBody($body);
    }

    /** A resource with one rule refused, for the refusal paths. */
    private function refusing(string $verb): DerivedMovieResource
    {
        return new class ($verb) extends DerivedMovieResource {
            public function __construct(private readonly string $verb) {}

            public function permissions(): Permissions
            {
                return new class ($this->verb) extends Permissions {
                    public function __construct(private readonly string $verb) { parent::__construct(); }
                    public function view(?object $record, ?object $user): bool { return $this->verb !== 'view'; }
                    public function create(?object $user): bool { return $this->verb !== 'create'; }
                    public function delete(?object $record, ?object $user): bool { return $this->verb !== 'delete'; }
                };
            }
        };
    }

    public function testIndexRendersTheListing(): void
    {
        $this->seed();

        $this->controller(new DerivedMovieResource())->handle($this->http('GET', '/panel/movies'), DerivedMovieResource::class, 'index');

        self::assertSame('Resource/Index', $this->pages->component);
        self::assertSame(['Heat', 'Jaws'], array_column($this->pages->props['movies']['data'], 'title'));
    }

    public function testShowStacksTheRecordsDrawerOnTheListing(): void
    {
        $heat = $this->seed();

        $this->controller(new DerivedMovieResource())->handle(
            $this->http('GET', '/panel/movies/' . $heat->getUuid()->toString()),
            DerivedMovieResource::class,
            'show',
            $heat->getUuid()->toString(),
        );

        $frame = $this->pages->props['stack'][0];
        self::assertSame('movie', $frame['type']);
        self::assertSame('Heat', $frame['title']);
        self::assertSame('Heat', $frame['data']['title']);
        self::assertSame('/panel/movies/' . $heat->getUuid()->toString(), $frame['href']);
        self::assertStringContainsString('/panel/movies/', (string) $frame['nextRecordUrl'], 'Next/previous come from the listing\'s order.');
    }

    public function testARefusedViewIsSentBackToTheListingWithAFlash(): void
    {
        $this->seed();

        $response = $this->controller($this->refusing('view'))->handle($this->http('GET', '/panel/movies'), DerivedMovieResource::class, 'index');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/panel/movies', $response->getHeaderLine('Location'));
        self::assertSame(['You do not have permission to do that.'], $this->flash->get('error'));
    }

    public function testARefusedJsonCallerGetsA403(): void
    {
        $this->seed();

        $response = $this->controller($this->refusing('create'))->handle(
            $this->http('GET', '/panel/movies/create', headers: ['Accept' => 'application/json']),
            DerivedMovieResource::class,
            'create',
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testStoreCreatesTheRecordAndRedirectsWithASuccessFlash(): void
    {
        $this->seed();

        $response = $this->controller(new DerivedMovieResource())->handle(
            $this->http('POST', '/panel/movies', ['title' => 'Collateral', 'synopsis' => 'A cab ride.']),
            DerivedMovieResource::class,
            'store',
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/panel/movies', $response->getHeaderLine('Location'));
        self::assertSame(['Movie created.'], $this->flash->get('success'));
        self::assertInstanceOf(Movie::class, self::em()->getRepository(Movie::class)->findOneBy(['title' => 'Collateral']));
    }

    public function testAnInvalidStoreRendersTheFormWithItsErrors(): void
    {
        $this->seed();

        $this->controller(new DerivedMovieResource())->handle(
            $this->http('POST', '/panel/movies', ['title' => '']),
            DerivedMovieResource::class,
            'store',
        );

        self::assertSame('Resource/Create', $this->pages->component);
        self::assertArrayHasKey('title', (array) $this->pages->props['errors']);
        self::assertNull(self::em()->getRepository(Movie::class)->findOneBy(['title' => '']));
    }

    public function testUpdateWritesTheRecordAndReturnsToTheEditPage(): void
    {
        $heat = $this->seed();
        $uuid = $heat->getUuid()->toString();

        $response = $this->controller(new DerivedMovieResource())->handle(
            $this->http('PUT', '/panel/movies/' . $uuid, ['title' => 'Heat (1995)', 'synopsis' => null, 'released_on' => null]),
            DerivedMovieResource::class,
            'update',
            $uuid,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/panel/movies/' . $uuid . '/edit', $response->getHeaderLine('Location'));
        $this->clear();
        self::assertSame('Heat (1995)', self::em()->getRepository(Movie::class)->findOneBy(['uuid' => $uuid])?->getTitle());
    }

    /**
     * The fixture entity has no softDelete(), so a delete is the real thing:
     * the preview collects its consequences, and destroy applies that plan.
     */
    public function testDestroyAppliesThePlanThePreviewShowed(): void
    {
        $heat = $this->seed();
        $uuid = $heat->getUuid()->toString();

        $preview = $this->controller(new DerivedMovieResource())->handle($this->http('GET', '/x'), DerivedMovieResource::class, 'deletePreview', $uuid);
        $plan    = json_decode((string) $preview->getBody(), true);

        self::assertSame(200, $preview->getStatusCode());
        self::assertFalse($plan['blocked']);
        self::assertArrayNotHasKey('soft', $plan, 'No softDelete() on the entity: a real removal, with a blast radius.');
        self::assertSame('Movie: Heat', $plan['nested'][0]['label']);

        $response = $this->controller(new DerivedMovieResource())->handle($this->http('DELETE', '/x'), DerivedMovieResource::class, 'destroy', $uuid);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame(['Movie deleted.'], $this->flash->get('success'));

        $this->clear();
        self::assertNull(self::em()->getRepository(Movie::class)->findOneBy(['uuid' => $uuid]));
    }

    public function testARefusedDeleteIsJsonForThePreviewAndAFlashForTheDelete(): void
    {
        $heat = $this->seed();
        $uuid = $heat->getUuid()->toString();

        self::assertSame(403, $this->controller($this->refusing('delete'))->handle($this->http('GET', '/x'), DerivedMovieResource::class, 'deletePreview', $uuid)->getStatusCode());

        $response = $this->controller($this->refusing('delete'))->handle($this->http('DELETE', '/x'), DerivedMovieResource::class, 'destroy', $uuid);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame(['You do not have permission to do that.'], $this->flash->get('error'));
    }

    public function testAnUnknownRecordGoesBackToTheListing(): void
    {
        $this->seed();

        $response = $this->controller(new DerivedMovieResource())->handle($this->http('GET', '/x'), DerivedMovieResource::class, 'show', '00000000-0000-4000-8000-000000000000');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/panel/movies', $response->getHeaderLine('Location'));
    }

    public function testRelationEndpointsRefuseAFieldThatIsNotARelation(): void
    {
        $this->seed();

        $response = $this->controller(new DerivedMovieResource())->handle($this->http('GET', '/x'), DerivedMovieResource::class, 'relationOptions', null, 'title');

        self::assertSame(404, $response->getStatusCode());
    }

    public function testExportIsRefusedUntilTheHostOffersFormats(): void
    {
        $this->seed();

        $response = $this->controller(new DerivedMovieResource())->handle($this->http('POST', '/x', ['format' => 'csv']), DerivedMovieResource::class, 'export');

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('not configured', (string) $response->getBody());
    }

    public function testABoardMoveOnAResourceWithoutABoardIs404(): void
    {
        $heat = $this->seed();

        $response = $this->controller(new DerivedMovieResource())->handle(
            $this->http('POST', '/x', ['column' => 'done', 'view' => 'board']),
            DerivedMovieResource::class,
            'boardMove',
            $heat->getUuid()->toString(),
        );

        self::assertSame(404, $response->getStatusCode());
    }
}
