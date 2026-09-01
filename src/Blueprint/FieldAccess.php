<?php

declare(strict_types=1);

namespace Modufolio\Panel\Blueprint;

/**
 * Resolve per-field access declarations against the request's user and
 * record. Two verbs only — read and write — with hidden ≠ forbidden
 * enforced on both sides of the wire:
 *
 *  - a field the user may not read is removed from the serialized
 *    definitions entirely (never shipped, not merely not rendered), and
 *  - a field the user may not write renders read-only AND has its submitted
 *    value stripped, so disabling the input is presentation, not the guard.
 *
 * Callables receive `($user, $record)`, either possibly null (a create form
 * has no record yet).
 */
final class FieldAccess
{
    /**
     * @param list<array<string, mixed>>                          $blueprint
     * @param array<string, array{read?: callable, write?: callable}> $access
     *
     * @return list<array<string, mixed>> definitions minus unreadable fields,
     *                                    unwritable ones marked readonly
     */
    public static function resolve(array $blueprint, array $access, ?object $user = null, ?object $record = null): array
    {
        $resolved = [];

        foreach ($blueprint as $field) {
            $key = (string) ($field['key'] ?? '');
            $spec = $access[$key] ?? null;

            if (null !== $spec && isset($spec['read']) && !$spec['read']($user, $record)) {
                continue;
            }

            if (null !== $spec && isset($spec['write']) && !$spec['write']($user, $record)) {
                $field['props'] = ($field['props'] ?? []) + ['readonly' => true];
            }

            $resolved[] = $field;
        }

        return $resolved;
    }

    /**
     * Drop submitted values for every field the user may not write (or read
     * — an unreadable field is a fortiori unwritable).
     *
     * @param array<string, array{read?: callable, write?: callable}> $access
     * @param array<string, mixed>                                    $values
     *
     * @return array<string, mixed>
     */
    public static function stripDenied(array $access, array $values, ?object $user = null, ?object $record = null): array
    {
        foreach ($access as $key => $spec) {
            $deniedRead = isset($spec['read']) && !$spec['read']($user, $record);
            $deniedWrite = isset($spec['write']) && !$spec['write']($user, $record);

            if ($deniedRead || $deniedWrite) {
                unset($values[$key]);
            }
        }

        return $values;
    }
}
