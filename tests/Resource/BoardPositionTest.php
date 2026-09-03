<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Resource;

use Modufolio\Panel\Resource\BoardPosition;
use Modufolio\Panel\Resource\BoardPositionExhausted;
use PHPUnit\Framework\TestCase;

/**
 * The arithmetic a board's drag-and-drop rests on.
 *
 * The property that matters is not any particular value but the invariant: a
 * position computed for a drop sorts strictly between its neighbours, however
 * many times the same gap is split — and when it genuinely cannot, it says so
 * rather than handing back a tie.
 */
final class BoardPositionTest extends TestCase
{
    public function testTheFirstCardSitsAtTheDefaultGap(): void
    {
        $this->assertSame(BoardPosition::GAP, BoardPosition::first());
    }

    public function testADropBetweenTwoCardsLandsStrictlyBetweenThem(): void
    {
        $position = BoardPosition::forDrop(100, 200);

        $this->assertGreaterThan(100, $position);
        $this->assertLessThan(200, $position);
    }

    /**
     * The reason for gaps rather than indices: splitting the same gap over and
     * over has to keep finding room without renumbering the column.
     */
    public function testTheSameGapCanBeSplitToExhaustion(): void
    {
        $after  = 0;
        $before = BoardPosition::GAP;
        $splits = 0;

        while (true) {
            try {
                $position = BoardPosition::midpoint($after, $before);
            } catch (BoardPositionExhausted) {
                break;
            }

            $this->assertGreaterThan($after, $position, "split {$splits} collapsed onto its lower bound");
            $this->assertLessThan($before, $position, "split {$splits} collapsed onto its upper bound");

            $before = $position;
            $splits++;
        }

        $this->assertSame(32, $splits, 'A 2^32 gap halves 32 times before it closes');
    }

    /**
     * Two people dropping into the same gap at the same instant must not be
     * handed the same position — that is a tie the board cannot order.
     */
    public function testConcurrentDropsIntoTheSameGapGetDistinctPositions(): void
    {
        $seen = [];

        for ($i = 0; $i < 200; $i++) {
            $seen[] = BoardPosition::between(0, BoardPosition::GAP);
        }

        $this->assertCount(200, array_unique($seen), 'Jitter must keep simultaneous drops apart');
    }

    /** The random offset must never reach a neighbour, however narrow the gap. */
    public function testJitterStaysStrictlyInsideTheGap(): void
    {
        foreach ([2, 3, 4, 7, 100, BoardPosition::GAP] as $gap) {
            for ($i = 0; $i < 100; $i++) {
                $position = BoardPosition::between(10, 10 + $gap);

                $this->assertGreaterThan(10, $position, "gap {$gap} reached its lower bound");
                $this->assertLessThan(10 + $gap, $position, "gap {$gap} reached its upper bound");
            }
        }
    }

    public function testDroppingAtEitherEndOfAColumn(): void
    {
        $this->assertLessThan(500, BoardPosition::forDrop(null, 500));
        $this->assertGreaterThan(500, BoardPosition::forDrop(500, null));
    }

    public function testAnEmptyColumnGetsTheFirstPosition(): void
    {
        $this->assertSame(BoardPosition::first(), BoardPosition::forDrop(null, null));
    }

    /**
     * Duplicate positions are a state a column can genuinely be in — imported
     * rows, an older scheme — and a move must not refuse because of it.
     */
    public function testEqualNeighboursAppendInsteadOfThrowing(): void
    {
        $this->assertGreaterThan(7, BoardPosition::between(7, 7));
    }

    public function testNeighboursTheWrongWayRoundAreRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        BoardPosition::between(9, 2);
    }

    /**
     * A closed gap is reported, not papered over with a colliding value: a tie
     * puts the card on an arbitrary side of its neighbour.
     */
    public function testAClosedGapIsRefusedRatherThanTied(): void
    {
        $this->expectException(BoardPositionExhausted::class);

        BoardPosition::between(5, 6);
    }

    public function testCollapsedGapsAreReported(): void
    {
        $this->assertTrue(BoardPosition::needsRebalance(5, 6));
        $this->assertFalse(BoardPosition::needsRebalance(5, 7));
    }

    /**
     * The midpoint of two positions near the top of the range must not
     * overflow — `(a + b) / 2` does, which is why it is computed as a distance
     * from the lower bound.
     */
    public function testTheMidpointOfTwoHugePositionsDoesNotOverflow(): void
    {
        $after  = PHP_INT_MAX - 1000;
        $before = PHP_INT_MAX - 10;

        $position = BoardPosition::midpoint($after, $before);

        $this->assertIsInt($position);
        $this->assertGreaterThan($after, $position);
        $this->assertLessThan($before, $position);
    }

    /** Appending past the end of the range is refused, not wrapped. */
    public function testAppendingBeyondTheRangeIsRefused(): void
    {
        $this->expectException(BoardPositionExhausted::class);

        BoardPosition::after(PHP_INT_MAX - 1);
    }

    public function testPrependingBeyondTheRangeIsRefused(): void
    {
        $this->expectException(BoardPositionExhausted::class);

        BoardPosition::before(PHP_INT_MIN + 1);
    }

    public function testARebalancedSequenceIsEvenlySpacedAndAscending(): void
    {
        $sequence = BoardPosition::sequence(5);

        $this->assertCount(5, $sequence);

        foreach (array_slice($sequence, 1) as $index => $position) {
            $this->assertGreaterThan($sequence[$index], $position);
        }
    }

    public function testIndexedPositionsMatchTheRebalancedSequence(): void
    {
        // The bridge from the index scheme has to agree with what a rebalance
        // writes, or migrated rows and rebalanced ones would interleave
        // differently.
        foreach (BoardPosition::sequence(4) as $index => $expected) {
            $this->assertSame($expected, BoardPosition::atIndex($index));
        }
    }

    public function testANegativeIndexIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        BoardPosition::atIndex(-1);
    }

    /** Whatever the database hands back becomes a position. */
    public function testValuesFromStorageNormaliseToIntegers(): void
    {
        $this->assertSame(65536, BoardPosition::normalize('65536'));
        $this->assertSame(65536, BoardPosition::normalize('65536.0000000000'));
        $this->assertSame(65536, BoardPosition::normalize(65536.0));
    }
}
