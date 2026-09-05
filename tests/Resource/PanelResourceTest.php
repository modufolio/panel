<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Resource;

use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\Permissions;
use Modufolio\Panel\Table\TableSchema;
use Modufolio\Panel\Tests\Fixture\StubListQuery;
use PHPUnit\Framework\TestCase;

/**
 * The defaults a resource inherits, and the hooks a portal overrides.
 *
 * These are the seams client work actually uses: a resource that is read-only,
 * a listing narrowed to one organisation, a field a role may see but not
 * change. Each is a plain method returning plain data, which is what makes the
 * whole authorization model testable without a request.
 */
final class PanelResourceTest extends TestCase
{
    private function resource(): PanelResource
    {
        return new class extends PanelResource {
            public function key(): string { return 'events'; }
            public function entityClass(): string { return \stdClass::class; }
            public function listQueryClass(): string { return StubListQuery::class; }
            public function present(array $entities): array { return []; }
        };
    }

    /** The base Permissions allow everything and name no role — gated by routes alone. */
    public function testAResourceIsUngatedByDefault(): void
    {
        $permissions = $this->resource()->permissions();

        self::assertSame(Permissions::class, $permissions::class);
        self::assertSame([], $permissions->roles());
        self::assertTrue($permissions->create(null));
    }

    public function testAResourceDeclaresNoFormFieldsByDefault(): void
    {
        // The route loader gates create/edit/update/delete on this being
        // non-null, so the default is a read-only, index-and-show resource.
        self::assertNull($this->resource()->formFields());
    }

    public function testTheGenericPageIsTheDefaultComponent(): void
    {
        self::assertSame('Resource/Index', $this->resource()->indexComponent());
    }

    /** 'events' → 'event': the DrawerStack slot a record renders into. */
    public function testTheDrawerTypeIsDerivedFromTheKey(): void
    {
        self::assertSame('event', $this->resource()->drawerType());
    }

    public function testAResourceDeclaresNoDrawerTabsByDefault(): void
    {
        self::assertSame([], $this->resource()->drawerTabs());
    }

    public function testTableSchemaIsAbsentUntilDeclared(): void
    {
        self::assertNull($this->resource()->tableSchema());
    }

    public function testADeclaredSchemaIsReturnedAsIs(): void
    {
        $resource = new class extends PanelResource {
            public function key(): string { return 'events'; }
            public function entityClass(): string { return \stdClass::class; }
            public function listQueryClass(): string { return StubListQuery::class; }
            public function present(array $entities): array { return []; }

            public function tableSchema(): TableSchema
            {
                return TableSchema::make()->recordUrl('/panel/events/{id}');
            }
        };

        self::assertSame(
            '/panel/events/{id}',
            $resource->tableSchema()->toArray(StubListQuery::class)['recordUrl'],
            'The declaration reaches the client untouched.',
        );
    }
}
