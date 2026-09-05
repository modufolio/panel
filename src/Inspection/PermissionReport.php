<?php

declare(strict_types=1);

namespace Modufolio\Panel\Inspection;

/**
 * What {@see PermissionInspector} found, as plain arrays a console command
 * can tabulate and a page can render.
 *
 * @phpstan-type RoleVerdict array{
 *     routes: array<string, bool>,
 *     can: array{view: bool, create: bool, edit: bool, delete: bool},
 *     fields: array{readable: list<string>, readDenied: list<string>, writeDenied: list<string>}
 * }
 * @phpstan-type ResourceEntry array{
 *     key: string,
 *     class: class-string,
 *     prefix: string|null,
 *     routes: list<string>,
 *     permissions: class-string,
 *     overrides: array{view: bool, create: bool, edit: bool, delete: bool, scope: bool, readable: bool, writable: bool, move: bool},
 *     roles: array<string, RoleVerdict>
 * }
 * @phpstan-type Note array{kind: string, resource: string, role: string|null, message: string}
 */
final readonly class PermissionReport
{
    /**
     * @param list<string>                 $roles     the roles inspected, in order
     * @param array<string, ResourceEntry> $resources keyed by resource key
     * @param list<Note>                   $notes     divergences worth a human's attention
     */
    public function __construct(
        public array $roles,
        public array $resources,
        public array $notes,
    ) {
    }

    /** @return array{roles: list<string>, resources: array<string, ResourceEntry>, notes: list<Note>} */
    public function toArray(): array
    {
        return [
            'roles'     => $this->roles,
            'resources' => $this->resources,
            'notes'     => $this->notes,
        ];
    }
}
