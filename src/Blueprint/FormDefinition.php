<?php

declare(strict_types=1);

namespace Modufolio\Panel\Blueprint;

/**
 * A resource form as the two things a request needs from it: the field
 * definitions that travel to the client, and the per-field access callables
 * that must not.
 *
 * They were one array once, and the closures had to be dropped before the
 * definitions could be serialised — so the guesser dropped them at the source
 * and `access` reached nobody. Keeping them side by side in one object lets a
 * caller serialise `fields` and still hold `access` for
 * {@see FieldAccess::stripDenied()}.
 */
final readonly class FormDefinition
{
    /**
     * @param list<array<string, mixed>>                            $fields
     * @param array<string, array{read?: callable, write?: callable}> $access
     */
    public function __construct(
        public array $fields,
        public array $access = [],
    ) {
    }
}
