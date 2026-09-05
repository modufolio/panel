<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Resource;

use Modufolio\Panel\Resource\Permissions;
use PHPUnit\Framework\TestCase;

/**
 * The base class allows everything, so a resource that declares nothing is
 * gated by its routes alone — and a class that refuses one thing inherits
 * "yes" for all the rest.
 */
final class PermissionsTest extends TestCase
{
    public function testTheBaseAllowsEverythingAndNamesNoRole(): void
    {
        $permissions = new Permissions();
        $record      = new \stdClass();
        $user        = new \stdClass();

        self::assertSame([], $permissions->roles());
        self::assertTrue($permissions->view(null, $user));
        self::assertTrue($permissions->view($record, $user));
        self::assertTrue($permissions->create($user));
        self::assertTrue($permissions->edit($record, $user));
        self::assertTrue($permissions->delete($record, $user));
        self::assertTrue($permissions->export($user));
        self::assertTrue($permissions->readable('title', $user));
        self::assertTrue($permissions->writable('title', $user, $record));
        self::assertTrue($permissions->move($record, 'done', $user));
    }

    public function testRolesAreWhatTheConstructorWasGiven(): void
    {
        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], (new Permissions(['ROLE_USER', 'ROLE_ADMIN']))->roles());
    }

    /** Downloading the list is reading the list: export follows view unless overridden. */
    public function testExportFollowsView(): void
    {
        $permissions = new class extends Permissions {
            public function view(?object $record, ?object $user): bool
            {
                return false;
            }
        };

        self::assertFalse($permissions->export(new \stdClass()));
    }

    /**
     * One rule may read the record and the user: "this role may not change the
     * price on a closed order" is one method, not a policy class plus a form
     * rebuild.
     */
    public function testWritableMayDependOnBothTheRecordAndTheUser(): void
    {
        $permissions = new class extends Permissions {
            public function writable(string $field, ?object $user, ?object $record = null): bool
            {
                if (($record->closed ?? false) === true) {
                    return !in_array($field, ['price', 'quantity'], true);
                }

                return !($field === 'price' && ($user->role ?? null) === 'viewer');
            }
        };

        $open   = (object) ['closed' => false];
        $closed = (object) ['closed' => true];
        $admin  = (object) ['role' => 'admin'];
        $viewer = (object) ['role' => 'viewer'];

        self::assertTrue($permissions->writable('price', $admin, $open));
        self::assertFalse($permissions->writable('price', $viewer, $open));
        self::assertFalse($permissions->writable('quantity', $admin, $closed));
        self::assertTrue($permissions->writable('title', $viewer, $closed));
    }
}
