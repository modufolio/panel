<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Resource;

use Modufolio\Panel\Form\Field;
use Modufolio\Panel\Resource\Drawer;
use Modufolio\Panel\Resource\DrawerTab;
use Modufolio\Panel\Resource\Menu;
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

    public function testAResourceDeclaresNoFormByDefault(): void
    {
        // The route loader gates create/edit/update/delete on this being
        // non-null, so the default is a read-only, index-and-show resource.
        self::assertNull($this->resource()->form());
    }

    /** The drawer's key list is labelled from fields(), so the drawer shows a subset of the form without saying anything twice. */
    public function testDrawerTabsForLabelsListedKeysFromTheSharedFields(): void
    {
        $resource = new class extends PanelResource {
            public function key(): string { return 'events'; }
            public function entityClass(): string { return \stdClass::class; }
            public function listQueryClass(): string { return StubListQuery::class; }
            public function present(array $entities): array { return []; }

            public function fields(): array
            {
                return [Field::make('starts_at')->date()->label('When')];
            }

            public function drawer(): Drawer
            {
                return Drawer::make()->tabs([DrawerTab::details()->fields(['title', 'starts_at'])]);
            }
        };

        $tabs = $resource->drawerTabsFor(['title' => 'Gala', 'starts_at' => '2026-09-05']);

        self::assertSame(['title' => null, 'starts_at' => 'When'], $tabs[0]['fields']);
        self::assertSame(['starts_at' => 'When'], $resource->fieldLabels());
    }

    public function testAResourceHasNoMenuEntryByDefault(): void
    {
        self::assertNull($this->resource()->menu());
        self::assertSame(
            ['label' => 'Events', 'icon' => 'calendar', 'group' => 'Main', 'order' => 16],
            Menu::make('Events', icon: 'calendar', group: 'Main', order: 16)->toArray(),
        );
        self::assertSame(['label' => 'Events', 'icon' => null, 'group' => null, 'order' => 50], Menu::make('Events')->toArray(), 'Only the label is required.');
    }

    public function testAMenuEntryWithoutALabelIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Menu::make('  ');
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

    public function testAResourceDeclaresAnEmptyDrawerByDefault(): void
    {
        self::assertSame([], $this->resource()->drawer()->declaredTabs());
    }

    /** No class named: the listing derives the query from the table. */
    public function testAResourceNamesNoListQueryClassByDefault(): void
    {
        $resource = new class extends PanelResource {
            public function key(): string { return 'events'; }
            public function entityClass(): string { return \stdClass::class; }
            public function present(array $entities): array { return []; }
        };

        self::assertNull($resource->listQueryClass());
        self::assertSame([], $resource->queries([]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('names no list query class');
        $resource->buildListQuery([], null, null);
    }

    public function testTheTableIsAbsentUntilDeclared(): void
    {
        self::assertNull($this->resource()->table());
    }

    public function testADeclaredSchemaIsReturnedAsIs(): void
    {
        $resource = new class extends PanelResource {
            public function key(): string { return 'events'; }
            public function entityClass(): string { return \stdClass::class; }
            public function listQueryClass(): string { return StubListQuery::class; }
            public function present(array $entities): array { return []; }

            public function table(): TableSchema
            {
                return TableSchema::make()->recordUrl('/panel/events/{id}');
            }
        };

        self::assertSame(
            '/panel/events/{id}',
            $resource->table()->toArray(new StubListQuery())['recordUrl'],
            'The declaration reaches the client untouched.',
        );
    }
}
