# modufolio/panel

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
    public function listQueryClass(): string { return EventListQuery::class; }

    public function present(array $entities): array
    {
        return EventPresenter::collection($entities);
    }

    public function tableSchema(): TableSchema
    {
        return TableSchema::make()
            ->recordUrl('/panel/events/{id}')
            ->filters([Filter::select('type')->options(EventType::class)])
            ->columns([
                Column::make('title')->linksToRecord()->weight('medium'),
                Column::make('when')->linksToRecord(),
            ]);
    }
}
```

## What the host must provide

Two interfaces, bound in the application's container:

| Interface | Answers |
|---|---|
| `Contracts\SharedPropsInterface` | the props every page carries — auth, flash, navigation, CSRF |
| `Contracts\PageRendererInterface` | how a component name and props become a response |

The package never names Inertia, a template engine or a session. That is what
lets one panel serve several applications that answer those questions
differently.

## Authorization

Four layers, all plain methods on the resource:

| Layer | Hook |
|---|---|
| Route | roles declared where the resource is registered |
| Operation | `canCreate()` / `canEdit()` / `canDelete()` |
| Row | `scopeQuery($qb, $user)` |
| Field | `readonlyFields($record, $user)` — enforced by dropping fields from the submission |

Field-level permission is per record *and* per user, and the server drops the
field rather than trusting a disabled input.

## Read-only resources

Create, edit, update and delete routes are generated only when
`formFieldKeys()` or `formFields()` returns non-null. A resource that declares
no form fields is index-and-show only, with no configuration.

## Development

The package is developed beside its sibling packages and resolves them through
composer path repositories, so it installs and tests on its own:

```bash
composer install
composer test
```

Those `repositories` entries apply only when this package is the root; a
consuming application resolves the siblings its own way.

## Documentation

- [docs/adding-a-resource.md](docs/adding-a-resource.md) — the recipe, and the
  failures that are silent
- [docs/graduating-a-resource.md](docs/graduating-a-resource.md) — the ladder
  from a generated resource to a fully custom page, one rung at a time
- [docs/panel-resources.md](docs/panel-resources.md) — why resources are
  composed rather than inherited, and what `ResourceListing` emits
- [docs/table-schema.md](docs/table-schema.md) — columns, filters, groups,
  constraints

For a screen that is not a listing at all, the consuming application owns that
recipe: see `docs/adding-a-custom-page.md` in appkit-portfolio, which covers
controller conventions, the Inertia page registry and layouts.
