<?php

declare(strict_types=1);

namespace Modufolio\Panel\Field;

/**
 * A field type that cannot render meaningfully without certain options —
 * the builder refuses the declaration when they are missing, so the mistake
 * surfaces where the blueprint is written rather than as a blank control.
 */
interface RequiresOptionsInterface
{
    /**
     * @return list<string> option names add() must receive for this type
     */
    public static function requiredOptions(): array;
}
