<?php

declare(strict_types=1);

namespace Modufolio\Panel\Http;

use Doctrine\ORM\EntityManagerInterface;
use Modufolio\Appkit\Core\AppAwareInterface;
use Modufolio\Appkit\Core\AppInterface;
use Modufolio\Appkit\Security\Token\TokenStorageInterface;
use Modufolio\Appkit\Security\User\UserInterface;
use Modufolio\Panel\Contracts\ExportAdapterProviderInterface;
use Modufolio\Panel\Contracts\PageRendererInterface;
use Modufolio\Panel\Contracts\SharedPropsInterface;
use Modufolio\Panel\Delete\Collector;
use Modufolio\Panel\Delete\PlanExecutor;
use Modufolio\Panel\Form\FormPresenter;
use Modufolio\Panel\Form\FormResolver;
use Modufolio\Panel\Form\SubmissionHandler;
use Modufolio\Panel\Resource\BoardMover;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\RecordLocator;
use Modufolio\Panel\Resource\RelationOptionResolver;
use Modufolio\Panel\Resource\ResourceListing;
use Modufolio\Psr7\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Serves every route {@see \Modufolio\Panel\Routing\PanelResourceRouteLoader}
 * generates — the controller a host used to write.
 *
 * The resource class and the operation arrive as route defaults rather than
 * being baked into a method signature, which is what lets one controller
 * stand in for the per-resource controllers a resource would otherwise need.
 *
 * What is here is HTTP: who is asking, which response a refusal or a success
 * becomes, where a redirect goes. Everything a resource's declaration decides
 * — the form's fields for this viewer, the submission's coercion, guards,
 * validation and mapping, the consequences of a delete — is the package's
 * already, in {@see FormPresenter}, {@see SubmissionHandler} and
 * {@see PlanExecutor}.
 *
 * An {@see AppAwareInterface} controller, the appkit way: the kernel hands the
 * application over right after construction and the controller pulls exactly
 * the services it needs, so a host registers nothing to use it. What only the
 * host knows — how a page is rendered, which props every page carries, which
 * download formats it offers, which entity is its media library — it answers
 * through the container: {@see PageRendererInterface} and
 * {@see SharedPropsInterface} are required, {@see ExportAdapterProviderInterface}
 * and a {@see FormResolver} are read when registered.
 */
final class ResourceController implements AppAwareInterface
{
    private ?AppInterface $app = null;
    private EntityManagerInterface $entityManager;
    private UrlGeneratorInterface $urlGenerator;
    private SharedPropsInterface $sharedProps;
    private PageRendererInterface $renderer;
    private ValidatorInterface $validator;
    private TokenStorageInterface $tokenStorage;
    private FlashBagInterface $flashBag;
    private ?ExportAdapterProviderInterface $exports = null;
    private ?FormResolver $forms = null;
    private ?FormPresenter $presenter = null;
    private ?SubmissionHandler $submissions = null;
    private ?PlanExecutor $executor = null;
    private ?RecordLocator $locator = null;
    private ?RelationOptionResolver $relations = null;

    public function setSubscribedServices(AppInterface $app): void
    {
        $this->app           = $app;
        $this->entityManager = $app->entityManager();
        $this->urlGenerator  = $app->urlGenerator();
        $this->validator     = $app->validator();
        $this->tokenStorage  = $app->tokenStorage();
        $this->flashBag      = $app->session()->getFlashBag();
        $this->sharedProps   = self::service($app, SharedPropsInterface::class);
        $this->renderer      = self::service($app, PageRendererInterface::class);

        // Optional: a host without download formats gets a 422 from the export
        // route; one without a media library gets a plain form resolver.
        $this->exports = $app->has(ExportAdapterProviderInterface::class)
            ? self::service($app, ExportAdapterProviderInterface::class)
            : null;
        $this->forms = $app->has(FormResolver::class)
            ? self::service($app, FormResolver::class)
            : null;
    }

    /**
     * @template T of object
     * @param  class-string<T> $id
     * @return T
     */
    private static function service(AppInterface $app, string $id): object
    {
        $service = $app->get($id);

        if (!$service instanceof $id) {
            throw new \LogicException(sprintf('Service "%s" is registered as %s.', $id, get_debug_type($service)));
        }

        return $service;
    }

