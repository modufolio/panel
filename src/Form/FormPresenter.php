<?php

declare(strict_types=1);

namespace Modufolio\Panel\Form;

use Doctrine\ORM\EntityManagerInterface;
use Modufolio\Panel\Blueprint\FieldAccess;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\RelationOptionResolver;
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
                'baseUrl'    => '/panel/' . $resource->key(),
                'drawerType' => $resource->drawerType(),
                'label'      => self::label($resource),
                'canDelete'  => $this->routeExists($resource->key() . '_destroy'),
            ],
            'fields' => $this->fields($resource, $record, $user),
        ];
    }

    /**
     * The fields the client renders for this viewer and record.
     *
     * A field this user may not read is gone entirely — not rendered but
     * hidden, not disabled: never serialised. One they may not write comes
     * back read-only, and one the resource freezes for this record is
     * disabled on the client while {@see SubmissionHandler} drops it on write.
     *
     * @return list<array<string, mixed>>
     */
    public function fields(PanelResource $resource, ?object $record = null, ?object $user = null): array
    {
        $readonly = $resource->readonlyFields($record, $user);

        return array_map(
            static function (array $field) use ($readonly): array {
                if (!in_array((string) ($field['key'] ?? ''), $readonly, true)) {
                    return $field;
                }

                $props = is_array($field['props'] ?? null) ? $field['props'] : [];

                return [...$field, 'props' => [...$props, 'disabled' => true]];
            },
            FieldAccess::resolve(
                $this->resolvedFields($resource),
                $this->forms->accessFor($resource),
                $user,
                $record,
            ),
        );
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

            $field['options'] = [];
            $field['props']   = [
                ...$props,
                'searchUrl' => $this->urlGenerator->generate(
                    $resource->key() . '_relation_options',
                    ['field' => $path],
                ),
                // The control renders its current value from these keys, and
                // its own search results arrive in the same shape.
                'valueKey'  => 'value',
                'labelKey'  => 'label',
                // An optional relation needs a way back to "no selection".
                'clearable' => ($field['required'] ?? false) !== true,
                // The "Create …" row, offered only when a name is all a new
                // row needs. The POST re-checks; this is the offer.
                'allowCreate' => $isLookup && $resolver->creatableFromLabel($relation),
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
    private function routeExists(string $name): bool
    {
        try {
            $this->urlGenerator->generate($name, ['uuid' => '00000000-0000-4000-8000-000000000000']);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
