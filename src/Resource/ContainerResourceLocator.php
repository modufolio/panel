<?php

declare(strict_types=1);

namespace Modufolio\Panel\Resource;

use Modufolio\Panel\Contracts\ResourceLocatorInterface;
use Psr\Container\ContainerInterface;

/**
 * Resources as the container builds them: `get(Foo::class)` must answer with
 * a Foo, so a resource registered under the wrong id, or not at all, fails
 * here by name instead of somewhere down the request.
 */
final class ContainerResourceLocator implements ResourceLocatorInterface
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function get(string $class): PanelResource
    {
        $resource = $this->container->get($class);

        if (!$resource instanceof $class) {
            throw new \LogicException(sprintf(
                'Service "%s" is registered as %s, not as itself.',
                $class,
                get_debug_type($resource),
            ));
        }

        return $resource;
    }
}
