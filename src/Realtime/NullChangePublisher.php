<?php

declare(strict_types=1);

namespace Modufolio\Panel\Realtime;

/**
 * The publisher for a panel with no realtime server behind it — which is the
 * normal case, and not a degraded one.
 *
 * Bound by {@see \Modufolio\Panel\PanelModule} so the controller can always
 * announce a write without asking whether anyone is listening. A host that
 * wants live updates declares its own {@see ChangePublisher} in
 * config/services.php and overrides this.
 */
final class NullChangePublisher implements ChangePublisher
{
    public function changed(string $resource, array $payload = []): void
    {
    }
}
