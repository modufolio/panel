<?php

declare(strict_types=1);

namespace Modufolio\Panel\Resource;

use Modufolio\Panel\Contracts\PageRendererInterface;
use Modufolio\Panel\Contracts\SharedPropsInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Modufolio\Appkit\Security\Token\TokenStorageInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Build a request-bound {@see ResourceListing} for a PanelResource class.
 *
 * Extracted from ResourceListingResolver so the generic ResourceController can
 * do the same thing: the resolver knows the resource class at compile time
 * (from `#[Resource(...)]`), the controller reads it off the matched route,
 * but both need identical construction and identical container semantics.
 */
final class ResourceListingFactory
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * @param class-string<PanelResource> $resourceClass
     */
    public function make(string $resourceClass, ServerRequestInterface $request): ResourceListing
    {
        $sharedProps = $this->container->get(SharedPropsInterface::class);
        assert($sharedProps instanceof SharedPropsInterface);

        $renderer = $this->container->get(PageRendererInterface::class);
        assert($renderer instanceof PageRendererInterface);

        return new ResourceListing(
            resource: $this->resource($resourceClass),
            request: $request,
            entityManager: $this->entityManager,
            urlGenerator: $this->urlGenerator,
            sharedProps: $sharedProps,
            renderer: $renderer,
            user: $this->currentUser(),
        );
    }

    /** The signed-in user, as a resource's permission hooks receive it. */
    private function currentUser(): ?object
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        return is_object($user) ? $user : null;
    }

    /**
     * Registered services win, so a resource needing collaborators (a presenter
     * wanting ImageService, say) can be declared in config/interfaces.php. A
     * resource with no required constructor arguments needs no registration at
     * all, keeping the common case zero-config.
     *
     * @param class-string<PanelResource> $class
     */
    public function resource(string $class): PanelResource
    {
        $resource = $this->container->has($class)
            ? $this->container->get($class)
            : $this->instantiate($class);

        if (!$resource instanceof PanelResource) {
            throw new \LogicException(sprintf(
                'Expected a %s, got %s.',
                PanelResource::class,
                get_debug_type($resource),
            ));
        }

        return $resource;
    }

    private function instantiate(string $class): object
    {
        if (!class_exists($class)) {
            throw new \LogicException(sprintf('Unknown panel resource class "%s".', $class));
        }

        $constructor = (new \ReflectionClass($class))->getConstructor();

        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            throw new \LogicException(sprintf(
                '%s has required constructor arguments, so it must be registered in config/interfaces.php.',
                $class,
            ));
        }

        return new $class();
    }
}
