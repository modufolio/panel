<?php

declare(strict_types=1);

namespace Modufolio\Panel\Form;

use Doctrine\ORM\EntityManagerInterface;
use Modufolio\Panel\Blueprint\FieldAccess;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\RelationOptionResolver;
use Modufolio\Panel\Routing\ResourceBaseUrl;
use Modufolio\Panel\Routing\RouteUrls;
use Modufolio\Panel\Table\RelationOptions;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * What a form page is sent: the field declarations as the client renders
 * them, and the record they edit.
 *
 * The declaration is pure data — it is also read at route-build time, where
 * the database must stay untouched — so everything that needs the database
 * happens here: relation declarations become option lists or search
 * endpoints, per-field access decides what is serialised at all, and computed
 * fields are filled from the accessor they name.
 */
final class FormPresenter
{
    private ?RelationOptionResolver $relations = null;

    public function __construct(
        private readonly FormResolver $forms,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Props both form pages share: the resource's self-description and the
     * fields this viewer may see, with the ones they may not change marked.
     *
     * @return array<string, mixed>
     */
    public function props(PanelResource $resource, ?object $record = null, ?object $user = null): array
    {
        return [
            'resource' => [
                'key'        => $resource->key(),
                'baseUrl'    => ResourceBaseUrl::resolve($this->urlGenerator, $resource->key()),
                // Where the form goes next, asked of the router: the record's
                // own update and destroy URLs when there is a record, null
                // for a route the resource did not generate.
                'urls'       => $this->urls($resource, $record),
                'drawerType' => $resource->drawerType(),
                'label'      => self::label($resource),
                // The route must exist *and* this viewer must be allowed to
                // delete this record — the same question the destroy endpoint
                // asks, so the button and the refusal cannot disagree.
                'canDelete'  => RouteUrls::exists($this->urlGenerator, $resource->key() . '_destroy')
                    && $resource->permissions()->delete($record, $user),
            ],
            'fields' => $this->fields($resource, $record, $user),
            // The tabs and fieldsets the client draws; empty for a flat form.
            'layout' => $resource->form()?->layout() ?? ['tabs' => [], 'fieldsets' => []],
        ];
    }

    /**
     * The fields the client renders for this viewer and record.
     *
     * A field this user may not read is gone entirely — not rendered but
     * hidden: never serialised. One they may not write comes back disabled,
     * while {@see SubmissionHandler} drops its value on write. Both
     * answers come from the resource's {@see Permissions}, asked per field
     * with the record in hand — so a rule about *this* record and a rule
     * about *this* user are the same kind of rule.
     *
     * @return list<array<string, mixed>>
     */
    public function fields(PanelResource $resource, ?object $record = null, ?object $user = null): array
    {
        return FieldAccess::resolve($this->resolvedFields($resource), $resource->permissions(), $user, $record);
    }

    /**
     * The declaration with every relation resolved for the client, before
     * any access is applied — what a drawer's addable tab builds its form
     * from.
     *
     * @return list<array<string, mixed>>
     */
    public function resolvedFields(PanelResource $resource): array
    {
        return $this->resolveOptions($this->forms->fieldsFor($resource), $resource);
    }

    /**
     * The presented record with every computed field filled from the
     * accessor it declares.
     *
     * A computed field has no column, so nothing arrives for it unless the
     * presenter happens to emit the same key. The declaration already names
     * its source; a named accessor the record does not implement is a
     * declaration bug, and says so.
     *
     * @param  array<string, mixed> $record
     * @return array<string, mixed>
     */
    public function record(PanelResource $resource, object $entity, array $record): array
    {
        foreach ($this->forms->fieldsFor($resource) as $field) {
            $accessor = $field['accessor'] ?? null;

            if (!is_string($accessor) || $accessor === '') {
                continue;
            }

            if (!method_exists($entity, $accessor)) {
                throw new \LogicException(sprintf(
                    '%s declares accessor "%s" for field "%s", but %s has no such method.',
                    $resource::class,
                    $accessor,
                    (string) ($field['key'] ?? '?'),
                    $entity::class,
                ));
            }

            $record[(string) ($field['key'] ?? '')] = $entity->{$accessor}();
        }

        return $record;
    }

    /** Human singular: 'movies' → 'Movie'. */
    public static function label(PanelResource $resource): string
    {
        return ucfirst($resource->drawerType());
    }

    /**
     * Turn each relation declaration into what the client needs, and nothing
     * it must not have: the {@see RelationOptions} names an entity class,
     * which is nobody's business, so it never travels.
     *
     * A to-one relation is always a lookup, searched as the user types; a
     * to-many becomes one once its list outgrows scrolling. The difference is
     * the control, not the data — a dropdown is only usable while the list is
     * short, so the server chooses the control the way it chooses the field
     * type. A repeater's sub-fields get the same treatment under dotted paths
     * (`cast.actor_id`), so a sub-key can never be mistaken for a top-level
     * field of the same name.
     *
     * @param  list<array<string, mixed>> $fields
     * @return list<array<string, mixed>>
     */
    private function resolveOptions(array $fields, PanelResource $resource, string $prefix = ''): array
    {
        return array_map(function (array $field) use ($resource, $prefix): array {
            $path = $prefix . (string) ($field['key'] ?? '');

            if (is_array($field['fields'] ?? null)) {
                $field['fields'] = $this->resolveOptions(FormResolver::subFields($field), $resource, $path . '.');
            }

            $relation = $field['relation'] ?? null;

            if (!$relation instanceof RelationOptions) {
                return $field;
            }

            $resolver = $this->relations();
            $isLookup = ($field['type'] ?? null) === 'belongs-to';

            unset($field['relation']);

            if (!$isLookup && !$resolver->isSearchable($relation)) {
                $field['options'] = $resolver->all($relation);

                return $field;
            }

            // The rows are not sent; the control asks as the user types, and
            // asks again by identifier for whatever it already holds.
            $props = is_array($field['props'] ?? null) ? $field['props'] : [];

            // Unless there is nothing to ask: a resource that generates no
            // form routes has no relation-options route, and asking the
            // generator for one that does not exist threw — which took down
            // the page that merely *showed* a record. The list travels with
            // the field instead, and the control filters it in the browser.
            $searchUrl = $this->url($resource->key() . '_relation_options', ['field' => $path]);

            $field['options'] = $searchUrl === null ? $resolver->all($relation) : [];
            $field['props']   = [
                ...$props,
                ...($searchUrl === null ? [] : ['searchUrl' => $searchUrl]),
                // The control renders its current value from these keys, and
                // its own search results arrive in the same shape.
                'valueKey'  => 'value',
                'labelKey'  => 'label',
                // An optional relation needs a way back to "no selection".
                'clearable' => ($field['required'] ?? false) !== true,
                // The "Create …" row, offered only when a name is all a new
                // row needs, and only where the POST that backs it exists.
                // The POST re-checks; this is the offer.
                'allowCreate' => $searchUrl !== null && $isLookup && $resolver->creatableFromLabel($relation),
            ];
            // A native <select> cannot search; the lookup can, and reads the
            // same option shape.
            $field['type'] = $isLookup ? 'belongs-to' : (string) ($field['type'] ?? 'multiselect');

            return $field;
        }, $fields);
    }

    private function relations(): RelationOptionResolver
    {
        return $this->relations ??= new RelationOptionResolver($this->entityManager);
    }

    /** Route existence, asked by trying to build a URL for it. */
    /**
     * @return array<string, string|null>
     */
    private function urls(PanelResource $resource, ?object $record): array
    {
        $key    = $resource->key();
        $params = $record !== null ? $resource->recordRouteParams($record) : null;

        return [
            'index'   => $this->url($key),
            'store'   => $this->url($key . '_store'),
            'update'  => $params !== null ? $this->url($key . '_update', $params) : null,
            'destroy' => $params !== null ? $this->url($key . '_destroy', $params) : null,
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function url(string $name, array $params = []): ?string
    {
        return RouteUrls::url($this->urlGenerator, $name, $params);
    }
}
