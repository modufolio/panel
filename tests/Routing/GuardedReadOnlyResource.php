<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Routing;

use Modufolio\Panel\Resource\Menu;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\Permissions;
use Modufolio\Panel\Tests\Fixture\StubListQuery;

/** A read-only resource whose permissions name a role for the routes. */
final class GuardedReadOnlyResource extends PanelResource
{
    public function key(): string { return 'events'; }
    public function entityClass(): string { return \stdClass::class; }
    public function listQueryClass(): string { return StubListQuery::class; }
    public function present(array $entities): array { return []; }

    public function permissions(): Permissions
    {
        return new Permissions(['ROLE_ADMIN']);
    }

    public function menu(): Menu
    {
        return Menu::make('Events', icon: 'calendar', group: 'Main', order: 16);
    }
}
