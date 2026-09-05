# modufolio/panel

[![CI](https://img.shields.io/github/actions/workflow/status/modufolio/panel/ci.yml?branch=main&style=flat-square&label=CI)](https://github.com/modufolio/panel/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat-square)](https://phpstan.org/)
[![Packagist](https://img.shields.io/packagist/v/modufolio/panel?style=flat-square)](https://packagist.org/packages/modufolio/panel)
[![License: MIT](https://img.shields.io/badge/License-MIT-brightgreen.svg?style=flat-square)](https://opensource.org/licenses/MIT)

The PHP half of the Modufolio panel: resources, table schemas, drawers and
blueprint forms, emitted as **plain data** for
[`@modufolio/panel`](https://www.npmjs.com/package/@modufolio/panel) to render.

Nothing here renders HTML. A resource declares what a listing *is* — its
columns, filters, drawer tabs, permissions — and the package serialises that to
JSON. How it reaches a browser is the host application's business, expressed
through two small interfaces.

```php
final class EventResource extends PanelResource
{
    public function key(): string            { return 'events'; }
    public function entityClass(): string    { return Event::class; }
    public function menu(): Menu             { return Menu::make('Events', icon: 'calendar'); }

    public function table(): TableSchema
    {
        return TableSchema::make()
            ->filters([Filter::select('type')->options(EventType::class)])
            ->defaultSort('startsAt')
            ->columns([
                Column::make('title')->searchable()->linksToRecord()->weight('medium'),
                Column::make('when')->value('starts_at')->linksToRecord(),
            ]);
    }
}
```

No presenter and no list query class: the rows are read off the entity
through the columns (`when` reads `getStartsAt()`), and sorting, search, the
default order and the soft-delete scope come from the table, built from the
same query objects a hand-written `AbstractListQuery` chains. Override
`present()` or name a class with `listQueryClass()` when a resource needs
what its columns cannot say.

## What the host must provide

Two interfaces, bound in the application's container:

| Interface | Answers |
|---|---|
| `Contracts\SharedPropsInterface` | the props every page carries — auth, flash, navigation, CSRF |
| `Contracts\PageRendererInterface` | how a component name and props become a response |

The package never names Inertia, a template engine or a session. That is what
lets one panel serve several applications that answer those questions
differently.

Every generated route dispatches to `Http\ResourceController`, which the
package ships: index, show, create, store, edit, update, destroy, bulk delete,
delete preview, export, relation lookups and board moves. It is an appkit
`AppAwareInterface` controller, so there is nothing to wire: the kernel hands
it the application and it pulls what it needs. It reads the two interfaces
above from the container, and, when registered, a
`Contracts\ExportAdapterProviderInterface` for downloads and a `FormResolver`
naming the media entity. There is no controller to write.

## Authorization

One class per resource, extending `Resource\Permissions`, whose every method
answers "yes" until overridden. The resource returns it from `permissions()`,
so a rule that needs a service takes it through the resource's constructor:

```php
final class EventPermissions extends Permissions
{
    public function __construct() { parent::__construct(['ROLE_USER']); }

    public function delete(?object $record, ?object $user): bool { return false; }

    public function scope(QueryBuilder $qb, string $alias, ?object $user): void
    {
        $qb->andWhere("{$alias}.tenant = :t")->setParameter('t', $user?->tenant());
    }

    public function writable(string $field, ?object $user, ?object $record = null): bool
    {
        return $field !== 'notes' || $user?->isAdmin() === true;
    }
}
```

| Layer | Method |
|---|---|
| Route | `roles()` — stored on every generated route, enforced by the kernel |
| Operation | `view()` / `create()` / `edit()` / `delete()` / `export()` |
| Row | `scope($qb, $alias, $user)` — what the listing and the record lookup can see at all |
| Field | `readable($field, $user, $record)` / `writable($field, $user, $record)` — per user, and per record when there is one |
| Board | `move($record, $lane, $user)` — which drags a workflow allows |

A field this user may not read is never serialised; one they may not write
renders disabled and has its submitted value dropped, so the disabled input is
presentation and the server is the guard. See
[docs/fields.md](docs/fields.md#per-field-access).

> The guards run inside the package's own form services: `FormPresenter`
> applies the read side when it serialises a form, and `SubmissionHandler`
> applies the write side, in a fixed order, before anything is validated. A
> host that routes its writes through `SubmissionHandler` gets them for free;
> a hand-written write path has to reproduce that order itself, or it yields a
> form that looks guarded and is not —
> [the wiring](docs/fields.md#wiring-the-guards).

## The write side

A generated create, edit or delete route needs more than the declaration,
and the package provides it as plain services a host controller composes:

| Service | Answers |
|---|---|
| `Form\FormResolver` | which form a resource has — hand-written or guessed from Doctrine |
| `Form\FormPresenter` | the fields this viewer may see, relations resolved, computed values filled |
| `Form\SubmissionHandler` | a request body to a persisted record, or errors keyed by field |
| `Delete\PlanExecutor` | carrying out what the delete `Collector` planned, in one transaction |
| `Resource\RecordLocator` | the record a URL names, through the resource's own scope |

What stays in the host is HTTP: who is asking, and which redirect or JSON a
refusal, a validation failure or a success becomes.

## Read-only resources

Create, edit, update and delete routes are generated only when `form()`
returns non-null. A resource that declares no form is index-and-show only,
with no configuration.

## Development

The package is developed beside its sibling packages and resolves them through
composer path repositories, so it installs and tests on its own:

```bash
composer install
composer test        # unit and database suites, SQLite in memory
composer stan        # PHPStan, level 8
```

The `Database` suite runs the listing, guesser, relation and delete
machinery against a real EntityManager over the fixture entities in
`tests/Fixture/Entity`. It needs no setup: SQLite in memory is the default.
The same `DB_*` variables the sibling packages read point it at a real engine,
and `docker-compose.yml` has one of each ready:

```bash
docker compose up -d mysql postgres
DB_DRIVER=pdo_mysql DB_PORT=3309 DB_USER=root DB_PASSWORD=secret composer test:db
DB_DRIVER=pdo_pgsql DB_PORT=5435 DB_USER=postgres DB_PASSWORD=secret composer test:db
```

CI runs that suite against MySQL 8.4, PostgreSQL 16 and SQL Server 2022 on
every push.

Those `repositories` entries apply only when this package is the root; a
consuming application resolves the siblings its own way.

## Documentation

- [docs/adding-a-resource.md](docs/adding-a-resource.md) — the recipe, and the
  failures that are silent
- [docs/graduating-a-resource.md](docs/graduating-a-resource.md) — the ladder
  from a generated resource to a fully custom page, one rung at a time
- [docs/panel-resources.md](docs/panel-resources.md) — why resources are
  composed rather than inherited, and what `ResourceListing` emits
- [docs/fields.md](docs/fields.md) — blueprint forms: field types, conditions,
  defaults, per-field access, and the guards an application has to call
- [docs/table-schema.md](docs/table-schema.md) — columns, filters, groups,
  constraints
