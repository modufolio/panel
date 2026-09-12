<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Resource;

use Modufolio\Appkit\Inertia\Inertia;
use Modufolio\Panel\Http\JsonApiPaginationTrait;
use Modufolio\Panel\Query\ChainedListQuery;
use Modufolio\Panel\Query\DerivedListQuery;
use Modufolio\Panel\Query\ListQueryInterface;
use Modufolio\Panel\Routing\ResourceBaseUrl;
use Modufolio\Panel\Routing\Uuid;
use Modufolio\Panel\Table\Column;
use Modufolio\Panel\Table\ColumnGuesser;
use Modufolio\Panel\Table\Constraint;
use Modufolio\Panel\Table\Filter;
use Modufolio\Panel\Table\Group;
use Modufolio\Panel\Table\Summary;
use Modufolio\Panel\Metric\MetricCalculator;
use Modufolio\Panel\Table\TableSchema;
use Doctrine\ORM\EntityManagerInterface;
use Modufolio\Appkit\Security\User\UserInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Psr\Clock\ClockInterface;
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

    /** @var array<string, mixed> Parsed params in scope during navigationRecords(). */
    private array $navigationParams = [];

    private ?ResourceCapabilities $capabilities = null;

    public function __construct(
        private readonly PanelResource $resource,
        private readonly ServerRequestInterface $request,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ClockInterface $clock,
        /** Who is asking — the resource's Permissions decide what that means. Null when nobody is signed in. */
        private readonly ?UserInterface $user = null,
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

    public function render(): Inertia
    {
        $queryParams = $this->request->getQueryParams();
        $params      = $this->resource->parseListParams($queryParams);
        $params['filters'] = $this->resource->filterValues($params, $queryParams);
        $pagination  = $this->getJsonApiPagination($queryParams);

        $schema = $this->resource->table();
        $query  = $this->listQuery($params, $pagination['limit'], $pagination['offset'], $schema);

        // Which shape of this listing was asked for. A board is a different
        // query — grouped into columns, ordered by position, limited per
        // column — so the choice has to be resolved before the rows are read,
        // not after.
        $view  = $this->resource->viewFor($this->requestedView($queryParams));
        $board = $view->isBoard() ? $this->board($view, $query, $params) : null;

        if ($schema !== null) {
            $this->assertChildRelations($schema);
        }

        $alias      = $this->resource->queryAlias();
        $repository = $this->repository();

        $listQb = $query->apply($repository->createQueryBuilder($alias));
        $this->applySchemaFilters($listQb, $alias, $params, $schema);
        $this->applyGrouping($listQb, $alias, $params, $schema);

        [, $sortDirection] = $this->resolveSortField($query, $params['sort']);
        $this->applyKeysetTiebreak($listQb, $alias, $sortDirection);

        $listQuery = $listQb->getQuery();

        // A declared child is loaded for the whole page in one IN query, so
        // the presenter can read the collection without an N+1. Set on the
        // query rather than joined in the list query: a fetch-join under the
        // page's LIMIT would count child rows against it and shorten pages.
        foreach ($schema?->declaredChildren() ?? [] as $child) {
            $listQuery->setFetchMode($this->resource->entityClass(), $child->relationName(), ClassMetadata::FETCH_EAGER);
        }

        // A board has already read its own rows, per column. Running the
        // paginated query as well would be a second full read of the same
        // table for a page that never renders it.
        $entities = $board === null ? $listQuery->getResult() : [];

        // The count must see the same filters, or the pager advertises pages
        // that do not exist.
        $countQb = $query->forCount($repository->createQueryBuilder($alias));
        $this->applySchemaFilters($countQb, $alias, $params, $schema);

        $totalCount = (int)$countQb
            ->select("COUNT({$alias}.id)")
            ->getQuery()
            ->getSingleScalarResult();

        $key = $this->resource->key();

        $capabilities = $this->capabilities();
        $resolver     = new SchemaResolver(
            $capabilities,
            new ColumnGuesser($this->entityManager),
            new FilterOptionResolver($this->entityManager),
        );

        if ($schema !== null) {
            $schema = $resolver->resolve($schema, $params['filters']);
        }

        $verdicts = $capabilities->verdicts()->verdictsEach($entities, $rows = $this->resource->present($entities));

        return Inertia::render(
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
                    $rows,
                    $totalCount,
                    $pagination['page'],
                    $pagination['perPage'],
                    $key,
                    // Summaries belong with the data, not the schema: they
                    // change with every filter, whereas the schema does not.
                    // The verdicts sit beside the rows, keyed by id, so a row
                    // stays exactly what present() returned.
                    [
                        'summaries' => $this->summaries($query, $alias, $params, $schema),
                        'can'       => $verdicts['can'],
                        // Only for refusals the resource can explain: an
                        // action with a reason shows disabled, with the
                        // sentence as its tooltip, instead of vanishing.
                        ...($verdicts['why'] === [] ? [] : ['why' => $verdicts['why']]),
                    ],
                ),
                'stack' => $this->stack,
                // Numbers about the resource, above the list. Deliberately not
                // narrowed by the current filters: a metric describes the
                // resource, and a column's summary already describes the
                // filtered set. Absent entirely when none are declared, so a
                // listing that wants none pays for none.
                ...(($metrics = $this->metrics()) === [] ? [] : ['metrics' => $metrics]),
                // Lets the generic Resource/Index page configure itself:
                // which prop holds the rows, where the listing lives, which
                // DrawerStack slot a record renders into — and which write
                // actions to offer, derived from whether the routes exist
                // rather than from any extra declaration. A page written for
                // one resource knows all of this already and ignores it.
                'resource' => [
                    'key'        => $key,
                    // Asked of the router: a `->prefix('/admin')` resource
                    // lives under /admin, and every write URL the client
                    // builds hangs off this path.
                    'baseUrl'    => ResourceBaseUrl::resolve($this->urlGenerator, $key),
                    // Every URL the client would otherwise assemble from
                    // baseUrl, asked of the router instead: null where the
                    // route was not generated. Templates carry `{id}`.
                    'urls'       => $capabilities->urls(),
                    'drawerType' => $this->resource->drawerType(),
                    // The heading and the singular, from the resource: what
                    // the client used to humanise from the key on its own.
                    'title'      => $this->resource->title(),
                    'label'      => $this->resource->label(),
                    // Both must hold: the route has to exist *and* this user
                    // has to be allowed. Route existence alone told the client
                    // what the resource supports, not what the viewer may do.
                    // {@see ResourceCapabilities} asks the pair.
                    'canCreate'  => $capabilities->create(),
                    'canEdit'    => $capabilities->edit(),
                    'canDelete'  => $capabilities->delete(),
                    // Null when the resource has no generated export route, in
                    // which case ExportButton falls back to its client-side
                    // path — which can only ever see the loaded page.
                    'exportUrl'  => $capabilities->url('export'),
                    // The switcher's options and which one is showing. A
                    // resource declaring only the table sends a single entry,
                    // and the client renders no switcher for one option.
                    'views'      => array_map(
                        static fn (ResourceView $declared): array => $declared->toArray(),
                        $this->resource->views(),
                    ),
                    'view'       => $view->key(),
                    // Whether cards on a board can be dragged. Deliberately
                    // not `canEdit` — see {@see ResourceCapabilities::move()}.
                    'canMove'    => $capabilities->move(),
                ],
                ...($board !== null ? ['board' => $board] : []),
                ...($schema !== null ? ['table' => $resolver->serialise($schema, $query)] : []),
                ...$this->extraProps,
            ],
        );
    }

    /**
     * The columns each card may be moved to, keyed by the card's id.
     *
     * Asked of {@see Permissions::move()}, which is the same predicate
     * the move endpoint enforces — so a button is offered exactly when the
     * move behind it would be allowed. A board deriving its buttons from a map
     * of its own is how the buttons and the rules drift apart.
     *
     * Empty unless the view asks for it: this is one call per card per column,
     * cheap for an in-memory rule and not something to spend where no button
     * will be rendered.
     *
     * @param  array<int, object>               $entities
     * @param  array<int, array<string, mixed>> $presented
     * @return array<string, list<array<string, mixed>>>
     */
    private function quickMoves(ResourceView $view, string $from, array $entities, array $presented): array
    {
        if (!$view->offersQuickMove()) {
            return [];
        }

        $moves       = [];
        $permissions = $this->resource->permissions();

        foreach ($entities as $index => $entity) {
            $id = (string) ($presented[$index]['id'] ?? '');

            if ($id === '') {
                continue;
            }

            $targets = [];

            foreach ($view->columnDefinitions() as $target) {
                if ($target['value'] === $from) {
                    continue;
                }

                if ($permissions->move($entity, $target['value'], $this->user) === true) {
                    $targets[] = $target;
                }
            }

            if ($targets !== []) {
                $moves[$id] = $targets;
            }
        }

        return $moves;
    }

    /**
     * The view key `?view=` asks for, if it asks for one at all.
     *
     * @param array<string, mixed> $queryParams
     */
    private function requestedView(array $queryParams): ?string
    {
        $requested = $queryParams['view'] ?? null;

        return is_string($requested) && $requested !== '' ? $requested : null;
    }

    /**
     * A board: one query per declared column, each ordered by position.
     *
     * Per column rather than one query over everything, because a board pages
     * by column. A single LIMIT across a grouped result cuts columns off at
     * arbitrary points — the fifth column would arrive empty not because it is
     * empty but because the first four used up the page.
     *
     * Columns come from the declaration, so a column with no cards is still
     * rendered. Growing them from the rows present would hide an empty "Done",
     * which is exactly the column whose emptiness is worth seeing.
     *
     * @param  array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function board(ResourceView $view, ListQueryInterface $query, array $params): array
    {
        $alias      = $this->resource->queryAlias();
        $repository = $this->repository();
        $groupBy    = (string) $view->groupBy();
        $position   = $view->positionField();

        $columns = [];

        foreach ($view->columnDefinitions() as $column) {
            // forCount() carries the query's filters without its sort or its
            // pagination — which is exactly a board column's starting point,
            // since the column supplies both itself.
            $qb = $query->forCount($repository->createQueryBuilder($alias));
            $this->applySchemaFilters($qb, $alias, $params);

            $qb->andWhere("{$alias}.{$groupBy} = :boardColumn")
                ->setParameter('boardColumn', $column['value']);

            $total = (int) (clone $qb)
                ->select("COUNT({$alias}.id)")
                ->getQuery()
                ->getSingleScalarResult();

            if ($position !== null) {
                $qb->orderBy("{$alias}.{$position}", 'ASC');
            }

            // Ties are broken by identity so a column's order is stable across
            // reloads. Two cards sharing a position is a real state — imported
            // rows, or an older integer scheme — and without this they would
            // swap places between requests for no visible reason.
            $qb->addOrderBy("{$alias}.id", 'ASC');

            $cards     = $qb->setMaxResults($view->columnLimit())->getQuery()->getResult();
            $presented = $this->resource->present($cards);

            $columns[] = [
                ...$column,
                'total' => $total,
                'cards' => $presented,
                // Which other columns each card may move to, asked of the
                // resource per card. Sent alongside the cards rather than
                // inside them, so `cards` stays exactly what present()
                // returned and no reserved key has to be carved out of it.
                'moves' => $this->quickMoves($view, $column['value'], $cards, $presented),
                // Same shape as a table page's meta.can: what this viewer may
                // do with each card, keyed by id, beside the cards.
                'can'   => $this->capabilities()->verdicts()->canEach($cards, $presented),
            ];
        }

        return ['view' => $view->toArray(), 'columns' => $columns];
    }

    /**
     * Previous/next record URLs for arrow-key traversal inside a drawer.
     *
     * @return array{next: string|null, previous: string|null}
     */
    public function navigationUrls(object $entity): array
    {
        $queryParams = $this->request->getQueryParams();
        $neighbours  = $this->navigationRecords($entity);

        return [
            'next'     => $this->recordUrl($neighbours['next'], $queryParams),
            'previous' => $this->recordUrl($neighbours['previous'], $queryParams),
        ];
    }

    /**
     * The records either side of this one in the listing's own order.
     *
     * Separate from {@see navigationUrls()} because a drawer is not the only
     * place a neighbour is reachable from: the edit page links to its
     * neighbours' *edit* routes, and deciding that needs the record — the
     * edit URL is a conjunction of the route existing and this viewer being
     * allowed to edit that particular record, which a finished URL has
     * already thrown away.
     *
     * @return array{next: object|null, previous: object|null}
     */
    public function navigationRecords(object $entity): array
    {
        $queryParams = $this->request->getQueryParams();
        $params      = $this->resource->parseListParams($queryParams);
        $params['filters'] = $this->resource->filterValues($params, $queryParams);

        // findAdjacent() builds its own query builders, so stash the parsed
        // params for applySchemaFilters() rather than threading them through.
        $this->navigationParams = $params;

        $query = $this->listQuery($params, null, null);

        [$sortField, $sortDirection] = $this->resolveSortField($query, $params['sort']);

        $alias        = $this->resource->queryAlias();
        $currentValue = $this->resource->sortValue($entity, $sortField);
        $currentId    = $this->identifierOf($entity);

        // $sortField comes from the list query's hardcoded allowlist, so it is
        // safe to interpolate; the compared values stay bound parameters.
        $forward  = $sortDirection === 'DESC' ? '<' : '>';
        $backward = $sortDirection === 'DESC' ? '>' : '<';

        $next = $this->findAdjacent(
            $query,
            $alias,
            $sortField,
            $forward,
            $currentValue,
            $currentId,
        );

        // Walking backwards means reversing the sort the listing *actually*
        // applies — the resolved field and direction, not the request's own
        // sort param. Those differ whenever the request names a field the
        // query cannot sort on: the query drops it in favour of its default,
        // and reversing the dropped entry reversed nothing — so "previous"
        // ran ascending and returned the first row below rather than the
        // nearest. Resolving first means the two directions always describe
        // the same order.
        $reversedSort = [$sortField => $sortDirection === 'ASC' ? 'DESC' : 'ASC'];

        $previous = $this->findAdjacent(
            $this->listQuery([...$params, 'sort' => $reversedSort], null, null),
            $alias,
            $sortField,
            $backward,
            $currentValue,
            $currentId,
        );

        return [
            'next'     => $next,
            'previous' => $previous,
        ];
    }

    /**
     * Refuse a child table whose relation the entity does not map the way a
     * child needs — at render time, where the metadata is, rather than in the
     * browser as a blank nested table.
     *
     * One-to-many only, for now: Doctrine batch-loads an eager one-to-many
     * for the page in a single query, while a many-to-many would load once
     * per parent row, and a bound the panel imposes must be visible.
     */
    private function assertChildRelations(TableSchema $schema): void
    {
        $children = $schema->declaredChildren();

        if ($children === []) {
            return;
        }

        $entityClass = $this->resource->entityClass();
        $meta        = $this->entityManager->getClassMetadata($entityClass);

        foreach ($children as $child) {
            $relation = $child->relationName();

            if (!$meta->hasAssociation($relation)) {
                throw new \LogicException(sprintf(
                    'TableSchema children name "%s", but %s maps no such association.',
                    $relation,
                    $entityClass,
                ));
            }

            if (!$meta->isCollectionValuedAssociation($relation)) {
                throw new \LogicException(sprintf(
                    'TableSchema children name "%s", but %s maps it as a to-one association; a child table lists a collection.',
                    $relation,
                    $entityClass,
                ));
            }

            if (($meta->getAssociationMapping($relation)['type'] & ClassMetadata::MANY_TO_MANY) !== 0) {
                throw new \LogicException(sprintf(
                    'TableSchema children name "%s", but %s maps it many-to-many, which would load once per row; '
                    . 'a child table lists a mapped-by one-to-many.',
                    $relation,
                    $entityClass,
                ));
            }
        }
    }

    /**
     * Narrow $qb by every declared filter that carries a value.
     *
     * @param array<string, mixed> $params
     */
    private function applySchemaFilters(QueryBuilder $qb, string $alias, array $params, ?TableSchema $schema = null): void
    {
        $schema ??= $this->resource->table();

        // Scoping rides along here for the same reason the schema filters do:
        // every query that must agree — the page, the count, the export, the
        // prev/next navigation — passes through this method, and a scope
        // applied to only some of them advertises rows that cannot be opened.
        $this->resource->permissions()->scope($qb, $alias, $this->user);

        $values = $params['filters'] ?? [];

        foreach ($schema?->declaredFilters() ?? [] as $filter) {
            $filter->apply($qb, $alias, $values[$filter->key()] ?? null);
        }

        $this->applyConstraints($qb, $alias, $params, $schema);
    }

    /**
     * Apply the user-composed conditions, ANDed together.
     *
     * A condition naming a field the schema does not declare is dropped — the
     * request chooses among declared constraints, it cannot invent one.
     *
     * @param array<string, mixed> $params
     */
    private function applyConstraints(QueryBuilder $qb, string $alias, array $params, ?TableSchema $schema = null): void
    {
        $schema ??= $this->resource->table();
        $declared = [];

        foreach ($schema?->declaredConstraints() ?? [] as $constraint) {
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
    private function applyGrouping(QueryBuilder $qb, string $alias, array $params, ?TableSchema $schema = null): void
    {
        $schema ??= $this->resource->table();
        $group = $schema?->group($params['group'] ?? null);

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
        $query = $this->listQuery($params, null, null);

        $qb = $query->apply($this->repository()->createQueryBuilder($alias));
        $this->applySchemaFilters($qb, $alias, $params);

        if ($uuids !== null) {
            // Canonicalized the way the repositories do it, so a malformed id
            // is dropped rather than thrown — one bad value in a selection
            // should not fail the export. The pattern is the one the routes
            // require, and lowercase is the form the column stores.
            $canonical = [];

            foreach ($uuids as $uuid) {
                if (preg_match('/^' . Uuid::PATTERN . '$/', $uuid) === 1) {
                    $canonical[] = strtolower($uuid);
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
     * @param  array<string, mixed> $params
     * @return array<string, list<array{type: string, label: string, value: float|null}>>
     */
    private function summaries(ListQueryInterface $query, string $alias, array $params, ?TableSchema $schema = null): array
    {
        $schema ??= $this->resource->table();

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

    /** This viewer's record-level verdicts, for the resource's own rules. */
    /**
     * The resource's declared metrics, computed against the same scope the
     * listing reads through.
     *
     * @return list<array<string, mixed>>
     */
    private function metrics(): array
    {
        if ($this->resource->metrics() === []) {
            return [];
        }

        return (new MetricCalculator($this->entityManager, $this->clock))->compute($this->resource, $this->user);
    }

    /**
     * What this viewer may do with the resource and where, asked once per
     * listing: the router's answers are memoised in it, and every button
     * the props offer reads from the same object.
     */
    public function capabilities(): ResourceCapabilities
    {
        return $this->capabilities ??= new ResourceCapabilities($this->resource, $this->urlGenerator, $this->user);
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
     * The list query for these params: the resource's class when it names
     * one, else derived from its table — and in either case with whatever
     * the resource's queries() chains on.
     *
     * @param array<string, mixed> $params
     */
    private function listQuery(array $params, ?int $limit, ?int $offset, ?TableSchema $schema = null): ListQueryInterface
    {
        $schema ??= $this->resource->table();

        $base = $this->resource->listQueryClass() !== null
            ? $this->resource->buildListQuery($params, $limit, $offset)
            : DerivedListQuery::fromTable(
                $schema,
                $this->entityManager->getClassMetadata($this->resource->entityClass()),
                $params,
                $limit,
                $offset,
            );

        $extras = $this->resource->queries($params);

        return $extras === [] ? $base : new ChainedListQuery($base, $extras);
    }

    /**
     * @param array<string, string> $sort
     * @return array{0: string, 1: string}
     */
    private function resolveSortField(ListQueryInterface $query, array $sort): array
    {
        $default      = $query->defaultOrder();
        $defaultField = (string)array_key_first($default);

        if ($sort === []) {
            return [$defaultField, $default[$defaultField]];
        }

        $requested = (string)array_key_first($sort);
        $mapped    = $query->mapSort($requested);

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
