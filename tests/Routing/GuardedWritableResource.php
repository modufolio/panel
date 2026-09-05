<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Routing;

use Modufolio\Panel\Form\Form;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\Permissions;
use Modufolio\Panel\Tests\Fixture\StubListQuery;

/** A writable resource whose permissions name a role for the routes. */
final class GuardedWritableResource extends PanelResource
{
    public function key(): string { return 'actors'; }
    public function entityClass(): string { return \stdClass::class; }
    public function listQueryClass(): string { return StubListQuery::class; }
    public function present(array $entities): array { return []; }

    public function form(): Form { return Form::make()->fields(['name' => []]); }

    public function permissions(): Permissions
    {
        return new Permissions(['ROLE_ADMIN']);
    }
}
