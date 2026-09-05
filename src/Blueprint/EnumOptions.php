<?php

declare(strict_types=1);

namespace Modufolio\Panel\Blueprint;

/**
 * A backed enum's cases as select options.
 *
 * The label comes from `getLabel()` where the enum declares one — the
 * convention the host's enums follow — and from the case's value otherwise,
 * humanised. Used by the guesser for an `enumType` column, which is a choice
 * among the cases whatever the column's storage type says.
 */
final class EnumOptions
{
    /**
     * @param  class-string $enum
     * @return list<array{value: string, label: string}>
     */
    public static function for(string $enum): array
    {
        if (!is_a($enum, \BackedEnum::class, true)) {
            throw new \InvalidArgumentException(sprintf('%s is not a backed enum, so its cases have no column value.', $enum));
        }

        $options = [];

        foreach ($enum::cases() as $case) {
            $options[] = [
                'value' => (string) $case->value,
                'label' => method_exists($case, 'getLabel')
                    ? (string) $case->getLabel()
                    : ucfirst(str_replace(['_', '-'], ' ', (string) $case->value)),
            ];
        }

        return $options;
    }
}
