<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Resource;

use Modufolio\Appkit\Security\User\UserInterface;
use Modufolio\Panel\Form\Field;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\Permissions;
use Modufolio\Panel\Resource\ResourceCapabilities;
use Modufolio\Panel\Resource\SchemaResolver;
use Modufolio\Panel\Table\Column;
use Modufolio\Panel\Table\Filter;
use Modufolio\Panel\Table\RowAction;
use Modufolio\Panel\Table\TableSchema;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;
use Modufolio\Panel\Tests\Fixture\MovieResource;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The schema pipeline, one step at a time, without a database: what a
 * declaration left out is filled from `fields()`, the routes and the
 * viewer's permissions, and never over what it said.
 */
final class SchemaResolverTest extends TestCase
{
    /** @param list<string> $operations */
    private static function routes(array $operations): UrlGenerator
    {
        $routes = new RouteCollection();

        foreach ($operations as $operation) {
            $name = $operation === 'index' ? 'movies' : 'movies_' . $operation;
            $path = in_array($operation, ['index', 'create', 'store', 'bulk_destroy', 'export'], true)
                ? '/panel/movies/' . $operation
                : '/panel/movies/{uuid}/' . $operation;

            $routes->add($name, new Route($path));
        }

        return new UrlGenerator($routes, new RequestContext());
    }

    /**
     * @param list<string> $operations
     */
    private static function resolver(MovieResource $resource, array $operations, ?Permissions $permissions = null): SchemaResolver
    {
        $resource = $permissions === null ? $resource : new class ($permissions, $resource) extends MovieResource {
            public function __construct(private readonly Permissions $permissions, private readonly MovieResource $inner)
            {
            }

            public function permissions(): Permissions
            {
                return $this->permissions;
            }

            public function fields(): array
            {
                return $this->inner->fields();
            }

            public function table(): TableSchema
            {
                return $this->inner->table();
            }

            public function defaultTrashed(): ?string
            {
                return $this->inner->defaultTrashed();
            }
        };

        return new SchemaResolver(new ResourceCapabilities($resource, self::routes($operations)));
    }

    private static function labelled(): MovieResource
    {
        return new class extends MovieResource {
            public function fields(): array
            {
                return [Field::make('title')->label('Film title'), Field::make('year')->label('Released in')];
            }

            public function table(): TableSchema
            {
                return TableSchema::make()->columns([
                    Column::make('title'),
                    Column::make('year')->label('Year'),
                ]);
            }
        };
    }

    // ── Labels ───────────────────────────────────────────────────────────────

    public function testAColumnWithoutALabelTakesTheOneFieldsDeclares(): void
    {
        $resource = self::labelled();
        $schema   = self::resolver($resource, ['index'])->resolveLabels($resource->table());

        $labels = array_column($schema->toArray()['columns'], 'label', 'key');

        self::assertSame('Film title', $labels['title'], 'Nothing declared on the column: fields() wins.');
        self::assertSame('Year', $labels['year'], 'The column said so itself: fields() does not override it.');
    }

    // ── Record URL ───────────────────────────────────────────────────────────

    public function testTheShowRouteBecomesTheRecordUrlUnlessOneWasDeclared(): void
    {
        $resource = new MovieResource();

        $derived = self::resolver($resource, ['index', 'show'])->resolveRecordUrl($resource->table());
        self::assertSame('/panel/movies/{id}/show', $derived->declaredRecordUrl());

        $declared = self::resolver($resource, ['index', 'show'])->resolveRecordUrl($resource->table()->recordUrl('/elsewhere/{id}'));
        self::assertSame('/elsewhere/{id}', $declared->declaredRecordUrl());
    }

    public function testAColumnLinkingToTheRecordWithNowhereToGoIsRefused(): void
    {
        $resource = new MovieResource();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Column "title" links to the record');

        self::resolver($resource, ['index'])->resolveRecordUrl($resource->table());
    }

    // ── Filter defaults ──────────────────────────────────────────────────────

