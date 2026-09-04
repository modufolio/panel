<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Routing;

use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Tests\Fixture\StubListQuery;

/** A resource with a constructor dependency, which only the resolver can supply. */
final class NeedsConstructorArgsResource extends PanelResource
{
    public function __construct(private readonly string $required) {}

    /** The key comes from the argument, so a generated path proves the resolver was asked. */
    public function key(): string { return $this->required; }
    public function entityClass(): string { return \stdClass::class; }
    public function listQueryClass(): string { return StubListQuery::class; }
    public function present(array $entities): array { return []; }
}
