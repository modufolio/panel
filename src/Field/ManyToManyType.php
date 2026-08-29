<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/**
 * A many-to-many relation, edited as a multiselect.
 *
 * Same discipline as {@see BelongsToType}: the declaration names the related
 * entity through a `RelationOptions` and stays pure data — the option list is
 * resolved by ResourceController at render time, and the submitted identifier
 * *array* is resolved back to entities and synced onto the owning collection
 * (add what is new, drop what is gone; the join table follows).
 *
 * The field key is the association property itself (`tags`, not `tags_id`) —
 * there is no foreign key column to allude to.
 */
final class ManyToManyType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'multiselect';
    }

    public static function defaults(): array
    {
        return [];
    }
}
