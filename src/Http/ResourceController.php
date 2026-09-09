<?php

declare(strict_types=1);

namespace Modufolio\Panel\Http;

use Doctrine\ORM\EntityManagerInterface;
use Modufolio\Appkit\Inertia\Inertia;
use Modufolio\Appkit\Security\Token\TokenStorageInterface;
use Modufolio\Appkit\Security\User\UserInterface;
use Modufolio\Panel\Contracts\ExportAdapterProviderInterface;
use Modufolio\Panel\Contracts\PermissionReportProviderInterface;
use Modufolio\Panel\Delete\Collector;
use Modufolio\Panel\Delete\PlanExecutor;
use Modufolio\Panel\Form\FormPresenter;
use Modufolio\Panel\Form\FormResolver;
use Modufolio\Panel\Form\SubmissionHandler;
use Modufolio\Panel\Resource\BoardMover;
use Modufolio\Panel\Resource\FieldPickUrls;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\RecordLocator;
use Modufolio\Panel\Resource\RelationAddUrls;
use Modufolio\Panel\Resource\RelationOptionResolver;
use Modufolio\Panel\Resource\ResourceListing;
use Modufolio\Panel\Routing\RouteUrls;
use Modufolio\Panel\Search\GlobalSearch;
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
 * Everything it needs arrives through the constructor; it holds no
 * application and asks no container. {@see \Modufolio\Panel\PanelModule}
 * wires it — list the module in config/modules.php and the kernel builds the
 * controller from the container like any other. What only the host knows —
 * how a page is rendered, which props every page carries — it declares as
 * the kernel's Inertia renderer; download
 * formats ({@see ExportAdapterProviderInterface}) and a {@see FormResolver}
 * naming the media entity have module defaults a host may override.
 *
 * The resource arrives as an argument, not a lookup. A generated route
 * carries its class as the `resourceClass` default; the host's parameter
 * resolver turns that into the instance its container builds, the same way
 * it fills a `#[MapEntity]` argument. So this controller never names a
 * resource class, never resolves one, and holds nothing that could resolve
 * anything else.
 */
final class ResourceController
{
    private ?FormPresenter $presenter = null;
    private ?SubmissionHandler $submissions = null;
    private ?PlanExecutor $executor = null;
    private ?RecordLocator $locator = null;
    private ?RelationOptionResolver $relations = null;

