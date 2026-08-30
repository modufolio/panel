<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Blueprint;

use Modufolio\Panel\Blueprint\BlueprintRegistry;
use Modufolio\Panel\Tests\Blueprint\Fixture\PricingBlueprint;
use PHPUnit\Framework\TestCase;

/**
 * Convention resolution, pinned at the package level.
 *
 * The registry once resolved conventional names in its *own* namespace, so
 * every application blueprint under the documented `App\Panel\Blueprint`
 * convention silently failed to resolve and pages fell back to guessed
 * fields — with the only failing test living in a consuming app that never
 * ran it. This suite is the package-side half of that contract.
 */
final class BlueprintRegistryTest extends TestCase
{
    private function registry(): BlueprintRegistry
    {
        return new BlueprintRegistry('Modufolio\\Panel\\Tests\\Blueprint\\Fixture');
    }

    public function testResolvesConventionalNamesInTheConfiguredNamespace(): void
    {
        $this->assertInstanceOf(PricingBlueprint::class, $this->registry()->for('pricing'));
    }

    public function testTheDefaultNamespaceIsTheDocumentedAppConvention(): void
    {
        // App\Panel\Blueprint\NoSuchBlueprint does not exist here, so the
        // default-constructed registry answers null — the important half is
        // that it *looked* in App\Panel\Blueprint rather than its own
        // namespace, which this class would satisfy if it still did.
        $this->assertNull((new BlueprintRegistry())->for('blueprint-registry'));
    }

    public function testKebabAndSnakeCaseBothStudlyResolve(): void
    {
        // `pricing` ← Pricing; a compound name exercises the studly rule.
        $registry = $this->registry();
        $this->assertNull($registry->for('no-such-template'));
        $this->assertInstanceOf(PricingBlueprint::class, $registry->for('pricing'));
    }

    public function testATemplateNameCannotTraverseIntoAnotherClass(): void
    {
        $registry = $this->registry();

        $this->assertNull($registry->for('../../Field/TextType'));
        $this->assertNull($registry->for('Pricing\\..\\Pricing'));
        $this->assertNull($registry->for(''));
    }

    public function testResolutionIsCachedPerTemplate(): void
    {
        $registry = $this->registry();

        $this->assertSame($registry->for('pricing'), $registry->for('pricing'));
    }
}
