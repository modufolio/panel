<?php

declare(strict_types=1);

namespace Modufolio\Panel\Resource;

use Doctrine\ORM\QueryBuilder;

/**
 * Who may do what with a resource, in one class the application writes.
 *
 * Every answer here is behaviour rather than declaration — a rule with a
 * typed record and a typed user, testable without a resource, reusable
 * through a base class, and free to take a service in its constructor. That
 * is why it is a class of its own and not a set of hooks on the resource or
 * closures in a builder: {@see \Modufolio\Panel\Inspection\PermissionInspector}
 * interrogates it directly, and a `TenantPermissions` base can carry the row
 * scope for every resource that shares it.
 *
 * The base class allows everything, so a resource with nothing to refuse
 * beyond its route roles returns `new Permissions(['ROLE_USER'])` and is
 * done. The layers, coarsest first:
 *
 *  - {@see roles()}: enforced by the kernel on every generated route, before
 *    the controller runs. The one layer that never sees a record.
 *  - {@see view()}, {@see create()}, {@see edit()}, {@see delete()},
 *    {@see export()}: per operation, about the type (no record) or one record.
 *  - {@see scope()}: per row — what the listing can see at all. The only
 *    layer that also fixes counts, pagination, search and record lookup.
 *  - {@see readable()}, {@see writable()}: per field, per user, and per
 *    record when there is one. A field this user may not read is never
 *    serialised; one they may not write renders disabled and has its
 *    submitted value dropped.
 *  - {@see move()}: per card, which board lanes it may be dragged into.
 *
 * The user is passed in rather than fetched, and typed as `?object`: the
 * package does not know the application's user class, and null means nobody
 * is signed in.
 */
class Permissions
{
    /**
     * @param list<string> $roles roles the generated routes require; any one
     *                            of them admits. Empty means the routes name
     *                            no role and every signed-in user reaches them.
     */
    public function __construct(
        private readonly array $roles = [],
    ) {
    }

    /**
     * Stored on every generated route as `_is_granted_roles`, the same default
     * `#[IsGranted]` writes, so the kernel enforces it with the role hierarchy
     * before the controller is reached.
     *
     * @return list<string>
     */
    public function roles(): array
    {
        return $this->roles;
    }

    /** May this user see the listing at all, or this record in particular? */
    public function view(?object $record, ?object $user): bool
    {
        return true;
    }

    public function create(?object $user): bool
    {
        return true;
    }

    public function edit(?object $record, ?object $user): bool
    {
        return true;
    }

    public function delete(?object $record, ?object $user): bool
    {
        return true;
    }

    /** Downloading the list is reading the list, unless a resource says otherwise. */
    public function export(?object $user): bool
    {
        return $this->view(null, $user);
    }

    /**
     * Narrow what the listing can see at all.
     *
     * The counterpart to {@see view()}: that one answers about a record
     * already in hand, this one keeps records out of reach entirely — which is
     * also the only version that fixes counts, pagination and search. A record
     * excluded here is not merely hidden; {@see RecordLocator} cannot load it
     * by URL either, so out of scope reads as not found.
     *
     * @param string $alias the query's root alias, so a scope can name columns
     */
    public function scope(QueryBuilder $qb, string $alias, ?object $user): void
    {
    }

    /**
     * May this user see the field at all? A field they may not read is
     * removed from the serialised form entirely — never shipped, not merely
     * not rendered — and a fortiori not writable.
     *
     * Null record means the type, or a create form with no record yet.
     */
    public function readable(string $field, ?object $user, ?object $record = null): bool
    {
        return true;
    }

    /**
     * May this user change the field? One answer for both questions the
     * package used to ask apart — "frozen on this record" and "off limits to
     * this role" — because a request answers neither by itself. The client
     * renders the field disabled; the server drops the submitted value.
     */
    public function writable(string $field, ?object $user, ?object $record = null): bool
    {
        return true;
    }

    /**
     * Whether this record may be dragged into that board lane.
     *
     * Returns true to allow, or a message explaining the refusal — which the
     * board shows and then puts the card back where it came from. The default
     * allows every move, because most boards are a plain grouping and dragging
     * is just editing that field. Override where the lane is a workflow state
     * and not every hop between them is legal.
     */
    public function move(object $record, string $lane, ?object $user): bool|string
    {
        return true;
    }
}
