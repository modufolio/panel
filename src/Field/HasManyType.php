<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/**
 * A one-to-many child collection, edited inline as a repeater.
 *
 * The declaration names only the association property (the field key) and the
 * sub-fields to edit — nothing else. The child entity class, the inverse side
 * and orphan removal all come from Doctrine's own association mapping, asked
 * for by ResourceController at submit time. Declaring any of that here would
 * be a second copy of what the ORM already knows (the same reasoning
 * JsonApiQueryBuilder follows when it walks ClassMetadata instead of a config).
 *
 * Rows are diffed by the child's uuid: a submitted row with a known id updates
 * that child in place, a row without one becomes a new child, and children
 * missing from the submission are removed — which orphanRemoval turns into a
 * delete. Row order is the submitted order, persisted to a `position` field
 * when the child has one.
 */
final class HasManyType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'repeater';
    }

    public static function defaults(): array
    {
        return ['width' => 'full'];
    }
}