    /** @param class-string<PanelResource> $resourceClass */
    public function handle(
        ServerRequestInterface $request,
        string $resourceClass,
        string $operation,
        ?string $uuid = null,
        ?string $field = null,
    ): ResponseInterface {
        $resource = $this->resource($resourceClass);

        return match ($operation) {
            'index'           => $this->index($request, $resource),
            'show'            => $this->show($request, $resource, $uuid),
            'export'          => $this->export($request, $resource),
            'create'          => $this->create($request, $resource),
            'store'           => $this->store($request, $resource),
            'edit'            => $this->edit($request, $resource, $uuid),
            'update'          => $this->update($request, $resource, $uuid),
            'destroy'         => $this->destroy($resource, $uuid),
            'bulkDestroy'     => $this->bulkDestroy($request, $resource),
            'deletePreview'   => $this->deletePreview($resource, $uuid),
            'relationOptions' => $this->relationOptions($request, $resource, $field),
            'relationCreate'  => $this->relationCreate($request, $resource, $field),
            'relationStore'   => $this->relationStore($request, $resource, $uuid, $field),
            'boardMove'       => $this->boardMove($request, $resource, $uuid),
            default           => throw new \LogicException(sprintf('Unknown panel resource operation "%s".', $operation)),
        };
    }

    // ── Reading ──────────────────────────────────────────────────────────────

    private function index(ServerRequestInterface $request, PanelResource $resource): ResponseInterface
    {
        if (!$resource->permissions()->view(null, $this->user())) {
            return $this->deny($request, $resource);
        }

        // A singleton's listing IS its one record: skip the table and open it
        // directly. With no record yet, fall through to the empty listing so
        // its empty state can offer creation.
        if ($resource->singleton()) {
            $record = $this->entityManager->getRepository($resource->entityClass())->findOneBy([]);

            if ($record !== null) {
                return Response::redirect($this->recordUrl($resource, $record));
            }
        }

        return $this->listing($request, $resource)->render();
    }

    /**
     * The listing with the record's drawer stacked on top: the frame's type,
     * title and payload come from the resource, the tabs from its drawer,
     * and next/previous from the listing's own order.
     */
    private function show(ServerRequestInterface $request, PanelResource $resource, ?string $uuid): ResponseInterface
    {
        $entity = $this->find($resource, $uuid);

        if ($entity === null) {
            return Response::redirect($this->indexUrl($resource));
        }

        if (!$resource->permissions()->view($entity, $this->user())) {
            return $this->deny($request, $resource);
        }

        $listing    = $this->listing($request, $resource);
        $navigation = $listing->navigationUrls($entity);
        $record     = $resource->presentOne($entity);

        $frame = [
            'type'              => $resource->drawerType(),
            'data'              => $record,
            'title'             => $resource->drawerTitle($entity),
            'description'       => '',
            'width'             => 'md',
            'href'              => $this->recordUrl($resource, $entity),
            'nextRecordUrl'     => $navigation['next'],
            'previousRecordUrl' => $navigation['previous'],
            // Badges count the rows the record already carries, so declaring
            // a tab costs no query. The resolved form comes along so an
            // addable list can carry the form its add action opens.
            'tabs'              => $resource->drawerTabsFor($record, $this->presenter()->resolvedFields($resource)),
            'presentation'      => 'drawer',
        ];

        return $listing->withDrawer([$frame])->render();
    }

    /**
     * Download the current result set, in the format the request names.
     *
     * Gated on {@see \Modufolio\Panel\Resource\Permissions::export()}: being
     * allowed to read the list and being allowed to download it are the same
     * permission unless the resource says otherwise. The rows are exactly what
     * the table shows, so the file cannot say what the screen does not.
     */
    private function export(ServerRequestInterface $request, PanelResource $resource): ResponseInterface
    {
        if (!$resource->permissions()->export($this->user())) {
            return $this->deny($request, $resource);
        }

        if ($this->exports === null) {
            return Response::json(['message' => 'Export is not configured for this application.'], 422);
        }

        $body = $this->body($request);

        try {
            $adapter = $this->exports->get((string) ($body['format'] ?? 'csv'));
        } catch (\InvalidArgumentException $e) {
            return Response::json(['message' => $e->getMessage()], 422);
        }

        $columns = $this->exportColumns($resource, $body['columns'] ?? null);

        if ($columns === []) {
            return Response::json(['message' => 'This resource declares no exportable columns.'], 422);
        }

        // An empty selection means "everything I am looking at", not
        // "everything in the table".
        $uuids   = array_values(array_filter(array_map('strval', (array) ($body['ids'] ?? []))));
        $records = $this->listing($request, $resource)->allMatching($uuids === [] ? null : $uuids);

        return new Response(200, [
            'Content-Type'        => $adapter->getMimeType(),
            'Content-Disposition' => sprintf('attachment; filename="%s-%s.%s"', $resource->key(), date('Y-m-d'), $adapter->getFileExtension()),
        ], $adapter->export(array_values($resource->present($records)), $columns));
    }

