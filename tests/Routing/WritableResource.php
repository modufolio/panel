<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Routing;

use Modufolio\Panel\Resource\PanelResource;

/** A writable resource: declaring form fields is what turns writes on. */
final class WritableResource extends PanelResource
{
    public function key(): string { return 'actors'; }
    public function entityClass(): string { return 'Fixture\\Entity\\Actor'; }
    public function listQueryClass(): string { return 'Fixture\\Query\\ActorListQuery'; }
    public function present(array $entities): array { return []; }

    public function formFieldKeys(): array { return ['name' => []]; }
}
