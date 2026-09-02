<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Resource;

use Modufolio\Panel\Resource\PanelResource;
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

    public function testAResourceAllowsEverythingByDefault(): void
    {
        $resource = $this->resource();

        self::assertTrue($resource->canCreate());
        self::assertTrue($resource->canEdit());
        self::assertTrue($resource->canDelete());
    }

    public function testNothingIsReadOnlyByDefault(): void
    {
        self::assertSame([], $this->resource()->readonlyFields());
    }

    public function testAResourceDeclaresNoFormFieldsByDefault(): void
    {
        // The route loader gates create/edit/update/delete on this being
        // non-null, so the default is a read-only, index-and-show resource.
        self::assertNull($this->resource()->formFieldKeys());
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

    public function testAReadOnlyResourceRefusesEveryWrite(): void
    {
        $resource = new class extends PanelResource {
            public function key(): string { return 'events'; }
            public function entityClass(): string { return \stdClass::class; }
            public function listQueryClass(): string { return StubListQuery::class; }
            public function present(array $entities): array { return []; }

            public function canCreate(?object $user = null): bool { return false; }
            public function canEdit(?object $record = null, ?object $user = null): bool { return false; }
            public function canDelete(?object $record = null, ?object $user = null): bool { return false; }
        };

        self::assertFalse($resource->canCreate());
        self::assertFalse($resource->canEdit());
        self::assertFalse($resource->canDelete());
    }

    /**
     * Field-level permission is per record *and* per user — "this role may not
     * change the price on a closed order" is one expression, not a policy
     * class plus a form rebuild.
     */
    public function testReadonlyFieldsCanDependOnBothTheRecordAndTheUser(): void
    {
        $resource = new class extends PanelResource {
            public function key(): string { return 'orders'; }
            public function entityClass(): string { return \stdClass::class; }
            public function listQueryClass(): string { return StubListQuery::class; }
            public function present(array $entities): array { return []; }

            public function readonlyFields(?object $record = null, ?object $user = null): array
            {
                if (($record->closed ?? false) === true) {
                    return ['price', 'quantity'];
                }

                return ($user->role ?? null) === 'viewer' ? ['price'] : [];
            }
        };

        self::assertSame([], $resource->readonlyFields((object) ['closed' => false], (object) ['role' => 'admin']));
        self::assertSame(['price'], $resource->readonlyFields((object) ['closed' => false], (object) ['role' => 'viewer']));
        self::assertSame(['price', 'quantity'], $resource->readonlyFields((object) ['closed' => true], (object) ['role' => 'admin']));
    }

    public function testScopeQueryDoesNothingUnlessAResourceNarrowsIt(): void
    {
        $query = new \stdClass();

        // The base hook must leave the query untouched — a resource that does
        // not scope sees everything, which is the documented default.
        $this->resource()->scopeQuery($query, null);

        self::assertEquals(new \stdClass(), $query);
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