    /**
     * The columns a download carries: the ones the request selected, else
     * every exportable column of the table — from the schema's own
     * serialisation, so the file's headers cannot disagree with the screen's.
     *
     * @return list<array{key: string, label: string}>
     */
    private function exportColumns(PanelResource $resource, mixed $requested): array
    {
        $columns = [];

        if (is_array($requested)) {
            foreach ($requested as $column) {
                if (is_array($column) && isset($column['key'])) {
                    $columns[] = ['key' => (string) $column['key'], 'label' => (string) ($column['label'] ?? $column['key'])];
                }
            }

            if ($columns !== []) {
                return $columns;
            }
        }

        foreach ($resource->table()?->toArray()['columns'] ?? [] as $column) {
            if (is_array($column) && isset($column['key']) && ($column['exportable'] ?? true) !== false) {
                $columns[] = ['key' => (string) $column['key'], 'label' => (string) ($column['label'] ?? $column['key'])];
            }
        }

        return $columns;
    }

    // ── Writing ──────────────────────────────────────────────────────────────

    private function create(ServerRequestInterface $request, PanelResource $resource): ResponseInterface
    {
        if (!$resource->permissions()->create($this->user())) {
            return $this->deny($request, $resource);
        }

        return $this->page('Resource/Create', $request, $this->presenter()->props($resource, null, $this->user()));
    }

    private function store(ServerRequestInterface $request, PanelResource $resource): ResponseInterface
    {
        if (!$resource->permissions()->create($this->user())) {
            return $this->deny($request, $resource);
        }

        $entityClass = $resource->entityClass();
        $entity      = new $entityClass();

        $errors = $this->submissions()->handle($resource, $entity, $this->body($request), $this->user());

        if ($errors === []) {
            $this->flashBag->add('success', $this->label($resource) . ' created.');

            return Response::redirect($this->indexUrl($resource));
        }

        return $this->page('Resource/Create', $request, [
            ...$this->presenter()->props($resource, null, $this->user()),
            'errors' => new \ArrayObject($errors),
        ]);
    }

    private function edit(ServerRequestInterface $request, PanelResource $resource, ?string $uuid): ResponseInterface
    {
        $entity = $this->find($resource, $uuid);

        if ($entity === null) {
            return Response::redirect($this->indexUrl($resource));
        }

        if (!$resource->permissions()->edit($entity, $this->user())) {
            return $this->deny($request, $resource);
        }

        return $this->page('Resource/Edit', $request, $this->editProps($resource, $entity));
    }

    private function update(ServerRequestInterface $request, PanelResource $resource, ?string $uuid): ResponseInterface
    {
        $entity = $this->find($resource, $uuid);

        if ($entity === null) {
            return Response::redirect($this->indexUrl($resource));
        }

        if (!$resource->permissions()->edit($entity, $this->user())) {
            return $this->deny($request, $resource);
        }

        $errors = $this->submissions()->handle($resource, $entity, $this->body($request), $this->user());

        if ($errors === []) {
            $this->flashBag->add('success', $this->label($resource) . ' updated.');

            return Response::redirect($this->urlGenerator->generate($resource->key() . '_edit', $resource->recordRouteParams($entity)));
        }

        return $this->page('Resource/Edit', $request, [
            ...$this->editProps($resource, $entity),
            'errors' => new \ArrayObject($errors),
        ]);
    }

    /**
     * The edit page's props: the form for this viewer and record, and the
     * record itself with its computed fields filled.
     *
     * @return array<string, mixed>
     */
    private function editProps(PanelResource $resource, object $entity): array
    {
        return [
            ...$this->presenter()->props($resource, $entity, $this->user()),
            'record' => $this->presenter()->record($resource, $entity, $resource->presentOne($entity)),
        ];
    }

