<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Routing;

use Modufolio\Panel\Resource\PanelResource;

/** Routes cannot be generated for a resource the loader cannot instantiate. */
final class NeedsConstructorArgsResource extends PanelResource
{
    public function __construct(private readonly string $required) {}

    public function key(): string { return 'broken'; }
    public function entityClass(): string { return 'Fixture\\Entity\\Broken'; }
    public function listQueryClass(): string { return 'Fixture\\Query\\BrokenListQuery'; }
    public function present(array $entities): array { return []; }
}
