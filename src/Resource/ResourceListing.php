<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Resource;

use Modufolio\Panel\Http\JsonApiPaginationTrait;
use Modufolio\Panel\Contracts\PageRendererInterface;
use Modufolio\Panel\Contracts\SharedPropsInterface;
use Modufolio\Panel\Query\ListQueryInterface;
use Modufolio\Panel\Table\BulkAction;
use Modufolio\Panel\Table\Column;
use Modufolio\Panel\Table\Constraint;
use Modufolio\Panel\Table\Filter;
use Modufolio\Panel\Table\Group;
use Modufolio\Panel\Table\RowAction;
use Modufolio\Panel\Table\RelationOptions;
use Modufolio\Panel\Table\Summary;
use Modufolio\Panel\Table\TableSchema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * A {@see PanelResource} bound to the current request, ready to render.
 *
 * Injected into controller actions by {@see \App\Resolver\ResourceListingResolver}
 * via the `#[Resource]` attribute, in the same spirit as `#[Template]`:
 *
 *     public function index(#[Resource(OrganizationResource::class)] ResourceListing $listing)
 *     {
 *         return $listing->render();
 *     }
 *
 * Immutable-ish: `withDrawer()` and `withProps()` return clones, so an action
 * can layer a drawer stack on without mutating shared state.
 */
final class ResourceListing
{
    use JsonApiPaginationTrait;

    /** @var array<int, array<string, mixed>> */
    private array $stack = [];

    /** @var array<string, mixed> */
    private array $extraProps = [];

    /** @var array<string, mixed> Parsed params in scope during navigationUrls(). */
    private array $navigationParams = [];

    public function __construct(
        private readonly PanelResource $resource,
        private readonly ServerRequestInterface $request,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly SharedPropsInterface $sharedProps,
        private readonly PageRendererInterface $renderer,
        /** Who is asking — the resource's scopeQuery() decides what that means. */
        private readonly ?object $user = null,
    ) {
    }

    public function resource(): PanelResource
    {
        return $this->resource;
    }

    /**
     * Overlay a drawer stack on the listing.
     *
     * @param array<int, array<string, mixed>> $stack
     */
    public function withDrawer(array $stack): self
    {
        $clone = clone $this;
        $clone->stack = $stack;

        return $clone;
    }

    /**
     * @param array<string, mixed> $props
     */
    public function withProps(array $props): self
    {
        $clone = clone $this;
        $clone->extraProps = [...$this->extraProps, ...$props];

        return $clone;
    }

    public function render(): ResponseInterface
    {
        $queryParams = $this->request->getQueryParams();
        $params      = $this->resource->parseListParams($queryParams);
        $params['filters'] = $this->resource->filterValues($params, $queryParams);
        $pagination  = $this->getJsonApiPagination($queryParams);

        $query = $this->resource->buildListQuery($params, $pagination['limit'], $pagination['offset']);

        $alias      = $this->resource->queryAlias();
        $repository = $this->repository();

        $listQb = $query->apply($repository->createQueryBuilder($alias));
        $this->applySchemaFilters($listQb, $alias, $params);
        $this->applyGrouping($listQb, $alias, $params);

        [, $sortDirection] = $this->resolveSortField($this->resource->listQueryClass(), $params['sort']);
        $this->applyKeysetTiebreak($listQb, $alias, $sortDirection);
        $entities = $listQb->getQuery()->getResult();

        // The count must see the same filters, or the pager advertises pages
        // that do not exist.
        $countQb = $query->forCount($repository->createQueryBuilder($alias));
        $this->applySchemaFilters($countQb, $alias, $params);

        $totalCount = (int)$countQb
            ->select("COUNT({$alias}.id)")
            ->getQuery()
            ->getSingleScalarResult();

        $key    = $this->resource->key();
        $schema = $this->resource->tableSchema();

        if ($schema !== null) {
            $schema = $this->resolveFilterOptions($schema);
            $schema = $this->resolveActions($schema, $key);
        }

        return $this->renderer->render(
            $this->resource->indexComponent(),
            [
                'filters' => [
                    'search'  => $params['search'],
                    'trashed' => $params['trashed'],
                    'sort'    => $this->formatSortParam($params['sort']),
                    'group'   => $params['group'] ?? null,
                    'constraints' => $params['constraints'] ?? [],
                    ...$params['filters'],
                    ...$this->resource->filterProps($params),
                ],
                $key    => $this->wrapWithJsonApiPagination(
                    $this->resource->present($entities),
                    $totalCount,
                    $pagination['page'],
                    $pagination['perPage'],
                    $key,
                    // Summaries belong with the data, not the schema: they
                    // change with every filter, whereas the schema does not.
                    ['summaries' => $this->summaries($query, $alias, $params)],
                ),
                'stack' => $this->stack,
                // Lets the generic Resource/Index page configure itself:
                // which prop holds the rows, where the listing lives, which
                // DrawerStack slot a record renders into — and which write
                // actions to offer, derived from whether the routes exist
                // rather than from any extra declaration. A page written for
                // one resource knows all of this already and ignores it.
                'resource' => [
                    'key'        => $key,
                    'baseUrl'    => '/panel/' . $key,
                    'drawerType' => $this->resource->drawerType(),
                    // Both must hold: the route has to exist *and* this user
                    // has to be allowed. Route existence alone told the client
                    // what the resource supports, not what the viewer may do.
                    'canCreate'  => $this->routeExists($key . '_create')
                        && $this->resource->canCreate($this->user),
                    'canEdit'    => $this->routeExists($key . '_edit')
                        && $this->resource->canEdit(null, $this->user),
                    'canDelete'  => $this->routeExists($key . '_destroy')
                        && $this->resource->canDelete(null, $this->user),
                    // Null when the resource has no generated export route, in
                    // which case ExportButton falls back to its client-side
                    // path — which can only ever see the loaded page.
                    'exportUrl'  => $this->exportUrl($key),
                ],
                ...($schema !== null ? ['table' => $schema->toArray($this->resource->listQueryClass())] : []),
                ...$this->extraProps,
                ...$this->sharedProps->create(),
            ],
            $this->request,
        );
    }

    /**
     * Previous/next record URLs for arrow-key traversal inside a drawer.
     *
     * @return array{next: string|null, previous: string|null}
     */
    public function navigationUrls(object $entity): array
    {
        $queryParams = $this->request->getQueryParams();
        $params      = $this->resource->parseListParams($queryParams);
        $params['filters'] = $this->resource->filterValues($params, $queryParams);

        // findAdjacent() builds its own query builders, so stash the parsed
        // params for applySchemaFilters() rather than threading them through.
        $this->navigationParams = $params;

        /** @var class-string<ListQueryInterface> $queryClass */
        $queryClass = $this->resource->listQueryClass();

        [$sortField, $sortDirection] = $this->resolveSortField($queryClass, $params['sort']);

        $alias        = $this->resource->queryAlias();
        $currentValue = $this->resource->sortValue($entity, $sortField);
        $currentId    = $this->identifierOf($entity);

        // $sortField comes from the list query's hardcoded allowlist, so it is
        // safe to interpolate; the compared values stay bound parameters.
        $forward  = $sortDirection === 'DESC' ? '<' : '>';
        $backward = $sortDirection === 'DESC' ? '>' : '<';

        $next = $this->findAdjacent(
            $this->resource->buildListQuery($params, null, null),
            $alias,
            $sortField,
            $forward,
            $currentValue,
            $currentId,
        );

        // Walking backwards means reversing the *effective* sort, otherwise an
        // empty sort param returns the global first row rather than the
        // immediate predecessor.
        $effectiveSort = $params['sort'] ?: [$sortField => $sortDirection];
        $reversedSort  = array_map(
            static fn (string $direction): string => $direction === 'ASC' ? 'DESC' : 'ASC',
            $effectiveSort,
        );

        $previous = $this->findAdjacent(
            $this->resource->buildListQuery([...$params, 'sort' => $reversedSort], null, null),
            $alias,
            $sortField,
            $backward,
            $currentValue,
            $currentId,
        );

        return [
            'next'     => $this->recordUrl($next, $queryParams),
            'previous' => $this->recordUrl($previous, $queryParams),
        ];
    }

    /**
     * Narrow $qb by every declared filter that carries a value.
     *
     * @param array<string, mixed> $params
     */
    private function applySchemaFilters(QueryBuilder $qb, string $alias, array $params): void
    {
        // Scoping rides along here for the same reason the schema filters do:
        // every query that must agree — the page, the count, the export, the
        // prev/next navigation — passes through this method, and a scope
        // applied to only some of them advertises rows that cannot be opened.
        $this->resource->scopeQuery($qb, $this->user);

        $values = $params['filters'] ?? [];

        foreach ($this->resource->tableSchema()?->declaredFilters() ?? [] as $filter) {
            $filter->apply($qb, $alias, $values[$filter->key()] ?? null);
        }

        $this->applyConstraints($qb, $alias, $params);
    }

    /**
     * Apply the user-composed conditions, ANDed together.
     *
     * A condition naming a field the schema does not declare is dropped — the
     * request chooses among declared constraints, it cannot invent one.
     *
     * @param array<string, mixed> $params
     */
    private function applyConstraints(QueryBuilder $qb, string $alias, array $params): void
    {
        $declared = [];

        foreach ($this->resource->tableSchema()?->declaredConstraints() ?? [] as $constraint) {
            $declared[$constraint->key()] = $constraint;
        }

        if ($declared === []) {
            return;
        }

        foreach (($params['constraints'] ?? []) as $index => $condition) {
            if (!is_array($condition)) {
                continue;
            }

            $constraint = $declared[$condition['key'] ?? ''] ?? null;

            if ($constraint instanceof Constraint) {
                $constraint->apply($qb, $alias, $condition, (int)$index);
            }
        }
    }

    /**
     * Turn relationship-backed filter choices into a flat option list.
     *
     * Done here rather than in the schema because it needs the database, and
     * a TableSchema is meant to stay a pure value object.
     */
    /**
     * Decide which row and bulk actions this viewer is actually offered.
     *
     * A resource that declares none gets the standard trio, derived from
     * whether the routes exist — the same rule the generic Resource/Index
     * page applied in markup, moved to where the routes and the permissions
     * both live. A resource that declares its own keeps them, minus the ones
     * this viewer may not perform: gating in the schema means a page cannot
     * offer what the server would refuse.
     */
    private function resolveActions(TableSchema $schema, string $key): TableSchema
    {
        $mayEdit   = $this->resource->canEdit(null, $this->user);
        $mayDelete = $this->resource->canDelete(null, $this->user);

        $actions = $schema->declaredActions();

        if ($actions === []) {
            // Nothing declared: derive the trio from the routes that exist.
            // Route existence is the resource's answer to "what can be done
            // here" only when it has given no other answer.
            $actions = $this->defaultRowActions(
                $key,
                $mayEdit && $this->routeExists($key . '_edit'),
                $mayDelete && $this->routeExists($key . '_destroy'),
            );
        } else {
            // A declared action names its own URL, so the route behind it is
            // the resource's business — gating on a `{key}_destroy` name here
            // silently dropped Delete from every listing whose controller
            // named it something else. Permission is the only question left.
            $canEdit   = $mayEdit;
            $canDelete = $mayDelete;

            $actions = array_values(array_filter(
                $actions,
                static fn (RowAction $action): bool => match ($action->name()) {
                    'edit' => $canEdit,
                    // Restore rides the delete permission: both govern the
                    // same trash lifecycle, and offering one without the
                    // other strands a record where the viewer put it.
                    'delete', 'restore' => $canDelete,
                    default => true,
                },
            ));
        }

        $bulkActions = $schema->declaredBulkActions();

        if ($bulkActions === [] && $mayDelete && ($bulkUrl = $this->routeUrl($key . '_bulk_destroy')) !== null) {
            $bulkActions = [BulkAction::delete($bulkUrl)];
        }

        return $schema->withActions($actions, $bulkActions);
    }

    /**
     * View / Edit / Delete, each only when its route exists.
     *
     * @return list<RowAction>
     */
    private function defaultRowActions(string $key, bool $canEdit, bool $canDelete): array
    {
        $actions = [];

        if ($this->routeExists($key . '_show')) {
            $actions[] = RowAction::view();
        }

        if ($canEdit && ($edit = $this->routeTemplate($key . '_edit')) !== null) {
            $actions[] = RowAction::edit($edit);
        }

        if ($canDelete && ($destroy = $this->routeTemplate($key . '_destroy')) !== null) {
            $delete = RowAction::delete($destroy);

            // Consequences instead of a blind guarantee, when the resource has
            // somewhere to ask.
            if (($preview = $this->routeTemplate($key . '_delete_preview')) !== null) {
                $delete = $delete->previewUrl($preview);
            }

            $actions[] = $delete;
        }

        return $actions;
    }

    /**
     * A route as a URL template with `{id}` where its uuid goes.
     *
     * Generated with a sentinel and substituted rather than string-built: the
     * router is the authority on where a route lives, and a hand-built path
     * was already wrong once for `users_export`.
     */
    private function routeTemplate(string $name): ?string
    {
        $sentinel = '00000000-0000-4000-8000-000000000000';

        try {
            $url = $this->urlGenerator->generate($name, ['uuid' => $sentinel]);
        } catch (\Throwable) {
            return null;
        }

        return str_replace($sentinel, '{id}', $url);
    }

    /** A route with no parameters, or null when it does not exist. */
    private function routeUrl(string $name): ?string
    {
        try {
            return $this->urlGenerator->generate($name);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveFilterOptions(TableSchema $schema): TableSchema
    {
        $filters = array_map(function (Filter $filter): Filter {
            $relation = $filter->relation();

            if ($relation === null) {
                return $filter;
            }

            // Bounded, the same way form relations are: a dropdown shipped the
            // whole table before this, so a large related table made every
            // listing page carry it. One row beyond the threshold is fetched
            // to tell "exactly full" from "there are more" — the overflow is
            // reported rather than silently trimmed, per the panel's rule that
            // a bound it imposes must be visible.
            $rows = $this->entityManager->createQueryBuilder()
                ->select(sprintf('r.%s AS value, r.%s AS label', $relation->valueField, $relation->labelField))
                ->from($relation->entityClass, 'r')
                ->orderBy(sprintf('r.%s', $relation->labelField), 'ASC')
                ->setMaxResults(RelationOptions::AUTO_SEARCH_THRESHOLD + 1)
                ->getQuery()
                ->getArrayResult();

            $truncated = count($rows) > RelationOptions::AUTO_SEARCH_THRESHOLD;

            if ($truncated) {
                $rows = array_slice($rows, 0, RelationOptions::AUTO_SEARCH_THRESHOLD);
            }

            return $filter->withResolvedOptions(array_values(array_map(
                static fn (array $row): array => [
                    'value' => (string)$row['value'],
                    'label' => (string)$row['label'],
                ],
                $rows
            )), $truncated);
        }, $schema->declaredFilters());

        return $schema->withFilters($filters);
    }

    /**
     * Break ties on the primary key, in the same direction as the sort.
     *
     * Without this the listing's order is undefined whenever the sort field
     * has duplicates, while findAdjacent() compares on (field, id). The two
     * then disagree: stepping "next" from the first row can find nothing, or
     * jump somewhere the user never saw. Deterministic ordering is also just
     * correct — an arbitrary order across pages loses and repeats rows.
     */
    private function applyKeysetTiebreak(QueryBuilder $qb, string $alias, string $direction): void
    {
        foreach ($qb->getDQLPart('orderBy') as $orderBy) {
            if (str_contains((string)$orderBy, "{$alias}.id")) {
                return;
            }
        }

        $qb->addOrderBy("{$alias}.id", $direction);
    }

    /**
     * Cluster rows by the active group.
     *
     * The group ordering has to come *first*, ahead of whatever the list query
     * ordered by, or rows for one group would be scattered through the page.
     *
     * @param array<string, mixed> $params
     */
    private function applyGrouping(QueryBuilder $qb, string $alias, array $params): void
    {
        $group = $this->resource->tableSchema()?->group($params['group'] ?? null);

        if (!$group instanceof Group) {
            return;
        }

        $existing = $qb->getDQLPart('orderBy');

        // $field comes from the schema, never the request.
        $field = "{$alias}.{$group->field()}";
        $qb->resetDQLPart('orderBy');
        $qb->orderBy($field, 'ASC');

        foreach ($existing as $orderBy) {
            // A sort on the group's own field is already expressed by the
            // grouping; repeating it is redundant everywhere and refused by
            // SQL Server, which requires ORDER BY columns to be unique.
            if (str_contains((string) $orderBy, $field)) {
                continue;
            }

            $qb->addOrderBy($orderBy);
        }
    }

    /**
     * Compute every declared column summary in a single aggregate query over
     * the filtered set.
     *
     * One query rather than one per summary — a footer with four aggregates
     * should not cost four round trips.
     *
     * @param array<string, mixed> $params
     * @return array<string, list<array{type: string, label: string, value: float|int|null}>>
     */
    /**
     * Whether a named route is registered, asked by trying to build a URL for
     * it. The router keeps no cheap "has route" API on the generator, and the
     * three lookups per render are trivial next to the listing's queries.
     */
    /**
     * Every record the current filters match, unpaginated.
     *
     * For exports, which must answer "what I am currently looking at" rather
     * than "everything in the table". Deliberately built from the same query
     * and the same {@see applySchemaFilters()} the page and its count use —
     * an export with its own idea of the filters is worse than no export,
     * because the file looks plausible.
     *
     * Unpaginated by design: the point is the whole result set, not the page.
     * Scoping applies here too, so an export can never widen what a user may
     * see.
     *
     * When $uuids is given the result is narrowed to those records — still
     * through the scope, so a hand-crafted list of ids cannot reach a row the
     * viewer may not see.
     *
     * @param list<string>|null $uuids
     * @return list<object>
     */
    public function allMatching(?array $uuids = null): array
    {
        $queryParams = $this->request->getQueryParams();
        $params      = $this->resource->parseListParams($queryParams);
        $params['filters'] = $this->resource->filterValues($params, $queryParams);

        $alias = $this->resource->queryAlias();
        $query = $this->resource->buildListQuery($params, null, null);

        $qb = $query->apply($this->repository()->createQueryBuilder($alias));
        $this->applySchemaFilters($qb, $alias, $params);

        if ($uuids !== null) {
            // Canonicalized the way the repositories do it, so a malformed id
            // is dropped rather than thrown — one bad value in a selection
            // should not fail the export.
            $canonical = [];

            foreach ($uuids as $uuid) {
                try {
                    $canonical[] = \Ramsey\Uuid\Uuid::fromString($uuid)->toString();
                } catch (\InvalidArgumentException) {
                    continue;
                }
            }

            if ($canonical === []) {
                return [];
            }

            $qb->andWhere("{$alias}.uuid IN (:allMatchingUuids)")
                ->setParameter(
                    'allMatchingUuids',
                    array_unique($canonical),
                    \Doctrine\DBAL\ArrayParameterType::STRING,
                );
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Where this list's export lives, asked of the router rather than built
     * from the key — `users_export` sits at `/panel/export/users`, so a
     * string-built path was simply wrong for it.
     */
    private function exportUrl(string $key): ?string
    {
        try {
            return $this->urlGenerator->generate($key . '_export');
        } catch (\Throwable) {
            return null;
        }
    }

    private function routeExists(string $name): bool
    {
        try {
            $this->urlGenerator->generate($name, ['uuid' => '00000000-0000-4000-8000-000000000000']);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed> $params
     * @return array<string, list<array{type: string, label: string, value: float|null}>>
     */
    private function summaries(ListQueryInterface $query, string $alias, array $params): array
    {
        $schema = $this->resource->tableSchema();

        if ($schema === null) {
            return [];
        }

        $columns = array_filter(
            $schema->declaredColumns(),
            static fn (Column $column): bool => $column->summaries() !== []
        );

        if ($columns === []) {
            return [];
        }

        $qb = $query->forCount($this->repository()->createQueryBuilder($alias));
        $this->applySchemaFilters($qb, $alias, $params);

        $selects = [];
        $map     = [];

        foreach ($columns as $column) {
            // A dot path would need a join to aggregate over; fail loudly
            // rather than emitting DQL that references an unjoined alias.
            if (str_contains($column->field(), '.')) {
                throw new \LogicException(sprintf(
                    'Column "%s" summarises "%s", which traverses a relation. '
                    . 'Aggregates over related entities need an explicit join.',
                    $column->key(),
                    $column->field(),
                ));
            }

            foreach ($column->summaries() as $index => $summary) {
                $token = sprintf('s_%s_%d', preg_replace('/\W/', '_', $column->key()), $index);

                $selects[]   = $summary->expression("{$alias}.{$column->field()}") . " AS {$token}";
                $map[$token] = [$column->key(), $summary];
            }
        }

        /** @var array<string, mixed> $row */
        $row = $qb->select(implode(', ', $selects))->getQuery()->getSingleResult();

        $summaries = [];

        foreach ($map as $token => [$columnKey, $summary]) {
            $value = $row[$token] ?? null;

            $summaries[$columnKey][] = [
                'type'  => $summary->type(),
                'label' => $summary->label(),
                'value' => $value === null ? null : (float)$value,
            ];
        }

        return $summaries;
    }

    /** @return EntityRepository<object> */
    private function repository(): EntityRepository
    {
        return $this->entityManager->getRepository($this->resource->entityClass());
    }

    /**
     * @param array<string, string> $sort
     */
    private function formatSortParam(array $sort): string
    {
        if ($sort === []) {
            return '';
        }

        $field = array_key_first($sort);

        return $sort[$field] === 'DESC' ? "-{$field}" : $field;
    }

    /**
     * @param class-string<ListQueryInterface> $queryClass
     * @param array<string, string> $sort
     * @return array{0: string, 1: string}
     */
    private function resolveSortField(string $queryClass, array $sort): array
    {
        $default      = $queryClass::defaultSort();
        $defaultField = (string)array_key_first($default);

        if ($sort === []) {
            return [$defaultField, $default[$defaultField]];
        }

        $requested = (string)array_key_first($sort);
        $mapped    = $queryClass::mapSortField($requested);

        if ($mapped === null) {
            return [$defaultField, $default[$defaultField]];
        }

        return [$mapped, $sort[$requested]];
    }

    /**
     * The surrogate id keyset navigation breaks ties on.
     *
     * The tiebreak compares against `{alias}.id`, so an entity without an
     * integer id is a declaration error, not a value to coerce.
     */
    private function identifierOf(object $entity): int
    {
        $id = method_exists($entity, 'getId') ? $entity->getId() : null;

        if (!is_int($id)) {
            throw new \LogicException(sprintf(
                'Keyset navigation needs %s::getId() to return an int, got %s.',
                $entity::class,
                get_debug_type($id),
            ));
        }

        return $id;
    }

    private function findAdjacent(
        ListQueryInterface $query,
        string $alias,
        string $sortField,
        string $comparison,
        mixed $currentValue,
        int $currentId,
    ): ?object {
        $qb = $query->apply($this->repository()->createQueryBuilder($alias));
        $this->applySchemaFilters($qb, $alias, $this->navigationParams);

        // Match the listing's tiebreak, and in the direction we are stepping,
        // so this picks the *nearest* neighbour rather than an arbitrary one.
        $this->applyKeysetTiebreak($qb, $alias, $comparison === '>' ? 'ASC' : 'DESC');

        $qb->andWhere(
            "{$alias}.{$sortField} {$comparison} :currentValue"
            . " OR ({$alias}.{$sortField} = :currentValue AND {$alias}.id {$comparison} :currentId)"
        )
            ->setParameter('currentValue', $currentValue)
            ->setParameter('currentId', $currentId)
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    private function recordUrl(?object $entity, array $queryParams): ?string
    {
        if ($entity === null) {
            return null;
        }

        $url = $this->urlGenerator->generate(
            $this->resource->showRouteName(),
            $this->resource->recordRouteParams($entity)
        );

        return $queryParams === [] ? $url : $url . '?' . http_build_query($queryParams);
    }
}
