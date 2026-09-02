<?php

declare(strict_types=1);

namespace Modufolio\Panel\Field\Concerns;

use Doctrine\ORM\QueryBuilder;

/**
 * The text-shaped operator set, shared by every string-valued field type.
 *
 * The operators mirror modufolio/json-api's SearchFilter strategies
 * (partial/exact/start/end) under the panel's humane names — one filter
 * vocabulary across the ecosystem, two layers that apply it.
 */
trait FiltersText
{
    /**
     * LIKE escape character. Declared in the DQL rather than assumed: without
     * an ESCAPE clause the engine's default applies, and SQLite has none — a
     * backslash there is an ordinary character, so a search for "%" looked
     * for a literal "\%" and never matched. Not a backslash, for the same
     * reason RelationOptionResolver avoids one: it would have to survive PHP,
     * DQL and the driver's own quoting, and each treats it differently.
     */
    private const LIKE_ESCAPE = '!';

    public static function filterOperators(): array
    {
        return [
            'contains' => 'Contains',
            'not_contains' => 'Does not contain',
            'equals' => 'Is exactly',
            'not_equals' => 'Is not',
            'starts_with' => 'Starts with',
            'ends_with' => 'Ends with',
            'empty' => 'Is empty',
            'not_empty' => 'Is not empty',
        ];
    }

    public static function applyFilter(QueryBuilder $qb, string $dqlField, string $operator, mixed $value, string $parameter): void
    {
        $text = \is_scalar($value) ? (string) $value : '';
        $e = self::LIKE_ESCAPE;
        $escaped = str_replace([$e, '%', '_'], [$e.$e, $e.'%', $e.'_'], $text);
        $like = "LIKE :{$parameter} ESCAPE '{$e}'";

        match ($operator) {
            'contains' => $qb->andWhere("{$dqlField} {$like}")->setParameter($parameter, '%'.$escaped.'%'),
            'not_contains' => $qb->andWhere("{$dqlField} NOT {$like}")->setParameter($parameter, '%'.$escaped.'%'),
            'equals' => $qb->andWhere("{$dqlField} = :{$parameter}")->setParameter($parameter, $text),
            'not_equals' => $qb->andWhere("{$dqlField} != :{$parameter}")->setParameter($parameter, $text),
            'starts_with' => $qb->andWhere("{$dqlField} {$like}")->setParameter($parameter, $escaped.'%'),
            'ends_with' => $qb->andWhere("{$dqlField} {$like}")->setParameter($parameter, '%'.$escaped),
            'empty' => $qb->andWhere("({$dqlField} IS NULL OR {$dqlField} = '')"),
            'not_empty' => $qb->andWhere("({$dqlField} IS NOT NULL AND {$dqlField} != '')"),
            default => throw new \InvalidArgumentException(sprintf('Unknown filter operator "%s" for %s.', $operator, static::class)),
        };
    }
}
