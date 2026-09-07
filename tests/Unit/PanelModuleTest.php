<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Modufolio\Appkit\Core\AppInterface;
use Modufolio\Appkit\DependencyInjection\ServiceConfigurator;
use Modufolio\Panel\Contracts\ExportAdapterProviderInterface;
use Modufolio\Panel\Contracts\ResourceLocatorInterface;
use Modufolio\Panel\Export\NoExportAdapters;
use Modufolio\Panel\Form\FormResolver;
use Modufolio\Panel\Http\ResourceController;
use Modufolio\Panel\PanelModule;
use Modufolio\Panel\Resource\ContainerResourceLocator;
use PHPUnit\Framework\TestCase;

/**
 * The module is the whole registration: its controller map covers the
 * controller's constructor, and its services are the defaults a host may
 * override.
 */
final class PanelModuleTest extends TestCase
{
    public function testItIsNamedPanel(): void
    {
        self::assertSame('panel', (new PanelModule())->name());
    }

    public function testTheControllerMapNamesEveryConstructorParameter(): void
    {
        $map = (new PanelModule())->controllers()[ResourceController::class];

        $parameters = array_map(
            static fn (\ReflectionParameter $p): string => $p->getName(),
            (new \ReflectionMethod(ResourceController::class, '__construct'))->getParameters(),
        );

        self::assertSame($parameters, array_keys($map), 'Named arguments, one per parameter, in signature order.');

        foreach ($map as $parameter => $id) {
            self::assertTrue(interface_exists($id) || class_exists($id), sprintf('"%s" for $%s is a real id.', $id, $parameter));
        }
    }

    public function testItsServicesAreTheDefaultsAHostWouldOtherwiseDeclare(): void
    {
        $services = new ServiceConfigurator();
        (new PanelModule())->services($services, ['media_entity' => \stdClass::class]);

        $app = $this->createStub(AppInterface::class);
        $app->method('entityManager')->willReturn($this->createStub(EntityManagerInterface::class));

        self::assertInstanceOf(ContainerResourceLocator::class, $services->definitions[ResourceLocatorInterface::class]($app));
        self::assertInstanceOf(FormResolver::class, $services->definitions[FormResolver::class]($app));

        $exports = $services->definitions[ExportAdapterProviderInterface::class]($app);
        self::assertInstanceOf(NoExportAdapters::class, $exports);

        $this->expectException(\InvalidArgumentException::class);
        $exports->get('csv');
    }

    public function testAnUnknownConfigKeyIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('media_entity');

        (new PanelModule())->services(new ServiceConfigurator(), ['media' => 'App\Entity\Media']);
    }

    public function testMediaEntityMustBeAnExistingClassOrNull(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('"App\\Entity\\Nope"');

        (new PanelModule())->services(new ServiceConfigurator(), ['media_entity' => 'App\\Entity\\Nope']);
    }
}
