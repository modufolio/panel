<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Modufolio\Appkit\Inertia\Inertia;
use Modufolio\Appkit\Security\Token\TokenStorageInterface;
use Modufolio\Panel\Form\Form;
use Modufolio\Panel\Http\ResourceController;
use Modufolio\Panel\Resource\ContainerResourceLocator;
use Psr\Container\ContainerInterface;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\Permissions;
use Modufolio\Panel\Table\Column;
use Modufolio\Panel\Table\TableSchema;
use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\CastDrawerMovieResource;
use Modufolio\Panel\Tests\Fixture\DerivedMovieResource;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;
use Modufolio\Panel\Tests\Fixture\Entity\Studio;
use Modufolio\Psr7\Http\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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

    private function controller(PanelResource $resource, ?UrlGeneratorInterface $urls = null): ResourceController
    {
        $this->flash = new FlashBag();

        // The module wires these from the container; here they are handed
        // over directly, with the package's own test doubles.
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => $id === DerivedMovieResource::class);
        $container->method('get')->willReturn($resource);

        $controller = new ResourceController(
            entityManager: self::em(),
            urlGenerator: $urls ?? $this->urlGenerator(DerivedMovieResource::class),
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
                    public function reason(string $ability, ?object $record, ?object $user): ?string
                    {
                        return $ability === 'delete' ? 'Classics cannot be deleted' : null;
                    }
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

    /**
     * The list says where a new row goes, so a frame stacked over another
     * resource can be added to from that record's own endpoint rather than
     * from whatever page happens to be underneath it.
     */
    public function testAnAddableListCarriesItsOwnEndpoint(): void
    {
        $heat = $this->seed();
        $uuid = $heat->getUuid()->toString();

        $page = $this->page($this->controller(
            new CastDrawerMovieResource(),
            $this->urlGenerator(CastDrawerMovieResource::class),
        )->handle($this->http('GET', '/panel/movies/' . $uuid), DerivedMovieResource::class, 'show', $uuid));

        $cast = $page->props()['stack'][0]['tabs'][0]['sections'][0];

        self::assertTrue($cast['addable']);
        self::assertSame('cast', $cast['addTarget']);
        self::assertSame('/panel/movies/' . $uuid . '/relations/cast', $cast['addUrl']);
    }

    /** Adding a row is editing the record: refused, the list is not addable. */
    public function testAnAddableListLosesItsEndpointWhenTheRecordCannotBeEdited(): void
    {
        $heat = $this->seed();
        $uuid = $heat->getUuid()->toString();

        $resource = new class extends CastDrawerMovieResource {
            public function permissions(): Permissions
            {
                return new class extends Permissions {
                    public function edit(?object $record, ?object $user): bool { return false; }
                };
            }
        };

        $page = $this->page($this->controller(
            $resource,
            $this->urlGenerator(CastDrawerMovieResource::class),
        )->handle($this->http('GET', '/panel/movies/' . $uuid), DerivedMovieResource::class, 'show', $uuid));

        $cast = $page->props()['stack'][0]['tabs'][0]['sections'][0];

        self::assertFalse($cast['addable']);
        self::assertNull($cast['addUrl']);
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
     * One cell, written where it is read. The redirect carries the list state
     * the request arrived with, because that URL is what Inertia reloads.
     */
    public function testPatchWritesOneFieldAndReturnsToTheListItWasEditedFrom(): void
    {
        $heat = $this->seed();
        $uuid = $heat->getUuid()->toString();

        $response = $this->response($this->controller($this->editableTitle())->handle(
            $this->http('PATCH', '/panel/movies/' . $uuid . '?page=3&sort=-year', ['title' => 'Heat (1995)']),
            DerivedMovieResource::class,
            'patch',
            $uuid,
        ));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/panel/movies?page=3&sort=-year', $response->getHeaderLine('Location'));
        self::assertSame(['Movie updated.'], $this->flash->get('success'));

        $this->clear();
        $movie = self::em()->getRepository(Movie::class)->findOneBy(['uuid' => $uuid]);
        self::assertSame('Heat (1995)', $movie?->getTitle());
        // The rest of the record is untouched: a partial write considers only
        // the fields that arrived, so nothing else is defaulted to empty.
        self::assertSame(1995, $movie->getYear());
    }

    /**
     * A column may display one field under another name; the body is keyed the
     * way the client knows the column, and the server translates.
     */
    public function testPatchFollowsTheColumnsValueMappingToTheFieldItWrites(): void
    {
        $heat = $this->seed();
        $uuid = $heat->getUuid()->toString();

        $resource = new class extends DerivedMovieResource {
            public function table(): TableSchema
            {
                return TableSchema::make()->columns([
                    Column::make('name')->value('title')->editable(),
                ]);
            }
        };

        $this->response($this->controller($resource)->handle(
            $this->http('PATCH', '/panel/movies/' . $uuid, ['name' => 'Heat, remastered']),
            DerivedMovieResource::class,
            'patch',
            $uuid,
        ));

        $this->clear();
        self::assertSame('Heat, remastered', self::em()->getRepository(Movie::class)->findOneBy(['uuid' => $uuid])?->getTitle());
    }

    /**
     * The endpoint's surface is what the listing draws a control for, not
     * whatever the form happens to contain.
     */
    public function testPatchRefusesAFieldNoColumnDeclaresEditable(): void
    {
        $heat = $this->seed();
        $uuid = $heat->getUuid()->toString();

        $this->response($this->controller($this->editableTitle())->handle(
            $this->http('PATCH', '/panel/movies/' . $uuid, ['synopsis' => 'Smuggled in beside the form.']),
            DerivedMovieResource::class,
            'patch',
            $uuid,
        ));

        self::assertSame(
            ['Nothing in that request can be edited from the list. Editable columns: title.'],
            $this->flash->get('error'),
        );

        $this->clear();
        self::assertNull(self::em()->getRepository(Movie::class)->findOneBy(['uuid' => $uuid])?->getSynopsis());
    }

    /** The same permission the full form asks, on the same record. */
    public function testPatchIsRefusedWithoutEditPermission(): void
    {
        $heat = $this->seed();
        $uuid = $heat->getUuid()->toString();

        $resource = new class extends DerivedMovieResource {
            public function table(): TableSchema
            {
                return TableSchema::make()->columns([Column::make('title')->editable()]);
            }

            public function permissions(): Permissions
            {
                return new class extends Permissions {
                    public function edit(?object $record, ?object $user): bool { return false; }
                };
            }
        };

        $this->response($this->controller($resource)->handle(
            $this->http('PATCH', '/panel/movies/' . $uuid, ['title' => 'Nope']),
            DerivedMovieResource::class,
            'patch',
            $uuid,
        ));

        self::assertSame(['You do not have permission to do that.'], $this->flash->get('error'));

        $this->clear();
        self::assertSame('Heat', self::em()->getRepository(Movie::class)->findOneBy(['uuid' => $uuid])?->getTitle());
    }

    /**
     * The cell has closed by the time the answer arrives, so a rejected value
     * has no input left to pin its message to — it travels as a flash.
     */
    public function testAnInvalidPatchExplainsItselfAndWritesNothing(): void
    {
        $heat = $this->seed();
        $uuid = $heat->getUuid()->toString();

        $this->response($this->controller($this->editableTitle())->handle(
            $this->http('PATCH', '/panel/movies/' . $uuid, ['title' => '']),
            DerivedMovieResource::class,
            'patch',
            $uuid,
        ));

        self::assertSame([], $this->flash->get('success'));
        self::assertNotSame([], $this->flash->get('error'));

        $this->clear();
        self::assertSame('Heat', self::em()->getRepository(Movie::class)->findOneBy(['uuid' => $uuid])?->getTitle());
    }

    /**
     * A control whose changes would evaporate is worse than one never
     * offered: the write goes through the form, so the column has to name a
     * field the form declares.
     */
    public function testAnEditableColumnTheFormDoesNotDeclareIsADeclarationError(): void
    {
        $heat = $this->seed();
        $uuid = $heat->getUuid()->toString();

        $resource = new class extends DerivedMovieResource {
            public function table(): TableSchema
            {
                return TableSchema::make()->columns([Column::make('year')->editable()]);
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('column "year" is editable but writes "year", which the form does not declare');

        $this->controller($resource)->handle(
            $this->http('PATCH', '/panel/movies/' . $uuid, ['year' => 1996]),
            DerivedMovieResource::class,
            'patch',
            $uuid,
        );
    }

    /**
     * A field this user may not write is refused outright, rather than being
     * dropped the way a whole form drops it: a form with one frozen field
     * still has the rest to save, a single cell has nothing, and reporting
     * success for a write that did not happen is the worse answer.
     */
    public function testPatchRefusesAFieldThisUserMayNotWrite(): void
    {
        $heat = $this->seed();
        $uuid = $heat->getUuid()->toString();

        $resource = new class extends DerivedMovieResource {
            public function table(): TableSchema
            {
                return TableSchema::make()->columns([Column::make('title')->editable()]);
            }

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

        $this->response($this->controller($resource)->handle(
            $this->http('PATCH', '/panel/movies/' . $uuid, ['title' => 'Frozen']),
            DerivedMovieResource::class,
            'patch',
            $uuid,
        ));

        self::assertSame(['You may not change title.'], $this->flash->get('error'));
        self::assertSame([], $this->flash->get('success'));

        $this->clear();
        self::assertSame('Heat', self::em()->getRepository(Movie::class)->findOneBy(['uuid' => $uuid])?->getTitle());
    }

    /** A resource whose title column is editable in place. */
    private function editableTitle(): DerivedMovieResource
    {
        return new class extends DerivedMovieResource {
            public function table(): TableSchema
            {
                return TableSchema::make()->columns([
                    Column::make('title')->editable(),
                    Column::make('year'),
                ]);
            }
        };
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

    public function testABulkDeleteReportsWhatItSkippedAndWhy(): void
    {
        $heat = $this->seed();
        $jaws = self::em()->getRepository(Movie::class)->findOneBy(['title' => 'Jaws']);
        self::assertNotNull($jaws);

        // Everything allowed, one id that no longer exists: "1 of 2", one reason.
        $this->response($this->controller(new DerivedMovieResource())->handle(
            $this->http('POST', '/panel/movies/bulk-destroy', ['ids' => [$heat->getUuid()->toString(), 'no-such-uuid']]),
            DerivedMovieResource::class,
            'bulkDestroy',
        ));
        self::assertSame(['1 of 2 movie(s) deleted.'], $this->flash->get('success'));
        self::assertSame(['1 skipped: no longer exists.'], $this->flash->get('warning'));

        // Every record refused with a reason: nothing deleted, the reason named once with its count.
        $this->response($this->controller($this->refusing('delete'))->handle(
            $this->http('POST', '/panel/movies/bulk-destroy', ['ids' => [$jaws->getUuid()->toString(), $heat->getUuid()->toString()]]),
            DerivedMovieResource::class,
            'bulkDestroy',
        ));
        self::assertSame([], $this->flash->get('success'));
        self::assertSame(
            ['0 of 2 movie(s) deleted.', '1 skipped: Classics cannot be deleted.', '1 skipped: no longer exists.'],
            $this->flash->get('warning'),
            'Heat went to the trash in the first call and is out of the locator\'s sight; Jaws is refused with its reason.',
        );
    }

    public function testAFrameCarriesTheReasonForARefusal(): void
    {
        $heat = $this->seed();

        $page = $this->page($this->controller($this->refusing('delete'))->handle(
            $this->http('GET', '/panel/movies/' . $heat->getUuid()->toString()),
            DerivedMovieResource::class,
            'show',
            $heat->getUuid()->toString(),
        ));

        $frame = $page->props()['stack'][0];
        self::assertSame(['edit' => true, 'delete' => false], $frame['can']);
        self::assertSame(['delete' => 'Classics cannot be deleted'], $frame['why']);
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

    /** DerivedMovieResource's own form does not name the studio; this one does. */
    private function movieResourceWithStudioField(): DerivedMovieResource
    {
        return new class extends DerivedMovieResource {
            public function form(): Form
            {
                return Form::make()->fields(['title', 'studio_id']);
            }
        };
    }

    /**
     * The endpoint a `pickable` drawer field's picker posts to — the same
     * `{key}_relation_store` route an addable list already uses, here on a
     * to-one field (a studio, standing in for a cover) rather than a to-many
     * one. `SubmissionHandler::append()` sets it through the entity's setter.
     */
    public function testRelationStoreSetsAToOneFieldFromThePickersSelection(): void
    {
        $heat   = $this->seed();
        $amblin = new Studio();
        $amblin->setName('Amblin');
        $this->persist($amblin);
        $this->clear();
        $heat = self::em()->find(Movie::class, $heat->getId());
        self::assertInstanceOf(Movie::class, $heat);

        $response = $this->response($this->controller($this->movieResourceWithStudioField())->handle(
            $this->http('POST', '/x', ['studio_id' => $amblin->getUuid()->toString()]),
            DerivedMovieResource::class,
            'relationStore',
            $heat->getUuid()->toString(),
            'studio_id',
        ));

        self::assertSame(302, $response->getStatusCode());

        $this->clear();
        $saved = self::em()->find(Movie::class, $heat->getId());
        self::assertInstanceOf(Movie::class, $saved);
        self::assertSame('Amblin', $saved->getStudio()?->getName());
    }

    /** Adding a row (or setting a field) is editing the record it hangs off. */
    public function testRelationStoreIsForbiddenWithoutEditPermission(): void
    {
        $heat = $this->seed();

        $resource = new class extends DerivedMovieResource {
            public function form(): Form
            {
                return Form::make()->fields(['title', 'studio_id']);
            }

            public function permissions(): Permissions
            {
                return new class extends Permissions {
                    public function edit(?object $record, ?object $user): bool { return false; }
                };
            }
        };

        $response = $this->response($this->controller($resource)->handle(
            $this->http('POST', '/x', ['studio_id' => 'irrelevant']),
            DerivedMovieResource::class,
            'relationStore',
            $heat->getUuid()->toString(),
            'studio_id',
        ));

        self::assertSame(403, $response->getStatusCode());
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
