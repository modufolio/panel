<?php

declare(strict_types=1);

namespace Modufolio\Panel\Resource;

use Modufolio\Panel\Routing\RouteUrls;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * What one viewer may do with one resource, and where.
 *
 * Every write button the panel offers is a conjunction of two facts that
 * live in two places: the router says whether the resource *generated* the
 * route (a resource that opted out of editing has no edit route, whoever is
 * asking), and the resource's {@see Permissions} say whether *this viewer*
 * may use it. Both must hold. Before this class the conjunction was spelled
 * out wherever a button was decided — the listing's `resource` prop, its
 * default row actions, the form's delete button, the drawer frame's URLs —
 * and each site was one chance to ask half the question.
 *
 * So the pair is asked here, once per resource and viewer, and the surfaces
 * read the answer. Route existence is memoised per operation: the generator
 * has no cheap "does this route exist", only a generation to try and a throw
 * to catch, and a listing asks about the same handful of routes several
 * times per render.
 *
 * Operations are named the way the loader names routes, without the key:
 * `create`, `edit`, `destroy`, `board_move`, … and `index` for the bare key.
 */
final class ResourceCapabilities
{
    /** @var array<string, string|null> */
    private array $templates = [];

    /** @var array<string, string|null> */
    private array $urls = [];

    public function __construct(
        private readonly PanelResource $resource,
        private readonly UrlGeneratorInterface $urlGenerator,
        /** Who is asking. Null when nobody is signed in. */
        private readonly ?object $user = null,
    ) {
    }

    public function resource(): PanelResource
    {
        return $this->resource;
    }

    public function user(): ?object
    {
        return $this->user;
    }

    // ── The router's half ────────────────────────────────────────────────────

    /** Whether the resource generated the route for this operation. */
    public function has(string $operation): bool
    {
        return $this->template($operation) !== null;
    }

    /**
     * The route as a URL template, `{id}` where the record's uuid goes. Null
     * when the resource did not generate it. A route that takes no record
     * comes back as its plain URL, so this answers for every operation.
     */
    public function template(string $operation): ?string
    {
        return $this->templates[$operation] ??= RouteUrls::template($this->urlGenerator, $this->routeName($operation));
    }

    /**
     * The route's URL, for an operation that takes no record. Null when the
     * resource did not generate it.
     */
    public function url(string $operation): ?string
    {
        return $this->urls[$operation] ??= RouteUrls::url($this->urlGenerator, $this->routeName($operation));
    }

    /** The route's URL for this record, or null when the resource did not generate it. */
    public function recordUrl(string $operation, object $record): ?string
    {
        if (!$this->has($operation)) {
            return null;
        }

        return RouteUrls::url($this->urlGenerator, $this->routeName($operation), $this->resource->recordRouteParams($record));
    }

    /**
     * Every generated route by operation, as the client receives them: a
     * plain URL for the ones without a record, an `{id}` template for the
     * ones with, null where the resource opted out — so the client can hide
     * what it cannot reach without knowing the route names.
     *
     * @return array<string, string|null>
     */
    public function urls(): array
    {
        return [
            'index'         => $this->url('index'),
            'create'        => $this->url('create'),
            'store'         => $this->url('store'),
            'show'          => $this->template('show'),
            'edit'          => $this->template('edit'),
            'update'        => $this->template('update'),
            'patch'         => $this->template('patch'),
            'destroy'       => $this->template('destroy'),
            'deletePreview' => $this->template('delete_preview'),
            'bulkDestroy'   => $this->url('bulk_destroy'),
            'export'        => $this->url('export'),
            'boardMove'     => $this->template('board_move'),
        ];
    }

    // ── Both halves ──────────────────────────────────────────────────────────

    /** The create route exists and this viewer may create. */
    public function create(): bool
    {
        return $this->has('create') && $this->permissions()->create($this->user);
    }

    /**
     * The edit route exists and this viewer may edit — the type when no
     * record is given, that record when one is.
     */
    public function edit(?object $record = null): bool
    {
        return $this->has('edit') && $this->permissions()->edit($record, $this->user);
    }

    /** The destroy route exists and this viewer may delete the type, or the record. */
    public function delete(?object $record = null): bool
    {
        return $this->has('destroy') && $this->permissions()->delete($record, $this->user);
    }

    /** The export route exists and this viewer may export. */
    public function export(): bool
    {
        return $this->has('export') && $this->permissions()->export($this->user);
    }

    /**
     * Whether cards on a board can be dragged. Deliberately not {@see edit()}:
     * that one also requires the edit *form* route, and a board is a way of
     * reading records that groups them by a field they already have — a
     * resource can have one without ever declaring a form. What a move does
     * require is the move route and the edit permission, which is exactly
     * what the move endpoint itself checks.
     */
    public function move(): bool
    {
        return $this->has('board_move') && $this->permissions()->edit(null, $this->user);
    }

    /** The record-level verdicts, asked with the same permissions and viewer. */
    public function verdicts(): RecordVerdicts
    {
        return new RecordVerdicts($this->permissions(), $this->user);
    }

    public function permissions(): Permissions
    {
        return $this->resource->permissions();
    }

    private function routeName(string $operation): string
    {
        $key = $this->resource->key();

        return $operation === 'index' ? $key : $key . '_' . $operation;
    }
}
