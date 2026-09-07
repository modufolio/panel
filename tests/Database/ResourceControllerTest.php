<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Modufolio\Appkit\Inertia\Inertia;
use Modufolio\Appkit\Security\Token\TokenStorageInterface;
use Modufolio\Panel\Http\ResourceController;
use Modufolio\Panel\Resource\ContainerResourceLocator;
use Psr\Container\ContainerInterface;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\Permissions;
use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\DerivedMovieResource;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;
use Modufolio\Panel\Tests\Fixture\Entity\Studio;
use Modufolio\Psr7\Http\ServerRequest;
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

    /** A finished response: a redirect, a JSON reply, a refusal. */
    private function response(mixed $result): ResponseInterface
    {
        self::assertInstanceOf(ResponseInterface::class, $result, 'The operation answers with a response, not a page.');

        return $result;
    }

    /** The page the controller handed back for the kernel to finish. */
    private function page(mixed $result): Inertia
    {
        self::assertInstanceOf(Inertia::class, $result, 'The operation answers with a page, not a response.');

        return $result;
    }

    private function controller(PanelResource $resource): ResourceController
    {
        $this->flash = new FlashBag();

        // The module wires these from the container; here they are handed
        // over directly, with the package's own test doubles.
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => $id === DerivedMovieResource::class);
        $container->method('get')->willReturn($resource);

        $controller = new ResourceController(
            entityManager: self::em(),
            urlGenerator: $this->urlGenerator(DerivedMovieResource::class),
            validator: Validation::createValidator(),
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            flashBag: $this->flash,
            resources: new ContainerResourceLocator($container),
        );

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

        $page = $this->page($this->controller(new DerivedMovieResource())->handle($this->http('GET', '/panel/movies'), DerivedMovieResource::class, 'index'));

        self::assertSame('Resource/Index', $page->component());
        self::assertSame(['Heat', 'Jaws'], array_column($page->props()['movies']['data'], 'title'));
    }

    public function testShowStacksTheRecordsDrawerOnTheListing(): void
    {
        $heat = $this->seed();

        $page = $this->page($this->controller(new DerivedMovieResource())->handle(
            $this->http('GET', '/panel/movies/' . $heat->getUuid()->toString()),
            DerivedMovieResource::class,
            'show',
            $heat->getUuid()->toString(),
        ));

        $frame = $page->props()['stack'][0];
        self::assertSame('movie', $frame['type']);
        self::assertSame('Heat', $frame['title']);
        self::assertSame('Heat', $frame['data']['title']);
        self::assertSame('/panel/movies/' . $heat->getUuid()->toString(), $frame['href']);
        self::assertStringContainsString('/panel/movies/', (string) $frame['nextRecordUrl'], 'Next/previous come from the listing\'s order.');
    }

    public function testARefusedViewIsSentBackToTheListingWithAFlash(): void
    {
        $this->seed();

        $response = $this->response($this->controller($this->refusing('view'))->handle($this->http('GET', '/panel/movies'), DerivedMovieResource::class, 'index'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/panel/movies', $response->getHeaderLine('Location'));
        self::assertSame(['You do not have permission to do that.'], $this->flash->get('error'));
    }

    public function testARefusedJsonCallerGetsA403(): void
    {
        $this->seed();

        $response = $this->response($this->controller($this->refusing('create'))->handle(
            $this->http('GET', '/panel/movies/create', headers: ['Accept' => 'application/json']),
            DerivedMovieResource::class,
            'create',
        ));

        self::assertSame(403, $response->getStatusCode());

        // One envelope for every JSON reply: `message` for the human, and the
        // flash the refusal wrote rides along as `_toasts` instead of waiting
        // for a page that a JSON caller never loads.
        $body = json_decode((string) $response->getBody(), true);
        self::assertSame('Forbidden.', $body['message']);
        self::assertSame([['type' => 'error', 'message' => 'You do not have permission to do that.']], $body['_toasts']);
        self::assertSame([], $this->flash->get('error'), 'Drained: the same message does not surface again on the next page.');
    }

    public function testStoreCreatesTheRecordAndRedirectsWithASuccessFlash(): void
    {
        $this->seed();

        $response = $this->response($this->controller(new DerivedMovieResource())->handle(
            $this->http('POST', '/panel/movies', ['title' => 'Collateral', 'synopsis' => 'A cab ride.']),
            DerivedMovieResource::class,
            'store',
        ));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/panel/movies', $response->getHeaderLine('Location'));
        self::assertSame(['Movie created.'], $this->flash->get('success'));
        self::assertInstanceOf(Movie::class, self::em()->getRepository(Movie::class)->findOneBy(['title' => 'Collateral']));
    }

    public function testAnInvalidStoreRendersTheFormWithItsErrors(): void
    {
        $this->seed();

        $page = $this->page($this->controller(new DerivedMovieResource())->handle(
            $this->http('POST', '/panel/movies', ['title' => '']),
            DerivedMovieResource::class,
            'store',
        ));

        self::assertSame('Resource/Create', $page->component());
        self::assertArrayHasKey('title', (array) $page->props()['errors']);
        self::assertNull(self::em()->getRepository(Movie::class)->findOneBy(['title' => '']));
    }

    public function testUpdateWritesTheRecordAndReturnsToTheEditPage(): void
    {
        $heat = $this->seed();
        $uuid = $heat->getUuid()->toString();

        $response = $this->response($this->controller(new DerivedMovieResource())->handle(
            $this->http('PUT', '/panel/movies/' . $uuid, ['title' => 'Heat (1995)', 'synopsis' => null, 'released_on' => null]),
            DerivedMovieResource::class,
            'update',
            $uuid,
        ));

        self::assertSame(303, $response->getStatusCode());
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

        $preview = $this->response($this->controller(new DerivedMovieResource())->handle($this->http('GET', '/x'), DerivedMovieResource::class, 'deletePreview', $uuid));
        $plan    = json_decode((string) $preview->getBody(), true);

        self::assertSame(200, $preview->getStatusCode());
        self::assertFalse($plan['blocked']);
        self::assertArrayNotHasKey('soft', $plan, 'No softDelete() on the entity: a real removal, with a blast radius.');
        self::assertSame('Movie: Heat', $plan['nested'][0]['label']);

        $response = $this->response($this->controller(new DerivedMovieResource())->handle($this->http('DELETE', '/x'), DerivedMovieResource::class, 'destroy', $uuid));
        self::assertSame(303, $response->getStatusCode());
        self::assertSame(['Movie deleted.'], $this->flash->get('success'));

        $this->clear();
        self::assertNull(self::em()->getRepository(Movie::class)->findOneBy(['uuid' => $uuid]));
    }

    public function testARefusedDeleteIsJsonForThePreviewAndAFlashForTheDelete(): void
    {
        $heat = $this->seed();
        $uuid = $heat->getUuid()->toString();

        self::assertSame(403, $this->response($this->controller($this->refusing('delete'))->handle($this->http('GET', '/x'), DerivedMovieResource::class, 'deletePreview', $uuid))->getStatusCode());

        $response = $this->response($this->controller($this->refusing('delete'))->handle($this->http('DELETE', '/x'), DerivedMovieResource::class, 'destroy', $uuid));
        self::assertSame(303, $response->getStatusCode());
        self::assertSame(['You do not have permission to do that.'], $this->flash->get('error'));
    }

    public function testAnUnknownRecordGoesBackToTheListing(): void
    {
        $this->seed();

        $response = $this->response($this->controller(new DerivedMovieResource())->handle($this->http('GET', '/x'), DerivedMovieResource::class, 'show', '00000000-0000-4000-8000-000000000000'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/panel/movies', $response->getHeaderLine('Location'));
    }

    public function testRelationEndpointsRefuseAFieldThatIsNotARelation(): void
    {
        $this->seed();

        $response = $this->response($this->controller(new DerivedMovieResource())->handle($this->http('GET', '/x'), DerivedMovieResource::class, 'relationOptions', null, 'title'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testExportIsRefusedUntilTheHostOffersFormats(): void
    {
        $this->seed();

        $response = $this->response($this->controller(new DerivedMovieResource())->handle($this->http('POST', '/x', ['format' => 'csv']), DerivedMovieResource::class, 'export'));

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('not configured', (string) $response->getBody());
    }

    public function testABoardMoveOnAResourceWithoutABoardIs404(): void
    {
        $heat = $this->seed();

        $response = $this->response($this->controller(new DerivedMovieResource())->handle(
            $this->http('POST', '/x', ['column' => 'done', 'view' => 'board']),
            DerivedMovieResource::class,
            'boardMove',
            $heat->getUuid()->toString(),
        ));

        self::assertSame(404, $response->getStatusCode());
    }
}
