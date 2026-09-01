<?php

declare(strict_types=1);

namespace Modufolio\Panel\Blueprint;

/**
 * Fill blank submitted values from the blueprint's declared defaults,
 * resolving the two time sentinels on the way:
 *
 *   'default' => '@now'    // date('Y-m-d H:i:s') at resolution time
 *   'default' => '@today'  // date('Y-m-d')
 *
 * Sentinels beat literals for "created at"-style fields because a literal
 * is evaluated when the blueprint is *built* — which under a worker runtime
 * is once per process, freezing yesterday's boot time into today's records.
 */
final class Defaults
{
    /**
     * @param list<array<string, mixed>> $blueprint
     * @param array<string, mixed>       $values
     *
     * @return array<string, mixed>
     */
    public static function resolve(array $blueprint, array $values, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();

        foreach ($blueprint as $field) {
            $key = (string) ($field['key'] ?? '');

            if ('' === $key || !\array_key_exists('default', $field)) {
                continue;
            }

            $current = $values[$key] ?? null;
            if (null !== $current && '' !== $current && [] !== $current) {
                continue;
            }

            $values[$key] = match ($field['default']) {
                '@now' => $now->format('Y-m-d H:i:s'),
                '@today' => $now->format('Y-m-d'),
                default => $field['default'],
            };
        }

        return $values;
    }
}
