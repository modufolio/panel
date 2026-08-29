# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-08-26

Initial extraction from appkit-portfolio, where this was ~8,500 lines of
application code that every panel had to copy. It is now a package that
declares admin listings as data and knows nothing about how they are rendered.

### Added

- **Table schema** — `TableSchema`, `Column`, `Filter`, `Group`, `Summary`,
  `Constraint`, `RowAction`, `BulkAction`, `ColumnAction`, `RelationOptions`.
  A column is sortable only when the resource's list query can order by it, so
  a schema cannot promise a sort the query would refuse.

- **Resources** — `PanelResource` and its four authorization layers: route
  roles, `canCreate()`/`canEdit()`/`canDelete()`, `scopeQuery()` for row-level
  visibility and `readonlyFields()` for field-level, the last per record *and*
  per user, enforced by dropping the field from the submission rather than by
  disabling an input.

- **Drawers** — `DrawerTab` with details, relation and custom tabs.
  A details tab naming no fields prints the whole record, which is right for a
  resource nobody has curated and wrong once a presenter carries keys that
  exist to make a row render.

- **Route generation** — `Routing\PanelResourceRouteLoader` turns registered
  resources into routes with no controller file and no `#[Route]`. Create,
  edit, update and delete are generated **only** when `formFieldKeys()` or
  `formFields()` returns non-null, so a resource that declares no form is
  index-and-show only without any configuration. `only()` / `except()` narrow
  it further, which is what lets a resource graduate to a hand-written
  controller one operation at a time.

- **Fields and blueprints** — 16 field types, `FormFieldGuesser`,
  `BlueprintBuilder`, `BlueprintRegistry`. The guesser takes the host's media
  entity class as a constructor argument rather than naming one: "the media
  library" is an application's concept and not every application has one.

- **`Routing\Uuid`** — the route-matching pattern generated routes address
  records by. It has to reject non-uuids, or `/panel/{key}/create` matches the
  show route and a resource loses its create page to its own detail view.

### Contracts

The package never names Inertia, a template engine or a session. Two
interfaces, bound by the host application, are the whole of what it asks for:

- `Contracts\SharedPropsInterface` — the props every page carries: auth,
  flash, navigation, CSRF.
- `Contracts\PageRendererInterface` — how a component name and props become a
  response.

That is what lets one panel serve several applications answering those
questions differently, and it is why `DefaultProps` and the Inertia adapter
stay in the host: they read an app-specific image disk, impersonation tokens
and flash keys, and are composition rather than machinery.

### Testing

114 tests, runnable standalone (`composer install && composer test`) rather
than only through a consuming application — the point of the extraction being
that the package can be changed from either side.

Two areas are covered deliberately rather than incidentally:

- **The generated route table is asserted exactly** — every route's name, path
  and methods. Mutation testing found that a suite checking only `index` and
  `show` missed a change to `_update`'s path, and that asserting the uuid
  requirement on `show` alone missed the other six routes that take one.
- **Routes must survive compilation.** No route default, requirement or option
  may be anything but a scalar or an array of scalars, and the collection is
  dumped and included back to prove it. A closure default works in development
  and dies on the first cached boot, so it needs a test rather than a comment.

### Known gaps

`ResourceListing`, `ResourceListingFactory`, `FormFieldGuesser`, the
`Blueprint` builders and the `Delete` collector have no tests of their own yet;
they need a request or Doctrine metadata to exercise. The consuming
application's integration tests cover them today.
