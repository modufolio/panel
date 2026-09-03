<?php

declare(strict_types=1);

namespace Modufolio\Panel\Resource;

/**
 * A column has no room left where a card was dropped.
 *
 * Distinct from an invalid argument because it is *recoverable*: the caller
 * rebalances the column and retries, and the person dragging never sees it.
 * Raised rather than resolved by handing back a colliding position, because a
 * tie puts the card on an arbitrary side of its neighbour.
 */
final class BoardPositionExhausted extends \RuntimeException
{
}
