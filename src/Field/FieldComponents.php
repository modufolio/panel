<?php

declare(strict_types=1);

namespace Modufolio\Panel\Field;

/**
 * Which client components a form's fields need, and which are missing.
 *
 * A field type names its component ({@see FieldTypeInterface::component()});
 * `@modufolio/panel` ships the components in {@see self::BUILT_IN}, and a host
 * registers the rest at boot (`createPanel({ fields: { image: … } })`). A type
 * whose component nobody registers renders as an error in the form. That is
 * discoverable at run time; this makes it checkable before — a lint over every
 * resource's form, with the host's registrations as the second list.
 *
 * BUILT_IN is pinned to `ui/src/Components/Fields/fieldTypes.json` by a test on
 * each side of the boundary, so the two registries cannot drift apart quietly.
 */
final class FieldComponents
{
    /** Components the client package ships. Sorted; keep in step with fieldTypes.json. */
    public const BUILT_IN = [
        'belongs-to',
        'checkbox',
        'color',
        'data',
        'date',
        'date-range',
        'datetime',
        'embed',
        'file',
        'hidden',
        'multiselect',
        'range',
        'repeater',
        'select',
        'separator',
        'set',
        'tags',
        'text',
        'textarea',
        'time',
        'toggle',
        'toggle-buttons',
    ];

    /**
     * Component names the fields use that are neither built in nor registered.
     *
     * @param list<array<string, mixed>> $fields     serialised field definitions, as FormResolver::fieldsFor() returns them
     * @param list<string>               $registered components the host registers at boot
     *
     * @return list<string> unique, in first-use order
     */
    public static function missing(array $fields, array $registered = []): array
    {
        $known = [...self::BUILT_IN, ...$registered];

        return array_values(array_filter(
            self::used($fields),
            static fn (string $component): bool => !in_array($component, $known, true),
        ));
    }

    /**
     * Every component name the fields use, sub-fields included — a set's
     * inputs and a repeater's row fields render through the registry too.
     *
     * @param list<array<string, mixed>> $fields
     *
     * @return list<string> unique, in first-use order
     */
    public static function used(array $fields): array
    {
        $used = [];

        foreach ($fields as $field) {
            if (is_string($field['type'] ?? null) && !in_array($field['type'], $used, true)) {
                $used[] = $field['type'];
            }

            if (is_array($field['fields'] ?? null)) {
                foreach (self::used(array_values($field['fields'])) as $sub) {
                    if (!in_array($sub, $used, true)) {
                        $used[] = $sub;
                    }
                }
            }
        }

        return $used;
    }
}
