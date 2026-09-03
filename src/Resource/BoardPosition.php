<?php

declare(strict_types=1);

namespace Modufolio\Panel\Resource;

/**
 * Where a card sits within its board column.
 *
 * The obvious design — a card's index — cannot express "between these two"
 * without renumbering everything below the drop, so every drag rewrites the
 * column and two people dragging at once overwrite each other. This leaves
 * *gaps* instead: cards start far apart, a drop between two neighbours takes
 * the midpoint of the gap, and only the moved row is written.
 *
 * Plain 64-bit integers, deliberately. The first version of this used decimals
 * and bcmath, which bought exact midpoints that the storage then threw away:
 * SQLite gives a DECIMAL column REAL affinity, so positions round-tripped
 * through a double and two values the server computed as distinct came back
 * equal — silently, which is the one failure the whole scheme exists to
 * prevent. An integer is exact in every database here and in PHP, and needs no
 * extension.
 *
 * The gap affords 32 halvings in one spot before it closes, and the column
 * holds two billion cards before the range runs out. {@see needsRebalance()}
 * reports the first case and {@see sequence()} fixes it.
 *
 * Two people can still drop into the same gap in the same instant, and a plain
 * midpoint would hand both the identical value. {@see between()} adds a small
 * random offset, so they land in a defined order rather than a tie.
 */
final class BoardPosition
{
    /**
     * Distance between freshly appended cards: 2^32.
     *
     * A power of two so halving stays exact, and large enough that repeatedly
     * dropping into the same gap keeps finding room — 32 times before the gap
     * closes.
     */
    public const GAP = 4294967296;

    /** Below this a gap cannot be halved again, and the column wants rebalancing. */
    public const MIN_GAP = 2;

    /** Random offset applied to a midpoint, as a fraction of the gap. */
    private const JITTER_DIVISOR = 10;

    /** The position of the first card in an empty column. */
    public static function first(): int
    {
        return self::GAP;
    }

    /**
     * The position an appended card takes when all that is known is its index.
     *
     * The bridge from a scheme where a card's place *was* its index. A caller
     * that knows its neighbours should use {@see forDrop()} instead — this one
     * assumes the column is otherwise evenly spaced.
     */
    public static function atIndex(int $index): int
    {
        if ($index < 0) {
            throw new \InvalidArgumentException('A card index cannot be negative.');
        }

        if ($index >= intdiv(PHP_INT_MAX, self::GAP)) {
            throw new \InvalidArgumentException('That card index is beyond the position range.');
        }

        return ($index + 1) * self::GAP;
    }

    /** A position after everything: appending to the end of a column. */
    public static function after(int $position): int
    {
        if ($position > PHP_INT_MAX - self::GAP) {
            throw new BoardPositionExhausted(
                'The end of this column has run out of room; rebalance it before appending.',
            );
        }

        return $position + self::GAP;
    }

    /** A position before everything: dropping at the top of a column. */
    public static function before(int $position): int
    {
        if ($position < PHP_INT_MIN + self::GAP) {
            throw new BoardPositionExhausted(
                'The start of this column has run out of room; rebalance it before prepending.',
            );
        }

        return $position - self::GAP;
    }

    /**
     * A position strictly between two neighbours, offset by a small random
     * amount so two simultaneous drops into the same gap do not collide.
     *
     * Equal bounds are not an error: it is what a column already holding
     * duplicate positions looks like — imported rows, or an older scheme — and
     * the honest answer is to sit after the first of them rather than to refuse
     * the move.
     *
     * @throws BoardPositionExhausted when the gap has closed and there is
     *         genuinely nothing between the two. The caller rebalances and
     *         retries; a silent tie would put the card on the wrong side.
     */
    public static function between(int $after, int $before): int
    {
        if ($after === $before) {
            return self::after($after);
        }

        if ($after > $before) {
            throw new \InvalidArgumentException(sprintf(
                'Position %d does not come before %d; the neighbours are the wrong way round.',
                $after,
                $before,
            ));
        }

        // Computed as a distance from the lower bound rather than as
        // (after + before) / 2, which overflows for two large positions.
        $gap = $before - $after;

        if ($gap < self::MIN_GAP) {
            throw new BoardPositionExhausted(
                'There is no room left between those two cards; rebalance the column.',
            );
        }

        $midpoint = $after + intdiv($gap, 2);

        return $midpoint + self::jitter($gap, $midpoint, $after, $before);
    }

    /**
     * The exact midpoint, with no random offset.
     *
     * For tests and anywhere a reproducible value matters more than concurrent
     * safety. Prefer {@see between()} for an actual move.
     */
    public static function midpoint(int $after, int $before): int
    {
        if ($after >= $before) {
            throw new \InvalidArgumentException(sprintf(
                'Position %d does not come before %d.',
                $after,
                $before,
            ));
        }

        $gap = $before - $after;

        if ($gap < self::MIN_GAP) {
            throw new BoardPositionExhausted(
                'There is no room left between those two cards; rebalance the column.',
            );
        }

        return $after + intdiv($gap, 2);
    }

    /**
     * Where a card dropped between these two neighbours belongs.
     *
     * Both null means the column was empty; one null means an end of it. The
     * four cases in one place, rather than re-derived per caller.
     */
    public static function forDrop(?int $after, ?int $before): int
    {
        return match (true) {
            $after === null && $before === null => self::first(),
            $after === null                     => self::before((int) $before),
            $before === null                    => self::after((int) $after),
            default                             => self::between((int) $after, (int) $before),
        };
    }

    /**
     * Whether two neighbours have grown too close to keep splitting.
     *
     * Checked after a move rather than before: the move itself succeeds, and
     * the column is rebalanced afterwards, so no drag fails because of
     * arithmetic the person dragging cannot see.
     */
    public static function needsRebalance(int $after, int $before): bool
    {
        return $before - $after < self::MIN_GAP;
    }

    /**
     * Evenly spaced positions for a whole column, in order.
     *
     * What rebalancing writes once the gaps have closed.
     *
     * @return list<int>
     */
    public static function sequence(int $count): array
    {
        $positions = [];

        for ($index = 0; $index < $count; $index++) {
            $positions[] = self::atIndex($index);
        }

        return $positions;
    }

    /** A value from anywhere — a database string, a legacy float — as a position. */
    public static function normalize(string|int|float $position): int
    {
        return (int) $position;
    }

    public static function compare(int $a, int $b): int
    {
        return $a <=> $b;
    }

    /**
     * A random offset that keeps the result strictly inside the bounds.
     *
     * Clamped rather than assumed: near a closed gap the tenth of it that the
     * offset is drawn from would otherwise be able to reach a neighbour, and a
     * position equal to its bound is the tie this exists to avoid.
     */
    private static function jitter(int $gap, int $midpoint, int $after, int $before): int
    {
        $bound = intdiv($gap, self::JITTER_DIVISOR);

        // Never past a neighbour, and never onto one.
        $bound = min($bound, $midpoint - $after - 1, $before - $midpoint - 1);

        return $bound < 1 ? 0 : random_int(-$bound, $bound);
    }
}
