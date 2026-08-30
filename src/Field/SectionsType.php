<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/**
 * Full-width content sections, each split into fraction-width columns of
 * builder content — the page-composition tier above BuilderType, under
 * Modufolio's own historical name for a content row.
 *
 * The value is a list of sections:
 *
 *     [{ id, attrs: {…}, columns: [{ id, width: "1/2", content: {ProseMirror doc} }] }]
 *
 * Declared with the two option slots the builder already carries:
 *  - `options` — the allowed column patterns, e.g. ['1/1', '1/2, 1/2', '1/3, 2/3'].
 *    A pattern is comma-separated fractions summing to one row.
 *  - `fields`  — the per-section settings form (class, spacing, fullwidth, …),
 *    ordinary sub-field declarations exactly as StructureType uses them;
 *    submitted values land in the section's `attrs`.
 *
 * Independent implementation of a widely shared concept (Kirby's layout
 * field, ACF's flexible content, Craft's Matrix all express it); the column
 * content here is this panel's own ProseMirror document, not their blocks.
 */
final class SectionsType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'sections';
    }

    public static function defaults(): array
    {
        return [
            'width' => 'full',
            // One full-width row is always a valid composition; blueprints
            // override with their own pattern list.
            'options' => ['1/1'],
        ];
    }
}