    public function testTheResourcesTrashedDefaultReachesTheTrashedFilter(): void
    {
        $resource = new class extends MovieResource {
            public function defaultTrashed(): string
            {
                return 'with';
            }
        };

        $schema  = self::resolver($resource, ['index'])->resolveFilterDefaults($resource->table());
        $trashed = array_values(array_filter($schema->declaredFilters(), static fn (Filter $f): bool => $f->type() === Filter::TRASHED))[0];

        self::assertSame('with', $trashed->defaultValue());
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    public function testNothingDeclaredDerivesTheTrioFromTheRoutesThatExist(): void
    {
        $resource = new MovieResource();
        $schema   = self::resolver($resource, ['index', 'show', 'edit', 'destroy', 'delete_preview', 'bulk_destroy'])
            ->resolveActions($resource->table());

        $actions = $schema->toArray()['actions'];

        self::assertSame(['view', 'edit', 'delete'], array_column($actions, 'name'));
        self::assertSame('/panel/movies/{id}/edit', $actions[1]['urlTemplate']);
        self::assertSame('/panel/movies/{id}/delete_preview', $actions[2]['previewUrl']);
        self::assertNotSame([], $schema->toArray()['bulkActionItems'], 'Bulk delete derived from the bulk_destroy route.');
    }

    public function testARouteThatWasNotGeneratedOffersNoAction(): void
    {
        $resource = new MovieResource();
        $schema   = self::resolver($resource, ['index', 'show'])->resolveActions($resource->table());

        self::assertSame(['view'], array_column($schema->toArray()['actions'], 'name'));
        self::assertSame([], $schema->toArray()['bulkActionItems']);
    }

    public function testAViewerWhoMayNotDeleteIsNotOfferedDeleteOrRestore(): void
    {
        $noDelete = new class extends Permissions {
            public function delete(?object $record, ?UserInterface $user): bool
            {
                return false;
            }
        };

        $derived = self::resolver(new MovieResource(), ['index', 'edit', 'destroy', 'bulk_destroy'], $noDelete)
            ->resolveActions((new MovieResource())->table());
        self::assertSame(['edit'], array_column($derived->toArray()['actions'], 'name'));
        self::assertSame([], $derived->toArray()['bulkActionItems'], 'Bulk delete rides the delete permission.');

        $declared = self::resolver(new MovieResource(), ['index'], $noDelete)->resolveActions(
            (new MovieResource())->table()->actions([RowAction::edit('/e/{id}'), RowAction::delete('/d/{id}'), RowAction::make('restore')->url('/r/{id}')]),
        );
        self::assertSame(['edit'], array_column($declared->toArray()['actions'], 'name'), 'Declared actions are gated on permission only.');
    }

    // ── Editability ──────────────────────────────────────────────────────────

    public function testAControlTheViewerMayNotWriteIsNotDrawn(): void
    {
        $permissions = new class extends Permissions {
            public function writable(string $field, ?UserInterface $user, ?object $record = null): bool
            {
                return $field !== 'rating';
            }
        };

        $schema = TableSchema::make()->columns([
            Column::make('title')->editable(),
            Column::make('rating')->editable(),
            Column::make('year'),
        ]);

        $resolved = self::resolver(new MovieResource(), ['index'], $permissions)->resolveEditable($schema);
        $editable = array_column($resolved->toArray()['columns'], 'editable', 'key');

        self::assertTrue($editable['title']);
        self::assertFalse($editable['rating'], 'The same writable() the patch endpoint asks, asked before the control exists.');
        self::assertFalse($editable['year']);
    }

    public function testAViewerWhoMayNotEditTheTypeGetsNoInlineControlAtAll(): void
    {
        $permissions = new class extends Permissions {
            public function edit(?object $record, ?UserInterface $user): bool
            {
                return false;
            }
        };

        $schema   = TableSchema::make()->columns([Column::make('title')->editable()]);
        $resolved = self::resolver(new MovieResource(), ['index'], $permissions)->resolveEditable($schema);

        self::assertFalse($resolved->toArray()['columns'][0]['editable']);
    }

    // ── Serialisation ────────────────────────────────────────────────────────

    public function testAResourceOnTheDefaultPresenterSendsNoValueKey(): void
    {
        $schema = static fn (): TableSchema => TableSchema::make()->columns([Column::make('studio')->value('studio.name')]);

        // The fixture presents its own rows, so the path travels with the column.
        self::assertSame('studio.name', self::resolver(new MovieResource(), ['index'])->serialise($schema())['columns'][0]['valueKey']);

        // On the default presenter the value already sits under the column's key.
        $default = new class extends PanelResource {
            public function key(): string
            {
                return 'movies';
            }

            public function entityClass(): string
            {
                return Movie::class;
            }
        };
        $resolver = new SchemaResolver(new ResourceCapabilities($default, self::routes(['index'])));

        self::assertArrayNotHasKey('valueKey', $resolver->serialise($schema())['columns'][0]);
    }
}