    private ?RelationAddUrls $addUrls = null;
    private ?FieldPickUrls $fieldPickUrls = null;
    private readonly FormResolver $forms;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ValidatorInterface $validator,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly FlashBagInterface $flashBag,
        ?FormResolver $forms = null,
        private readonly ?ExportAdapterProviderInterface $exports = null,
        private readonly ?GlobalSearch $search = null,
        private readonly ?PermissionReportProviderInterface $permissions = null,
    ) {
        // A host without a media library gets the plain resolver.
        $this->forms = $forms ?? new FormResolver($entityManager);
    }

    /**
     * Arguments are spread by name, so the order here is free: `$operation`
     * and `$resource` both come from the route's defaults, the first as the
     * string the loader wrote and the second as whatever the host's parameter
     * resolver built from it.
     */
    public function handle(
        ServerRequestInterface $request,
        string $operation,
        ?PanelResource $resource = null,
        ?string $uuid = null,
        ?string $field = null,
    ): ResponseInterface|Inertia {
        // The operations that span resources instead of naming one.
        if ($operation === 'search') {
            return $this->search($request);
        }

        if ($operation === 'permissions') {
            return $this->permissions();
        }

        // Every other route names a resource, so a null here is a wiring
        // fault — the host's parameter resolver did not fill the argument —
        // and not something a request could provoke.
        if ($resource === null) {
            throw new \LogicException(sprintf(
                'The panel resource for operation "%s" was not resolved. The host\'s parameterResolver() must fill a %s argument from the route\'s "resourceClass" default.',
                $operation,
                PanelResource::class,
            ));
        }

        return match ($operation) {
            'index'           => $this->index($request, $resource),
            'show'            => $this->show($request, $resource, $uuid),
            'export'          => $this->export($request, $resource),
            'create'          => $this->create($request, $resource),
            'store'           => $this->store($request, $resource),
            'edit'            => $this->edit($request, $resource, $uuid),
            'update'          => $this->update($request, $resource, $uuid),
            'patch'           => $this->patch($request, $resource, $uuid),
            'destroy'         => $this->destroy($request, $resource, $uuid),
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

    private function index(ServerRequestInterface $request, PanelResource $resource): ResponseInterface|Inertia
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
                return $this->redirect($request, $this->recordUrl($resource, $record));
            }
        }

        return $this->listing($request, $resource)->render();
    }

    /**
     * The listing with the record's drawer stacked on top: the frame's type,
     * title and payload come from the resource, the tabs from its drawer,
     * and next/previous from the listing's own order.
     */
    private function show(ServerRequestInterface $request, PanelResource $resource, ?string $uuid): ResponseInterface|Inertia
    {
        $entity = $this->find($resource, $uuid);

        if ($entity === null) {
            return $this->redirect($request, $this->indexUrl($resource));
        }

        if (!$resource->permissions()->view($entity, $this->user())) {
            return $this->deny($request, $resource);
        }

        $listing      = $this->listing($request, $resource);
        $navigation   = $listing->navigationUrls($entity);
        $record       = $resource->presentOne($entity);
        $capabilities = $listing->capabilities();
        $verdict      = $capabilities->verdicts()->verdict($entity);

        $frame = [
            'type'              => $resource->drawerType(),
            'data'              => $record,
            'title'             => $resource->drawerTitle($entity),
            'description'       => '',
            'width'             => 'md',
            'href'              => $this->recordUrl($resource, $entity),
            'nextRecordUrl'     => $navigation['next'],
            'previousRecordUrl' => $navigation['previous'],
            // What the viewer may do with this record and where, so the
            // frame's footer offers exactly what the endpoints would accept.
            'can'               => $verdict['can'],
            'why'               => $verdict['why'],
            'urls'              => [
                'edit'    => $capabilities->recordUrl('edit', $entity),
                'destroy' => $capabilities->recordUrl('destroy', $entity),
            ],
            // Badges count the rows the record already carries, so declaring
            // a tab costs no query. The resolved form comes along so an
            // addable list can carry the form its add action opens.
            'tabs'              => $this->fieldPickUrls()->stamp(
                $this->addUrls()->stamp(
                    $resource->drawerTabsFor($record, $this->presenter()->resolvedFields($resource)),
                    $resource,
                    $entity,
                    $this->user(),
                ),
                $resource,
                $entity,
                $this->user(),
            ),
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
            return $this->json(['message' => 'Export is not configured for this application.'], 422);
        }

        $body = $this->body($request);

        try {
            $adapter = $this->exports->get((string) ($body['format'] ?? 'csv'));
        } catch (\InvalidArgumentException $e) {
            return $this->json(['message' => $e->getMessage()], 422);
        }

        $columns = $this->exportColumns($resource, $body['columns'] ?? null);

        if ($columns === []) {
            return $this->json(['message' => 'This resource declares no exportable columns.'], 422);
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

    private function create(ServerRequestInterface $request, PanelResource $resource): ResponseInterface|Inertia
    {
        if (!$resource->permissions()->create($this->user())) {
            return $this->deny($request, $resource);
        }

        return $this->page('Resource/Create', $this->presenter()->props($resource, null, $this->user()));
    }

    private function store(ServerRequestInterface $request, PanelResource $resource): ResponseInterface|Inertia
    {
        if (!$resource->permissions()->create($this->user())) {
            return $this->deny($request, $resource);
        }

        $entityClass = $resource->entityClass();
        $entity      = new $entityClass();

        $errors = $this->submissions()->handle($resource, $entity, $this->body($request), $this->user());

        if ($errors === []) {
            $this->flashBag->add('success', $this->label($resource) . ' created.');

            return $this->redirect($request, $this->indexUrl($resource));
        }

        return $this->page('Resource/Create', [
            ...$this->presenter()->props($resource, null, $this->user()),
            'errors' => new \ArrayObject($errors),
        ]);
    }

    private function edit(ServerRequestInterface $request, PanelResource $resource, ?string $uuid): ResponseInterface|Inertia
    {
        $entity = $this->find($resource, $uuid);

        if ($entity === null) {
            return $this->redirect($request, $this->indexUrl($resource));
        }

        if (!$resource->permissions()->edit($entity, $this->user())) {
            return $this->deny($request, $resource);
        }

        return $this->page('Resource/Edit', $this->editProps($resource, $entity));
    }

    private function update(ServerRequestInterface $request, PanelResource $resource, ?string $uuid): ResponseInterface|Inertia
    {
        $entity = $this->find($resource, $uuid);

        if ($entity === null) {
            return $this->redirect($request, $this->indexUrl($resource));
        }

        if (!$resource->permissions()->edit($entity, $this->user())) {
            return $this->deny($request, $resource);
        }

        $errors = $this->submissions()->handle($resource, $entity, $this->body($request), $this->user());

        if ($errors === []) {
            $this->flashBag->add('success', $this->label($resource) . ' updated.');

            return $this->redirect($request, $this->urlGenerator->generate($resource->key() . '_edit', $resource->recordRouteParams($entity)));
        }

        return $this->page('Resource/Edit', [
            ...$this->editProps($resource, $entity),
            'errors' => new \ArrayObject($errors),
        ]);
    }

    /**
     * One field of one record, written where it is read: the listing's
     * editable cells.
     *
     * Three gates, none of which the client can talk its way past. The
     * record's own `edit` permission, as the full form asks. Then the table's
     * declaration: only a column that says `editable()` may be written this
     * way, so the endpoint's surface is exactly what the listing draws a
     * control for — a field that is merely present in the form is not enough.
     * Then the submission handler, which applies per-field write access,
     * coercion and the field's own rules, told to consider only the fields
     * that arrived.
     *
     * A column may display one field under another name
     * (`Column::make('status')->value('account_status')`), so the body is
     * keyed the way the client knows the column and translated here — the
     * mapping a hand-written page used to restate in its save handler.
     */
    private function patch(ServerRequestInterface $request, PanelResource $resource, ?string $uuid): ResponseInterface
    {
        $entity = $this->find($resource, $uuid);

        if ($entity === null) {
            return $this->redirect($request, $this->listUrl($request, $resource));
        }

        if (!$resource->permissions()->edit($entity, $this->user())) {
            return $this->deny($request, $resource);
        }

        $editable = $this->editableFields($resource);
        $values   = [];

        foreach ($this->body($request) as $column => $value) {
            if (isset($editable[$column])) {
                $values[$editable[$column]] = $value;
            }
        }

        if ($values === []) {
            // Says which fields *are* writable this way rather than only that
            // this one is not: the usual cause is a column that renders a
            // control without declaring `editable()`.
            $this->flashBag->add('error', sprintf(
                'Nothing in that request can be edited from the list.%s',
                $editable === [] ? '' : ' Editable columns: ' . implode(', ', array_keys($editable)) . '.',
            ));

            return $this->redirect($request, $this->listUrl($request, $resource));
        }

        // Per-field write access, asked here rather than left to the handler:
        // the handler *drops* a field this user may not write, which is right
        // for a whole form (the rest of it still saves) and wrong for a single
        // cell — it would report success having changed nothing.
        $permissions = $resource->permissions();

        foreach (array_keys($values) as $field) {
            if (!$permissions->writable($field, $this->user(), $entity)) {
                $this->flashBag->add('error', sprintf('You may not change %s.', str_replace('_', ' ', $field)));

                return $this->redirect($request, $this->listUrl($request, $resource));
            }
        }

        $errors = $this->submissions()->handle($resource, $entity, $values, $this->user(), array_keys($values));

        if ($errors === []) {
            $this->flashBag->add('success', $this->label($resource) . ' updated.');
        } else {
            // The row snaps back to what the server holds, so the reason has
            // to travel as a message: there is no field on screen to pin it
            // to once the cell has closed.
            $this->flashBag->add('error', reset($errors));
        }

        return $this->redirect($request, $this->listUrl($request, $resource));
    }

    /**
     * The columns a listing may write, as `column key => field written`.
     *
     * A column reading a nested path (`organization.name`) is not one of
     * them: there is no single field behind it to set, and a form that meant
     * to edit the related record would say so.
     *
     * The write goes through the form's declaration — its coercion, its
     * per-field access, its rules — so a column that is editable but names no
     * form field is refused loudly rather than saved into nothing. The listing
     * draws a control for it, and a control whose changes evaporate is worse
     * than one that was never offered.
     *
     * @return array<string, string>
     */
    private function editableFields(PanelResource $resource): array
    {
        $schema = $resource->table();

        if ($schema === null) {
            return [];
        }

        $formFields = array_column($this->forms()->fieldsFor($resource), 'key');
        $editable   = [];

        foreach ($schema->declaredColumns() as $column) {
            if (!$column->isEditable() || str_contains($column->field(), '.')) {
                continue;
            }

            if (!in_array($column->field(), $formFields, true)) {
                throw new \LogicException(sprintf(
                    '%s: column "%s" is editable but writes "%s", which the form does not declare. '
                    . 'Add the field to form(), or drop editable() from the column.',
                    $resource::class,
                    $column->key(),
                    $column->field(),
                ));
            }

            $editable[$column->key()] = $column->field();
        }

        return $editable;
    }

    /**
     * Back to the list the edit was made from, filters and page intact.
     *
     * The client sends its list state on the request, because the redirect's
     * URL is what Inertia reloads: landing on the bare index would answer an
     * edit made on page 3 of a filtered list with page 1 of an unfiltered one.
     */
    private function listUrl(ServerRequestInterface $request, PanelResource $resource): string
    {
        $query = http_build_query($request->getQueryParams());

        return $this->indexUrl($resource) . ($query !== '' ? '?' . $query : '');
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

    private function destroy(ServerRequestInterface $request, PanelResource $resource, ?string $uuid): ResponseInterface
    {
        $entity = $this->find($resource, $uuid);

        if ($entity === null) {
            return $this->redirect($request, $this->indexUrl($resource));
        }

        if (!$resource->permissions()->delete($entity, $this->user())) {
            $this->flashBag->add('error', 'You do not have permission to do that.');

            return $this->redirect($request, $this->indexUrl($resource));
        }

        // An entity carrying the soft-delete trait keeps its restorable trash
        // flow; anything else is genuinely removed, with its consequences
        // collected first.
        if (method_exists($entity, 'softDelete')) {
            $entity->softDelete();
            $this->entityManager->flush();
            $this->flashBag->add('success', $this->label($resource) . ' deleted.');

            return $this->redirect($request, $this->indexUrl($resource));
        }

        $plan = (new Collector($this->entityManager))->collect($entity);

        // Refused rather than attempted. The client asked for this plan before
        // confirming, so arriving here blocked means the data changed in
        // between — answer with the same list either way.
        if ($plan->isBlocked()) {
            return $this->json([
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

        return $this->redirect($request, $this->indexUrl($resource));
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
        $asked   = 0;
        /** @var array<string, int> reason => how many */
        $skipped = [];
        $skip    = static function (string $reason) use (&$skipped): void {
            $skipped[$reason] = ($skipped[$reason] ?? 0) + 1;
        };

        foreach ($uuids as $uuid) {
            if (!is_string($uuid)) {
                continue;
            }

            ++$asked;
            $entity = $this->find($resource, $uuid);

            if ($entity === null) {
                $skip('no longer exists');

                continue;
            }

            if (!$resource->permissions()->delete($entity, $this->user())) {
                $skip($resource->permissions()->reason('delete', $entity, $this->user()) ?? 'not allowed');

                continue;
            }

            if (method_exists($entity, 'softDelete')) {
                // Already trashed: leave its deletion time alone, and say so.
                if (method_exists($entity, 'isDeleted') && $entity->isDeleted()) {
                    $skip('already in the trash');

                    continue;
                }

                $entity->softDelete();
                $deleted++;

                continue;
            }

            $plan = (new Collector($this->entityManager))->collect($entity);

            if ($plan->isBlocked()) {
                $skip('referenced by protected records');

                continue;
            }

            $this->executor()->apply($plan);
            $deleted++;
        }

        $this->entityManager->flush();

        // The outcome, reason by reason: "7 of 10 deleted" and one line per
        // reason something was skipped, so nobody has to guess which three.
        $label = strtolower($this->label($resource));

        $this->flashBag->add(
            $deleted > 0 ? 'success' : 'warning',
            $skipped === []
                ? sprintf('%d %s(s) deleted.', $deleted, $label)
                : sprintf('%d of %d %s(s) deleted.', $deleted, $asked, $label),
        );

        foreach ($skipped as $reason => $count) {
            $this->flashBag->add('warning', sprintf('%d skipped: %s.', $count, rtrim($reason, '.')));
        }

        return $this->redirect($request, $this->indexUrl($resource));
    }

    /**
     * `GET {prefix}/search?q=…`: the panel's search across every resource
     * that opted in, as JSON for the search dialog. Bounded per resource,
     * scoped per viewer, by {@see GlobalSearch}.
     */
    private function search(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->search === null) {
            return $this->json(['message' => 'Search across resources is not wired.'], 404);
        }

        $params = $request->getQueryParams();
        $query  = is_string($params['q'] ?? null) ? $params['q'] : '';
        $limit  = is_numeric($params['limit'] ?? null) ? max(1, min(20, (int) $params['limit'])) : GlobalSearch::DEFAULT_LIMIT;

        return $this->json($this->search->search($query, $this->user(), $limit));
    }

    /**
     * `GET {prefix}/_permissions`: the permission inspector as a page — what
     * each role may do on every resource, and where two layers disagree.
     *
     * The report is the application's to build (its routes, its roles, its
     * idea of a user), so the panel asks a provider for it and renders what
     * comes back. Without one there is nothing to show, and saying so is more
     * use than an empty grid.
     */
    private function permissions(): ResponseInterface|Inertia
    {
        $report = $this->permissions?->report();

        if ($report === null) {
            return $this->json([
                'message' => 'The permission inspector is not wired: register a '
                    . PermissionReportProviderInterface::class . ' to enable this page.',
            ], 404);
        }

        return $this->page('Resource/Permissions', ['report' => $report->toArray()]);
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
            return $this->json(['message' => 'Not found.'], 404);
        }

        if (!$resource->permissions()->delete($entity, $this->user())) {
            return $this->json(['message' => 'Forbidden.'], 403);
        }

        if (method_exists($entity, 'softDelete')) {
            // Reversible, so nothing is at stake and there is no blast radius.
            return $this->json(['blocked' => false, 'soft' => true, 'protected' => [], 'nested' => [], 'counts' => [], 'linkCounts' => []]);
        }

        return $this->json((new Collector($this->entityManager))->collect($entity)->toArray());
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
            return $this->json(['message' => 'Forbidden.'], 403);
        }

        $relation = $this->forms()->relationFor($resource, (string) $field);

        if ($relation === null) {
            return $this->json(['message' => sprintf('"%s" is not a relation field of this resource.', (string) $field)], 404);
        }

        $query    = $request->getQueryParams();
        $resolver = $this->relations();

        if (isset($query['values'])) {
            $values = is_array($query['values']) ? $query['values'] : explode(',', (string) $query['values']);

            return $this->json([
                'data' => $resolver->byValues($relation, array_values(array_map('strval', $values))),
                'meta' => ['total' => 0, 'limit' => 0, 'truncated' => false],
            ]);
        }

        return $this->json($resolver->search($relation, trim((string) ($query['q'] ?? ''))));
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
            return $this->json(['message' => 'Forbidden.'], 403);
        }

        $relation = $this->forms()->relationFor($resource, (string) $field);

        if ($relation === null) {
            return $this->json(['message' => sprintf('"%s" is not a relation field of this resource.', (string) $field)], 404);
        }

        $resolver = $this->relations();

        if (!$resolver->creatableFromLabel($relation)) {
            return $this->json(['message' => 'This relation cannot be created from a name alone.'], 422);
        }

        $label = trim((string) ($this->body($request)['label'] ?? ''));

        if ($label === '') {
            return $this->json(['message' => 'A name is required.'], 422);
        }

        $existing = $resolver->findByLabel($relation, $label);

        if ($existing !== null) {
            return $this->json(['data' => $resolver->option($relation, $existing)]);
        }

        $entity = $resolver->newFromLabel($relation, $label);

        // The entity's own constraints still apply — a length limit or format
        // rule on the label refuses here, as it would on the full form.
        foreach ($this->validator->validate($entity) as $violation) {
            /** @var ConstraintViolationInterface $violation */
            return $this->json(['message' => (string) $violation->getMessage()], 422);
        }

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $this->json(['data' => $resolver->option($relation, $entity)], 201);
    }

    /**
     * Add one row to a record's relation without leaving its drawer. Redirects
     * back to the record so the drawer re-renders with the new row.
     */
    private function relationStore(ServerRequestInterface $request, PanelResource $resource, ?string $uuid, ?string $field): ResponseInterface
    {
        $entity = $this->find($resource, $uuid);

        if ($entity === null) {
            return $this->redirect($request, $this->indexUrl($resource));
        }

        // Adding a row is editing the record it hangs off.
        if (!$resource->permissions()->edit($entity, $this->user())) {
            return $this->json(['message' => 'Forbidden.'], 403);
        }

        $key = (string) $field;

        if ($this->forms()->field($resource, $key) === null) {
            return $this->json(['message' => sprintf('"%s" is not a field of this resource.', $key)], 404);
        }

        try {
            $errors = $this->submissions()->append($resource, $entity, $key, $this->body($request));
        } catch (\InvalidArgumentException $e) {
            return $this->json(['message' => $e->getMessage()], 422);
        }

        if ($errors !== []) {
            return $this->json(['message' => 'The submitted data was invalid.', 'errors' => $errors], 422);
        }

        return $this->redirect($request, $this->recordUrl($resource, $entity));
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
            return $this->json(['message' => 'Not found.'], 404);
        }

        if (!$resource->permissions()->edit($entity, $user)) {
            return $this->json(['message' => 'Forbidden.'], 403);
        }

        $body   = $this->body($request);
        $column = trim((string) ($body['column'] ?? ''));

        // Consulted before the write; its message is what the board shows when
        // it puts the card back.
        $allowed = $resource->permissions()->move($entity, $column, $user);

        if ($allowed !== true) {
            return $this->json(['message' => is_string($allowed) ? $allowed : 'That move is not allowed.'], 422);
        }

        $view = $resource->viewFor((string) ($body['view'] ?? ''));

        if (!$view->isBoard()) {
            return $this->json(['message' => 'This resource has no board to move cards on.'], 404);
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
            return $this->json(['message' => $exception->getMessage()], 422);
        }

        return $this->json(['data' => $resource->present([$moved])[0] ?? []]);
    }

    // ── Collaborators ────────────────────────────────────────────────────────

    private function listing(ServerRequestInterface $request, PanelResource $resource): ResourceListing
    {
        return new ResourceListing(
            $resource,
            $request,
            $this->entityManager,
            $this->urlGenerator,
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
     * A page, for the kernel to finish: it merges the props every page
     * carries underneath these, so a form's validation `errors` win over a
     * flash-derived default, and renders through the host's Inertia setup.
     *
     * @param array<string, mixed> $props
     */
    private function page(string $component, array $props): Inertia
    {
        return Inertia::render($component, $props);
    }

    /**
     * Refuse, in the shape the caller expects: a browser navigating is sent
     * back to the listing, anything asking for JSON gets a 403 it can read.
     */
    private function deny(ServerRequestInterface $request, PanelResource $resource): ResponseInterface
    {
        $this->flashBag->add('error', 'You do not have permission to do that.');

        if (str_contains($request->getHeaderLine('Accept'), 'application/json') && !$request->hasHeader('X-Inertia')) {
            return $this->json(['message' => 'Forbidden.'], 403);
        }

        return $this->redirect($request, $this->indexUrl($resource));
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

    /**
     * A redirect the Inertia client can follow: 303 after PUT, PATCH or
     * DELETE, so the browser re-requests the target with GET instead of
     * replaying the method against the listing. 302 keeps its meaning for
     * the GET and POST cases.
     */
    private function redirect(ServerRequestInterface $request, string $url): ResponseInterface
    {
        $replayable = in_array(strtoupper($request->getMethod()), ['GET', 'HEAD', 'POST'], true);

        return Response::redirect($url, $replayable ? 302 : 303);
    }

    /**
     * A JSON reply in the one envelope every endpoint here uses — `message`
     * for the human, `errors` by field when there are any — with whatever
     * the flash bag holds riding along as `_toasts`, so a caller that never
     * navigates still hears what the server had to say. Draining the bag
     * here means the same message does not surface again on the next page.
     *
     * @param array<string, mixed> $data
     */
    private function json(array $data, int $status = 200): ResponseInterface
    {
        $toasts = [];

        foreach ($this->flashBag->all() as $type => $messages) {
            foreach ((array) $messages as $message) {
                $toasts[] = ['type' => $type, 'message' => (string) $message];
            }
        }

        if ($toasts !== []) {
            $data['_toasts'] = $toasts;
        }

        return Response::json($data, $status);
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
        return $this->forms;
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

    private function addUrls(): RelationAddUrls
    {
        return $this->addUrls ??= new RelationAddUrls($this->urlGenerator);
    }

    private function fieldPickUrls(): FieldPickUrls
    {
        return $this->fieldPickUrls ??= new FieldPickUrls($this->urlGenerator);
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
