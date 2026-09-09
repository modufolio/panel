<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Modufolio\Panel\Form\Field;
use Modufolio\Panel\Form\Form;
use Modufolio\Panel\Form\FormResolver;
use Modufolio\Panel\Inspection\SchemaInspector;
use Modufolio\Panel\Inspection\SchemaReport;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\Permissions;
use Modufolio\Panel\Table\Column;
use Modufolio\Panel\Table\TableSchema;
use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;
use Modufolio\Panel\Tests\Fixture\MovieResource;

/**
 * The resolved schema read back with its sources: the file a generator
 * would have written, so a column that renders wrong can be traced to the
 * layer that decided it without stepping through the request.
 */
final class SchemaInspectorTest extends DoctrineTestCase
{
    private function inspect(PanelResource $resource, string $options = '', ?object $user = null): SchemaReport
    {
        $routes = $this->routesFromConfig(
            'function (PanelResourceConfigurator $panel): void { $panel->resource(\\' . MovieResource::class . '::class)' . $options . '; }',
        );

        $inspector = new SchemaInspector(
            $routes,
            static fn (string $class): PanelResource => $resource,
            new FormResolver(self::em()),
            self::em(),
        );

        return $inspector->inspect(MovieResource::class, $user);
    }

    /** A resource with one column per source. */
    private static function traced(): MovieResource
    {
        return new class extends MovieResource {
            public function fields(): array
            {
                return [Field::make('year')->label('Released in')];
            }

            public function table(): TableSchema
            {
                return TableSchema::make()->columns([
                    Column::make('title')->label('Film')->type('text'),
                    Column::make('year'),
                    Column::make('released_on'),
                    Column::make('rating')->editable(),
                ]);
            }

            public function form(): Form
            {
                return Form::make()->fields([
                    'title',
                    Field::make('year')->number(),
                    'released_on',
                ]);
            }
        };
    }

    /** @return array<string, array<string, mixed>> */
    private static function columns(SchemaReport $report): array
    {
        return array_column($report->table['columns'] ?? [], null, 'key');
    }

    public function testEachColumnNamesTheLayerThatDecidedItsLabelAndType(): void
    {
        $columns = self::columns($this->inspect(self::traced()));

        self::assertSame(['Film', 'column', 'column'], [$columns['title']['label'], $columns['title']['labelSource'], $columns['title']['typeSource']]);
        self::assertSame(['Released in', 'fields'], [$columns['year']['label'], $columns['year']['labelSource']]);
        self::assertSame(['Released on', 'default'], [$columns['released_on']['label'], $columns['released_on']['labelSource']]);
        self::assertSame(['date', 'mapping'], [$columns['released_on']['type'], $columns['released_on']['typeSource']], 'A date column read off the mapping.');
        self::assertSame('default', $columns['year']['typeSource'], 'Nothing declared, nothing guessed.');
    }

    public function testAnEditableColumnSaysWhetherTheViewerKeepsTheControl(): void
    {
        $allowed = self::columns($this->inspect(self::traced()));
        self::assertSame([true, 'column'], [$allowed['rating']['editable'], $allowed['rating']['editableSource']]);

        $frozen = new class extends MovieResource {
            public function table(): TableSchema
            {
                return TableSchema::make()->columns([Column::make('rating')->editable()]);
            }

            public function permissions(): Permissions
            {
                return new class extends Permissions {
                    public function writable(string $field, ?object $user, ?object $record = null): bool
                    {
                        return false;
                    }
                };
            }
        };

        $refused = self::columns($this->inspect($frozen));
        self::assertSame([false, 'permissions'], [$refused['rating']['editable'], $refused['rating']['editableSource']]);
        self::assertNull($allowed['title']['editableSource'], 'Never editable: nothing to trace.');
    }

    public function testTheRecordUrlAndActionsSayWhetherTheyCameFromTheTableOrTheRoutes(): void
    {
        $table = $this->inspect(self::traced())->table;
        self::assertNotNull($table);

        self::assertSame('route', $table['recordUrlSource']);
        self::assertStringContainsString('{id}', (string) $table['recordUrl']);
        self::assertSame(['view', 'edit', 'delete'], $table['actions']);
        self::assertSame('route', $table['actionsSource']);

        $readOnly = $this->inspect(self::traced(), "->only(['index'])");
        $table    = $readOnly->table;
        self::assertNotNull($table);

        self::assertNull($table['recordUrl'], 'No show route, no declared URL: nothing links.');
        self::assertNull($table['recordUrlSource']);
        self::assertSame([], $table['actions']);
        self::assertSame(['create' => false, 'edit' => false, 'delete' => false, 'move' => false, 'export' => true], $readOnly->capabilities);
    }

    public function testEachFormFieldNamesTheLayerThatDecidedIt(): void
    {
        $fields = array_column($this->inspect(self::traced())->form ?? [], null, 'key');

        self::assertSame(['default', 'mapping'], [$fields['title']['labelSource'], $fields['title']['typeSource']]);
        self::assertSame(['fields', 'form'], [$fields['year']['labelSource'], $fields['year']['typeSource']]);
        self::assertSame('Released in', $fields['year']['label']);
        self::assertTrue($fields['title']['writable']);
    }

    public function testAResourceWithoutATableOrFormReportsNeither(): void
    {
        $bare = new class extends PanelResource {
            public function key(): string
            {
                return 'movies';
            }

            public function entityClass(): string
            {
                return Movie::class;
            }
        };

        $report = $this->inspect($bare);

        self::assertNull($report->table);
        self::assertNull($report->form);
        self::assertSame('movies', $report->toArray()['key']);
    }
}
