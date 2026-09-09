<?php

declare(strict_types=1);

namespace Modufolio\Panel\Support;

/**
 * A readable label derived from a key, when nothing declared one.
 *
 * Two registers, and the split is deliberate rather than an accident of six
 * separate copies: a table, a drawer or a filter reads in sentence case —
 * "Unit cost", "Connected contact" — while a form's field labels read in
 * title case, "Unit Cost", which is the register {@see
 * \Modufolio\Panel\Blueprint\FormFieldGuesser} already generates for the
 * fields it guesses. Mixing the two inside one surface is what looks wrong,
 * so each surface asks for the one it uses and neither has to remember the
 * `preg_replace` that gets there.
 *
 * Separator runs collapse and the result is trimmed, so `first__name` and
 * `first-name ` land on the same label as `first_name`.
 */
final class Label
{
    /** Sentence case: the register tables, drawers, filters and views read in. */
    public static function sentence(string $key): string
    {
        return ucfirst(self::words($key));
    }

    /** Title case: the register a form's field labels read in. */
    public static function title(string $key): string
    {
        return ucwords(self::words($key));
    }

    /** The key's words, separators collapsed to single spaces. */
    private static function words(string $key): string
    {
        return trim(preg_replace('/[_\-]+/', ' ', $key) ?? $key);
    }
}
