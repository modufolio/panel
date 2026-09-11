<?php

declare(strict_types=1);

namespace Modufolio\Panel;

use Doctrine\ORM\EntityManagerInterface;
use Modufolio\Appkit\Core\AppInterface;
use Modufolio\Appkit\Core\Kernel;
use Modufolio\Appkit\DependencyInjection\ServiceConfigurator;
use Modufolio\Appkit\Module\AbstractModule;
use Modufolio\Appkit\Security\Token\TokenStorageInterface;
use Modufolio\Panel\Contracts\ExportAdapterProviderInterface;
use Modufolio\Panel\Contracts\PermissionReportProviderInterface;
use Modufolio\Panel\Export\NoExportAdapters;
use Modufolio\Panel\Form\FormResolver;
use Modufolio\Panel\Http\ResourceController;
use Modufolio\Panel\Inspection\NoPermissionReport;
use Modufolio\Panel\Inspection\PermissionInspector;
use Modufolio\Panel\Realtime\ChangePublisher;
use Modufolio\Panel\Realtime\NullChangePublisher;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Search\GlobalSearch;
use Psr\Clock\ClockInterface;
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
 * a {@see FormResolver} naming the configured media entity, and a
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
                'clock' => ClockInterface::class,
                'forms' => FormResolver::class,
                'exports' => ExportAdapterProviderInterface::class,
                'search' => GlobalSearch::class,
                'permissions' => PermissionReportProviderInterface::class,
                'realtime' => ChangePublisher::class,
            ],
        ];
    }

    protected function loadServices(ServiceConfigurator $services, array $config): void
    {
        // Realtime is opt-in: the controller announces every write, and with
        // nothing listening that costs one method call. A host that wants live
        // panels declares its own ChangePublisher, which — like every module
        // default — wins over this one.
        $services->set(ChangePublisher::class, static fn (): ChangePublisher => new NullChangePublisher());

        // The search across resources reads the resources off the routes, as
        // the permission inspector does, so it knows exactly what is mounted.
        $services->set(GlobalSearch::class, static function (AppInterface $app): GlobalSearch {
            // The route collection is the kernel's; the interface exposes the
            // URL generator only, so the concrete kernel is asked for it.
            if (!$app instanceof Kernel) {
                throw new \LogicException(sprintf('The panel\'s search needs the kernel\'s router; %s is not a %s.', get_debug_type($app), Kernel::class));
            }

            return new GlobalSearch(
                $app->entityManager(),
                $app->urlGenerator(),
                $app->get(ClockInterface::class),
                // The second argument is the container's own type check, so
                // a resource registered under someone else's id fails by name
                // here rather than somewhere down the request.
                static fn (string $class): PanelResource => $app->get($class, $class),
                static fn (): array => PermissionInspector::resourceClassesIn($app->router()->getRouteCollection()),
            );
        });

        $declared = $config['media_entity'] ?? null;

        if ($declared !== null && (!is_string($declared) || !class_exists($declared))) {
            throw new \LogicException(sprintf(
                'The panel module\'s "media_entity" must be an existing entity class or null, %s given.',
                is_string($declared) ? '"'.$declared.'"' : get_debug_type($declared),
            ));
        }

        $mediaEntity = $declared;

        $services
            ->set(FormResolver::class, fn (AppInterface $app) => new FormResolver($app->entityManager(), $mediaEntity))
            ->set(ExportAdapterProviderInterface::class, fn () => new NoExportAdapters())
            // No report until a host wires one: only the application knows its
            // roles and what a user carrying one looks like.
            ->set(PermissionReportProviderInterface::class, fn () => new NoPermissionReport());
    }
}
