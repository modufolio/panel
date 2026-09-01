<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Routing;

use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Tests\Fixture\StubListQuery;

/** Routes cannot be generated for a resource the loader cannot instantiate. */
final class NeedsConstructorArgsResource extends PanelResource
{
    public function __construct(private readonly string $required) {}

    /** Read so the argument is genuinely needed; the loader refuses long before asking. */
    public function key(): string { return $this->required; }
    public function entityClass(): string { return \stdClass::class; }
    public function listQueryClass(): string { return StubListQuery::class; }
    public function present(array $entities): array { return []; }
}
