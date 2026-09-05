<?php

declare(strict_types=1);

namespace Modufolio\Panel\Blueprint;

use Modufolio\Panel\Resource\Permissions;

/**
 * Apply a resource's field permissions to a serialised form, on both sides
 * of the wire. Two verbs only — read and write — with hidden ≠ forbidden:
 *  - a field the user may not read is removed from the serialised
 *    definitions entirely (never shipped, not merely not rendered), and
 *  - a field the user may not write renders disabled AND has its submitted
 *    value stripped, so the disabled input is presentation, not the guard.
 *
 * The rules themselves live on {@see Permissions::readable()} and
 * {@see Permissions::writable()}; this is only where they meet the form.
 */
final class FieldAccess
{
    /**
     * @param  list<array<string, mixed>> $fields
     * @return list<array<string, mixed>> definitions minus unreadable fields,
     *                                    unwritable ones marked disabled
     */
    public static function resolve(array $fields, Permissions $permissions, ?object $user = null, ?object $record = null): array
    {
        $resolved = [];

        foreach ($fields as $field) {
            $key = (string) ($field['key'] ?? '');

            if ($key === '') {
                // A separator: nothing to read or write.
                $resolved[] = $field;

                continue;
            }

            if (!$permissions->readable($key, $user, $record)) {
                continue;
            }

            if (!$permissions->writable($key, $user, $record)) {
                $props = is_array($field['props'] ?? null) ? $field['props'] : [];

                // `disabled`, not `readonly`: every field component honours
                // the former, only the text inputs the latter.
                $field['props'] = [...$props, 'disabled' => true];
            }

            $resolved[] = $field;
        }

        return $resolved;
    }

    /**
     * Drop submitted values for every field the user may not write (or read
     * — an unreadable field is a fortiori unwritable).
     *
     * @param  list<array<string, mixed>> $fields
     * @param  array<string, mixed>       $values
     * @return array<string, mixed>
     */
    public static function stripDenied(array $fields, Permissions $permissions, array $values, ?object $user = null, ?object $record = null): array
    {
        foreach ($fields as $field) {
            $key = (string) ($field['key'] ?? '');

            if ($key === '' || !array_key_exists($key, $values)) {
                continue;
            }

            if (!$permissions->readable($key, $user, $record) || !$permissions->writable($key, $user, $record)) {
                unset($values[$key]);
            }
        }

        return $values;
    }
}
