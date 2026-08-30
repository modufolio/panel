<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/**
 * A repeater of plain rows, with no entity behind them.
 *
 * The counterpart to HasManyType for content that is *data on the record*
 * rather than a child collection: pricing cards on a page, FAQ entries,
 * testimonial quotes. Rows live wherever the record's own storage puts them
 * (a JSON array in a flat-file page's field, a JSON column on an entity) —
 * nothing here touches the ORM, so there is no association to name, no uuid
 * diffing and no orphan removal. Submitted rows simply replace stored rows,
 * in submitted order.
 *
 * Declared like HasManyType — the `fields` option carries the sub-field
 * declarations — and rendered by the same repeater component, which treats
 * the two identically: it edits rows of sub-fields either way, and only the
 * server-side handling of the submitted array differs.
 *
 * Kirby calls this field "structure", which is where the name comes from —
 * a blueprint ported from a Kirby site maps its structure fields onto this
 * one token for token.
 */
final class StructureType implements FieldTypeInterface
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