    private function destroy(PanelResource $resource, ?string $uuid): ResponseInterface
    {
        $entity = $this->find($resource, $uuid);

        if ($entity === null) {
            return Response::redirect($this->indexUrl($resource));
        }

        if (!$resource->permissions()->delete($entity, $this->user())) {
            $this->flashBag->add('error', 'You do not have permission to do that.');

            return Response::redirect($this->indexUrl($resource));
        }

        // An entity carrying the soft-delete trait keeps its restorable trash
        // flow; anything else is genuinely removed, with its consequences
        // collected first.
        if (method_exists($entity, 'softDelete')) {
            $entity->softDelete();
            $this->entityManager->flush();
            $this->flashBag->add('success', $this->label($resource) . ' deleted.');

            return Response::redirect($this->indexUrl($resource));
        }

        $plan = (new Collector($this->entityManager))->collect($entity);

        // Refused rather than attempted. The client asked for this plan before
        // confirming, so arriving here blocked means the data changed in
        // between — answer with the same list either way.
        if ($plan->isBlocked()) {
            return Response::json([
                'error' => sprintf(
                    'Cannot delete this %s: it is referenced by %d protected record(s).',
                    strtolower($this->label($resource)),
                    count($plan->protected),
                ),
                'plan'  => $plan->toArray(),
            ], 409);
        }

        $this->executor()->apply($plan);
        $this->flashBag->add('success', $this->label($resource) . ' deleted.');

        return Response::redirect($this->indexUrl($resource));
    }

    /**
     * Delete the selected records, with the same rules as {@see destroy()}
     * applied per row. A row the viewer may not delete, or whose deletion a
     * protected reference blocks, is skipped rather than failing the whole
     * request — a selection of twenty that refuses because of one, with no
     * way to see which, is the alternative.
     */
    private function bulkDestroy(ServerRequestInterface $request, PanelResource $resource): ResponseInterface
    {
        $body  = $this->body($request);
        $uuids = is_array($body['ids'] ?? null) ? $body['ids'] : [];

        $deleted = 0;
        $skipped = 0;

        foreach ($uuids as $uuid) {
            if (!is_string($uuid)) {
                continue;
            }

            $entity = $this->find($resource, $uuid);

            if ($entity === null || !$resource->permissions()->delete($entity, $this->user())) {
                $skipped++;

                continue;
            }

            if (method_exists($entity, 'softDelete')) {
                // Already trashed: leave its deletion time alone.
                if (method_exists($entity, 'isDeleted') && $entity->isDeleted()) {
                    continue;
                }

                $entity->softDelete();
                $deleted++;

                continue;
            }

            $plan = (new Collector($this->entityManager))->collect($entity);

            if ($plan->isBlocked()) {
                $skipped++;

                continue;
            }

            $this->executor()->apply($plan);
            $deleted++;
        }

        $this->entityManager->flush();

        $label = strtolower($this->label($resource));

        $this->flashBag->add('success', sprintf('%d %s(s) deleted.', $deleted, $label));

        if ($skipped > 0) {
            $this->flashBag->add('error', sprintf('%d %s(s) were skipped: not allowed, or referenced by protected records.', $skipped, $label));
        }

        return Response::redirect($this->indexUrl($resource));
    }

    /**
     * What deleting this record would do, before anyone commits to it. The
     * same collection runs here and in {@see destroy()}, so the preview cannot
     * promise something the delete then refuses.
     */
    private function deletePreview(PanelResource $resource, ?string $uuid): ResponseInterface
    {
        $entity = $this->find($resource, $uuid);

        if ($entity === null) {
            return Response::json(['error' => 'Not found.'], 404);
        }

        if (!$resource->permissions()->delete($entity, $this->user())) {
            return Response::json(['error' => 'Forbidden.'], 403);
        }

        if (method_exists($entity, 'softDelete')) {
            // Reversible, so nothing is at stake and there is no blast radius.
            return Response::json(['blocked' => false, 'soft' => true, 'protected' => [], 'nested' => [], 'counts' => [], 'linkCounts' => []]);
        }

        return Response::json((new Collector($this->entityManager))->collect($entity)->toArray());
    }

    // ── Relations ────────────────────────────────────────────────────────────

