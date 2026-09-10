<?php

declare(strict_types=1);

namespace Modufolio\Panel\Inspection;

use Doctrine\ORM\EntityManagerInterface;
use Modufolio\Appkit\Security\User\UserInterface;
use Modufolio\Panel\Form\FormResolver;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\ResourceCapabilities;
use Modufolio\Panel\Resource\SchemaResolver;
use Modufolio\Panel\Table\Column;
use Modufolio\Panel\Table\ColumnGuesser;
use Modufolio\Panel\Table\Filter;
use Modufolio\Panel\Table\RowAction;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * The resolved table and form of a resource, with the layer that decided
 * each part written beside it — without a request.
 *
 * Nothing in this package is generated to disk: a column's label, type and
 * control are derived at render time from the column, the resource's
 * `fields()`, Doctrine's mapping, the routes and the viewer's permissions,
 * in that order of precedence. That keeps a resource a dozen lines, and it
 * means that when a column renders wrong there is no file to open — the
 * answer is somewhere in a precedence chain across four namespaces. This
 * is the file that would have been generated, produced on demand: the same
 * {@see SchemaResolver} the listing runs, over the same routes, for a
 * stand-in viewer, with each outcome annotated with its source.
 *
 * Modelled on {@see PermissionInspector}, which does the same for the
 * permission layers.
 *
 * @phpstan-import-type TableEntry from SchemaReport
 * @phpstan-import-type FieldEntry from SchemaReport
 */
final class SchemaInspector
{
    private readonly UrlGeneratorInterface $urls;

    /**
     * @param \Closure(class-string<PanelResource>): PanelResource $resources how the host builds a resource
     */
    public function __construct(
        RouteCollection $routes,
        private readonly \Closure $resources,
        private readonly FormResolver $forms,
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->urls = new UrlGenerator($routes, new RequestContext());
    }

    /**
     * @param class-string<PanelResource> $class
     * @param UserInterface|null          $user  the viewer to answer for; null is nobody signed in
     */
    public function inspect(string $class, ?UserInterface $user = null): SchemaReport
    {
        $resource     = ($this->resources)($class);
        $capabilities = new ResourceCapabilities($resource, $this->urls, $user);

        return new SchemaReport(
            key: $resource->key(),
            class: $resource::class,
            permissions: $resource->permissions()::class,
            capabilities: [
                'create' => $capabilities->create(),
                'edit'   => $capabilities->edit(),
                'delete' => $capabilities->delete(),
                'move'   => $capabilities->move(),
                'export' => $capabilities->export(),
            ],
            urls: $capabilities->urls(),
            table: $this->table($resource, $capabilities),
            form: $this->form($resource, $user),
        );
    }

    // ── Table ────────────────────────────────────────────────────────────────

    /**
     * @return TableEntry|null
     */
    private function table(PanelResource $resource, ResourceCapabilities $capabilities): ?array
    {
        $schema = $resource->table();

        if ($schema === null) {
            return null;
        }

        // What the declaration said, before anything fills it in. Read off
        // the same objects the resolver will mutate, so "declared" here
        // means exactly what the resolver's "do not override" checks mean.
        $declared = [];

        foreach ($schema->declaredColumns() as $column) {
            $declared[$column->key()] = [
                'label'    => $column->hasDeclaredLabel(),
                'type'     => $column->hasDeclaredType(),
                'options'  => $column->hasOptions() || $column->hasColors(),
                'editable' => $column->isEditable(),
                'typeWas'  => $column->currentType(),
            ];
        }

        $declaredRecordUrl = $schema->declaredRecordUrl();
        $declaredActions   = $schema->declaredActions() !== [];
        $fieldLabels       = $resource->fieldLabels();

        // The listing's own pipeline, minus the database step for filter
        // options: an option list is data, not schema, and reading a related
        // table is not what an inspection is for.
        $resolver = new SchemaResolver($capabilities, new ColumnGuesser($this->entityManager));
        $schema   = $resolver->resolve($schema);

        $columns = [];

        foreach ($schema->declaredColumns() as $column) {
            $was  = $declared[$column->key()];
            $type = $column->currentType();

            $columns[] = [
                'key'            => $column->key(),
                'label'          => $column->currentLabel(),
                'labelSource'    => $was['label'] ? 'column' : (isset($fieldLabels[$column->key()]) ? 'fields' : 'default'),
                'type'           => $type,
                'typeSource'     => $was['type'] ? 'column' : ($type !== $was['typeWas'] ? 'mapping' : 'default'),
                'options'        => $this->optionsSource($column, $was['options']),
                'editable'       => $column->isEditable(),
                'editableSource' => $was['editable'] ? ($column->isEditable() ? 'column' : 'permissions') : null,
            ];
        }

        $actions = array_map(static fn (RowAction $action): string => $action->name(), $schema->declaredActions());

        return [
            'columns'         => $columns,
            'recordUrl'       => $schema->declaredRecordUrl(),
            'recordUrlSource' => $declaredRecordUrl !== null ? 'table' : ($schema->declaredRecordUrl() !== null ? 'route' : null),
            'actions'         => $actions,
            'actionsSource'   => $declaredActions ? 'table' : 'route',
            'filters'         => array_map(static fn (Filter $filter): array => [
                'key'      => $filter->key(),
                'type'     => $filter->type(),
                'relation' => $filter->relation()?->entityClass,
            ], $schema->declaredFilters()),
        ];
    }

    private function optionsSource(Column $column, bool $declared): ?string
    {
        if (!$column->hasOptions() && !$column->hasColors()) {
            return null;
        }

        return $declared ? 'column' : 'mapping';
    }

    // ── Form ─────────────────────────────────────────────────────────────────

    /**
     * @return list<FieldEntry>|null
     */
    private function form(PanelResource $resource, ?UserInterface $user): ?array
    {
        $form = $resource->form();

        if ($form === null) {
            return null;
        }

        // What each layer said about each key, so the resolved field can be
        // traced back: the form entry's own options, then fields(), then the
        // guess. The same two-level merge the guesser performs.
        $entries = [];

        foreach ($form->entries() as [$key, $options]) {
            if (is_string($key)) {
                $entries[$key] = $options;
            }
        }

        $shared      = $resource->fieldDefinitions();
        $permissions = $resource->permissions();
        $fields      = [];

        foreach ($this->forms->fieldsFor($resource) as $field) {
            $key = (string) ($field['key'] ?? '');

            if ($key === '' || ($field['type'] ?? null) === 'separator') {
                continue;
            }

            $entry = $entries[$key] ?? [];
            $base  = $shared[$key] ?? [];

            $fields[] = [
                'key'         => $key,
                'label'       => (string) ($field['label'] ?? ''),
                'labelSource' => isset($entry['label']) ? 'form' : (isset($base['label']) ? 'fields' : 'default'),
                'type'        => (string) ($field['type'] ?? ''),
                'typeSource'  => isset($entry['type']) ? 'form' : (isset($base['type']) ? 'fields' : 'mapping'),
                'readable'    => $permissions->readable($key, $user),
                'writable'    => $permissions->writable($key, $user),
            ];
        }

        return $fields;
    }
}
