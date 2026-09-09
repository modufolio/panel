<?php

declare(strict_types=1);

namespace Modufolio\Panel\Inspection;

/**
 * What {@see SchemaInspector} found for one resource and one viewer, as
 * plain arrays a console command can tabulate and a page can render.
 *
 * Every `*Source` value names the layer that decided the thing beside it:
 * `column` / `form` (the part said so), `fields` (the resource's shared
 * declaration), `mapping` (Doctrine's metadata), `route` (a generated route),
 * `permissions` (this viewer's rules), `default` (nothing said anything).
 *
 * @phpstan-type ColumnEntry array{
 *     key: string,
 *     label: string,
 *     labelSource: string,
 *     type: string,
 *     typeSource: string,
 *     options: string|null,
 *     editable: bool,
 *     editableSource: string|null
 * }
 * @phpstan-type FieldEntry array{
 *     key: string,
 *     label: string,
 *     labelSource: string,
 *     type: string,
 *     typeSource: string,
 *     readable: bool,
 *     writable: bool
 * }
 * @phpstan-type TableEntry array{
 *     columns: list<ColumnEntry>,
 *     recordUrl: string|null,
 *     recordUrlSource: string|null,
 *     actions: list<string>,
 *     actionsSource: string,
 *     filters: list<array{key: string, type: string, relation: string|null}>
 * }
 */
final readonly class SchemaReport
{
    /**
     * @param array<string, bool>         $capabilities create / edit / delete / move / export, for this viewer
     * @param array<string, string|null>  $urls         every generated route by operation, null where absent
     * @param TableEntry|null             $table        null when the resource declares no table
     * @param list<FieldEntry>|null       $form         null when the resource declares no form
     */
    public function __construct(
        public string $key,
        public string $class,
        public string $permissions,
        public array $capabilities,
        public array $urls,
        public ?array $table,
        public ?array $form,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key'          => $this->key,
            'class'        => $this->class,
            'permissions'  => $this->permissions,
            'capabilities' => $this->capabilities,
            'urls'         => $this->urls,
            'table'        => $this->table,
            'form'         => $this->form,
        ];
    }
}
