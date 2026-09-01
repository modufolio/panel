<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Routing;

use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Tests\Fixture\StubListQuery;

/** A read-only resource: no form fields, so no write routes. */
final class ReadOnlyResource extends PanelResource
{
    public function key(): string { return 'events'; }
    public function entityClass(): string { return \stdClass::class; }
    public function listQueryClass(): string { return StubListQuery::class; }
    public function present(array $entities): array { return []; }
}
