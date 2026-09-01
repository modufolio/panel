<?php

declare(strict_types=1);

namespace Modufolio\Panel\Field;

/**
 * A named group of sub-fields stored as one value object — "SEO" with
 * title/description/image, "Open Graph" overrides. The single-row sibling
 * of StructureType: same `fields` option, same sub-field declarations, but
 * the value is one object rather than a list of rows.
 *
 * Kirby calls the concept "object", Bolt calls it "set" — the shorter name
 * won.
 */
final class SetType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'set';
    }

    public static function defaults(): array
    {
        return [];
    }
}
