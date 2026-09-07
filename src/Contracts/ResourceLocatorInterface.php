<?php

declare(strict_types=1);

namespace Modufolio\Panel\Contracts;

use Modufolio\Panel\Resource\PanelResource;

/**
 * Where a resource class becomes an instance.
 *
 * The package never constructs a resource: a resource with collaborators
 * takes them through its constructor, and the one place that knows how to
 * build it is the host's container. The controller and the route loader ask
 * this interface instead of the container itself, so neither holds the
 * application. {@see \Modufolio\Panel\Resource\ContainerResourceLocator}
 * adapts any PSR-11 container; {@see \Modufolio\Panel\PanelModule} registers
 * it over the host's.
 */
interface ResourceLocatorInterface
{
    /**
     * @template T of PanelResource
     * @param  class-string<T> $class
     * @return T
     *
     * @throws \LogicException when the container answers with something else
     */
    public function get(string $class): PanelResource;
}