    /**
     * Options for one declared relation field. `?q=` searches; `?values=a,b`
     * labels identifiers the client already holds. The allowlist is the
     * declaration itself: the field must be a relation field of this
     * resource's form, so no request can name an entity class or a table.
     */
    private function relationOptions(ServerRequestInterface $request, PanelResource $resource, ?string $field): ResponseInterface
    {
        // The endpoint exists to feed a form, so it is reachable exactly when
        // that form is — otherwise it becomes a way to read a table sideways.
        if (!$this->mayUseForm($resource)) {
            return Response::json(['error' => 'Forbidden.'], 403);
        }

        $relation = $this->forms()->relationFor($resource, (string) $field);

        if ($relation === null) {
            return Response::json(['error' => sprintf('"%s" is not a relation field of this resource.', (string) $field)], 404);
        }

        $query    = $request->getQueryParams();
        $resolver = $this->relations();

        if (isset($query['values'])) {
            $values = is_array($query['values']) ? $query['values'] : explode(',', (string) $query['values']);

            return Response::json([
                'data' => $resolver->byValues($relation, array_values(array_map('strval', $values))),
                'meta' => ['total' => 0, 'limit' => 0, 'truncated' => false],
            ]);
        }

        return Response::json($resolver->search($relation, trim((string) ($query['q'] ?? ''))));
    }

    /**
     * The picker's "Create …" row: make the record the user just named. Only
     * for targets creatable from a label alone; an existing row with the exact
     * label is returned rather than duplicated, because in a lookup the name
     * is the identity the user is choosing by.
     */
    private function relationCreate(ServerRequestInterface $request, PanelResource $resource, ?string $field): ResponseInterface
    {
        if (!$this->mayUseForm($resource)) {
            return Response::json(['error' => 'Forbidden.'], 403);
        }

        $relation = $this->forms()->relationFor($resource, (string) $field);

        if ($relation === null) {
            return Response::json(['error' => sprintf('"%s" is not a relation field of this resource.', (string) $field)], 404);
        }

        $resolver = $this->relations();

        if (!$resolver->creatableFromLabel($relation)) {
            return Response::json(['error' => 'This relation cannot be created from a name alone.'], 422);
        }

        $label = trim((string) ($this->body($request)['label'] ?? ''));

        if ($label === '') {
            return Response::json(['error' => 'A name is required.'], 422);
        }

        $existing = $resolver->findByLabel($relation, $label);

        if ($existing !== null) {
            return Response::json(['data' => $resolver->option($relation, $existing)]);
        }

        $entity = $resolver->newFromLabel($relation, $label);

        // The entity's own constraints still apply — a length limit or format
        // rule on the label refuses here, as it would on the full form.
        foreach ($this->validator->validate($entity) as $violation) {
            /** @var ConstraintViolationInterface $violation */
            return Response::json(['error' => (string) $violation->getMessage()], 422);
        }

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return Response::json(['data' => $resolver->option($relation, $entity)], 201);
    }

    /**
     * Add one row to a record's relation without leaving its drawer. Redirects
     * back to the record so the drawer re-renders with the new row.
     */
    private function relationStore(ServerRequestInterface $request, PanelResource $resource, ?string $uuid, ?string $field): ResponseInterface
    {
        $entity = $this->find($resource, $uuid);

        if ($entity === null) {
            return Response::redirect($this->indexUrl($resource));
        }

        // Adding a row is editing the record it hangs off.
        if (!$resource->permissions()->edit($entity, $this->user())) {
            return Response::json(['error' => 'Forbidden.'], 403);
        }

        $key = (string) $field;

        if ($this->forms()->field($resource, $key) === null) {
            return Response::json(['error' => sprintf('"%s" is not a field of this resource.', $key)], 404);
        }

        try {
            $errors = $this->submissions()->append($resource, $entity, $key, $this->body($request));
        } catch (\InvalidArgumentException $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }

        if ($errors !== []) {
            return Response::json(['errors' => $errors], 422);
        }

        return Response::redirect($this->recordUrl($resource, $entity));
    }

    // ── Board ────────────────────────────────────────────────────────────────

    /**
     * One drag on a board: the card, the column it landed in, and the two
     * cards it landed between. The client never sends a position — the
     * arithmetic belongs to the server, the only party that sees two people
     * dropping into the same gap at once. Answers with the moved record so the
     * board can replace its own copy rather than guess at what changed.
     */
    private function boardMove(ServerRequestInterface $request, PanelResource $resource, ?string $uuid): ResponseInterface
    {
        $user   = $this->user();
        $entity = $this->find($resource, $uuid);

        if ($entity === null) {
            return Response::json(['error' => 'Not found.'], 404);
        }

        if (!$resource->permissions()->edit($entity, $user)) {
            return Response::json(['error' => 'Forbidden.'], 403);
        }

        $body   = $this->body($request);
        $column = trim((string) ($body['column'] ?? ''));

        // Consulted before the write; its message is what the board shows when
        // it puts the card back.
        $allowed = $resource->permissions()->move($entity, $column, $user);

        if ($allowed !== true) {
            return Response::json(['error' => is_string($allowed) ? $allowed : 'That move is not allowed.'], 422);
        }

        $view = $resource->viewFor((string) ($body['view'] ?? ''));

        if (!$view->isBoard()) {
            return Response::json(['error' => 'This resource has no board to move cards on.'], 404);
        }

        try {
            $moved = (new BoardMover($this->entityManager))->move(
                $resource,
                $view,
                $entity,
                $column,
                $this->nullableString($body['after'] ?? null),
                $this->nullableString($body['before'] ?? null),
            );
        } catch (\InvalidArgumentException $exception) {
            return Response::json(['error' => $exception->getMessage()], 422);
        }

        return Response::json(['data' => $resource->present([$moved])[0] ?? []]);
    }

