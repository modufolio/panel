<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Routing;

use Modufolio\Panel\Resource\PanelResource;

/** A read-only resource: no form fields, so no write routes. */
final class ReadOnlyResource extends PanelResource
{
    public function key(): string { return 'events'; }
    public function entityClass(): string { return 'Fixture\\Entity\\Event'; }
    public function listQueryClass(): string { return 'Fixture\\Query\\EventListQuery'; }
    public function present(array $entities): array { return []; }
}
