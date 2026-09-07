<?php

declare(strict_types=1);

namespace Modufolio\Panel;

use Doctrine\ORM\EntityManagerInterface;
use Modufolio\Appkit\Core\AppInterface;
use Modufolio\Appkit\DependencyInjection\ServiceConfigurator;
use Modufolio\Appkit\Module\AbstractModule;
use Modufolio\Appkit\Security\Token\TokenStorageInterface;
use Modufolio\Panel\Contracts\ExportAdapterProviderInterface;
use Modufolio\Panel\Contracts\ResourceLocatorInterface;
use Modufolio\Panel\Export\NoExportAdapters;
use Modufolio\Panel\Form\FormResolver;
use Modufolio\Panel\Http\ResourceController;
use Modufolio\Panel\Resource\ContainerResourceLocator;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The panel as an appkit module: list it in config/modules.php and the
 * package's controller is wired.
 *
 * ```php
 * // config/modules.php
 * return [
 *     \Modufolio\Panel\PanelModule::class => ['media_entity' => \App\Entity\Media::class],
 * ];
 * ```
 *
 * The module contributes two things. Its controller map names every
 * constructor dependency of {@see ResourceController}, so the kernel builds
 * the controller from the container like any wired controller — no
 * reflection fallback, no `AppAware` hand-over, no application held anywhere.
 * And its services are the defaults a host would otherwise have to declare:
 * a {@see ResourceLocatorInterface} over the host's container, a
 * {@see FormResolver} naming the configured media entity, and a
 * {@see ExportAdapterProviderInterface} that offers no formats. Module
 * definitions sit underneath the application's config/services.php, so a
 * host overrides any of them by declaring the same id.
 *
 * Pages are Inertia pages the kernel finishes: the host wires appkit's
 * InertiaModule with its root view and shared props, and the panel names
 * only components and props.
 *
 * Configuration keys:
 *   - `media_entity`: the entity class of the media library, so a to-one
 *     pointing at it is guessed as an image field (null: no media library).
 */
final class PanelModule extends AbstractModule
{
    protected function defaultConfig(): array
    {
        return ['media_entity' => null];
    }

    public function controllers(): array
    {
        return [
            ResourceController::class => [
                'entityManager' => EntityManagerInterface::class,
                'urlGenerator' => UrlGeneratorInterface::class,
                'validator' => ValidatorInterface::class,
                'tokenStorage' => TokenStorageInterface::class,
                'flashBag' => FlashBagInterface::class,
                'resources' => ResourceLocatorInterface::class,
                'forms' => FormResolver::class,
                'exports' => ExportAdapterProviderInterface::class,
            ],
        ];
    }

    protected function loadServices(ServiceConfigurator $services, array $config): void
    {
        $declared = $config['media_entity'] ?? null;

        if ($declared !== null && (!is_string($declared) || !class_exists($declared))) {
            throw new \LogicException(sprintf(
                'The panel module\'s "media_entity" must be an existing entity class or null, %s given.',
                is_string($declared) ? '"'.$declared.'"' : get_debug_type($declared),
            ));
        }

        $mediaEntity = $declared;

        $services
            ->set(ResourceLocatorInterface::class, fn (AppInterface $app) => new ContainerResourceLocator($app))
            ->set(FormResolver::class, fn (AppInterface $app) => new FormResolver($app->entityManager(), $mediaEntity))
            ->set(ExportAdapterProviderInterface::class, fn () => new NoExportAdapters());
    }
}