    // ── Collaborators ────────────────────────────────────────────────────────

    /**
     * A resource, as the container builds it — the one way a class becomes an
     * instance, so a resource with constructor dependencies comes from the same
     * place it does everywhere else.
     *
     * @param class-string<PanelResource> $class
     */
    private function resource(string $class): PanelResource
    {
        if ($this->app === null) {
            throw new \LogicException(self::class . ' handles requests only after the kernel has called setSubscribedServices().');
        }

        return self::service($this->app, $class);
    }

    private function listing(ServerRequestInterface $request, PanelResource $resource): ResourceListing
    {
        return new ResourceListing(
            $resource,
            $request,
            $this->entityManager,
            $this->urlGenerator,
            $this->sharedProps,
            $this->renderer,
            $this->user(),
        );
    }

    /**
     * The signed-in user, as the permissions receive it. Null when nobody is
     * signed in, which the rules must treat as "no" — though in practice the
     * firewall has already refused by then.
     */
    private function user(): ?UserInterface
    {
        return $this->tokenStorage->getToken()?->getUser();
    }

    /**
     * A page the host renders, with the props every page carries. The shared
     * props go first: they supply a flash-derived `errors` of their own, and a
     * form's validation errors must win over it.
     *
     * @param array<string, mixed> $props
     */
    private function page(string $component, ServerRequestInterface $request, array $props): ResponseInterface
    {
        return $this->renderer->render($component, [...$this->sharedProps->create(), ...$props], $request);
    }

    /**
     * Refuse, in the shape the caller expects: a browser navigating is sent
     * back to the listing, anything asking for JSON gets a 403 it can read.
     */
    private function deny(ServerRequestInterface $request, PanelResource $resource): ResponseInterface
    {
        $this->flashBag->add('error', 'You do not have permission to do that.');

        if (str_contains($request->getHeaderLine('Accept'), 'application/json') && !$request->hasHeader('X-Inertia')) {
            return Response::json(['error' => 'Forbidden.'], 403);
        }

        return Response::redirect($this->indexUrl($resource));
    }

    /** The relation endpoints feed a form, so they are open exactly when a form is. */
    private function mayUseForm(PanelResource $resource): bool
    {
        $user = $this->user();

        return $resource->permissions()->create($user) || $resource->permissions()->edit(null, $user);
    }

    private function indexUrl(PanelResource $resource): string
    {
        return $this->urlGenerator->generate($resource->key());
    }

    private function recordUrl(PanelResource $resource, object $entity): string
    {
        return $this->urlGenerator->generate($resource->showRouteName(), $resource->recordRouteParams($entity));
    }

    private function find(PanelResource $resource, ?string $uuid): ?object
    {
        $this->locator ??= new RecordLocator($this->entityManager);

        return $this->locator->find($resource, $uuid, $this->user());
    }

    private function forms(): FormResolver
    {
        return $this->forms ??= new FormResolver($this->entityManager);
    }

    private function presenter(): FormPresenter
    {
        return $this->presenter ??= new FormPresenter($this->forms(), $this->entityManager, $this->urlGenerator);
    }

    private function submissions(): SubmissionHandler
    {
        return $this->submissions ??= new SubmissionHandler($this->forms(), $this->entityManager, $this->validator);
    }

    private function executor(): PlanExecutor
    {
        return $this->executor ??= new PlanExecutor($this->entityManager);
    }

    private function relations(): RelationOptionResolver
    {
        return $this->relations ??= new RelationOptionResolver($this->entityManager);
    }

    /** @return array<string, mixed> */
    private function body(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    /** A body value that means "no neighbour" when absent or empty. */
    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Human singular: 'movies' → 'Movie'. */
    private function label(PanelResource $resource): string
    {
        return FormPresenter::label($resource);
    }
}
