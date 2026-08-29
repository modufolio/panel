<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/**
 * A many-to-one relation, edited as a select.
 *
 * The declaration names the related entity through the `relation` option (an
 * {@see \Modufolio\Panel\Table\RelationOptions}, the same value object the table filters
 * use), and stays pure data — the option list is resolved against the database
 * by ResourceController at render time, and the submitted identifier is
 * resolved back to an entity before the setter runs. Neither happens here:
 * formFields() is also called by the route loader at boot, where touching the
 * database would be a bug.
 *
 * Renders as a lookup — type to search, the server answers — rather than a
 * native select. That is the default rather than an optimisation for large
 * relations: a dropdown is only usable while the list is short, so making the
 * control depend on how many rows happen to exist today means the field's
 * behaviour changes under the user as the data grows. One control, always
 * searchable, is what every relation gets.
 */
final class BelongsToType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'belongs-to';
    }

    public static function defaults(): array
    {
        return [];
    }
}
