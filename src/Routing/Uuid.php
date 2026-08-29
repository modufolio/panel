<?php

declare(strict_types=1);

namespace Modufolio\Panel\Routing;

/**
 * Route matching for the public identifiers panel resources are addressed by.
 *
 * Generated routes never carry a row number: `/panel/events/{uuid}` is what
 * `ResourceController::find()` looks a record up by, and a route that accepted
 * anything would let `/panel/events/create` match the show route.
 *
 * The package owns this pattern rather than borrowing the host's so that it
 * can generate routes without knowing anything about the application's own
 * toolkit. An application matching uuids in its hand-written routes keeps
 * using whatever it already has.
 */
final class Uuid
{
    public const PATTERN = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';
}
