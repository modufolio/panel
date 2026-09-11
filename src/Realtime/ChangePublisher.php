<?php

declare(strict_types=1);

namespace Modufolio\Panel\Realtime;

/**
 * Announces that a resource changed, so panels looking at it can catch up.
 *
 * A *nudge*, not a feed: the message names the resource and carries no record
 * data, and a listening page answers by reloading through the route it would
 * have used anyway. That is what keeps authorization in one place — the reload
 * is authorized on its own terms, so a channel never has to restate a rule the
 * server already enforces.
 *
 * The panel declares the contract and calls it after every write it performs;
 * the transport is the host's business. A host with no realtime binds
 * {@see NullChangePublisher} (the module's default) and nothing changes.
 */
interface ChangePublisher
{
    /**
     * @param string               $resource A resource key — `PanelResource::key()`
     * @param array<string, mixed> $payload  Hints for the client; never data a listener may not see
     */
    public function changed(string $resource, array $payload = []): void;
}
