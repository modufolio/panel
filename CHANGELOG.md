# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.1] - 2026-09-02

PHP only; `@modufolio/panel` on npm stays at 0.2.0.

### Added

- **A `Database` test suite for the core.** `ResourceListing`, its factory,
  `FormFieldGuesser`, `RelationOptionResolver` and the delete `Collector`
  were covered only by the consuming application — the 0.1.x "known gaps".
  They now run against a real EntityManager over six fixture entities, one
  of each shape the guesser and the collector distinguish. The base case
  boots no kernel, only what the classes need; SQLite in memory by default,
  MySQL/PostgreSQL/SQL Server through the same `DB_*` variables the sibling
  packages read (`docker-compose.yml` has one of each). CI runs all three.

### Fixed

- **`Collector` is reusable.** Its state was never cleared between calls, so
  a shared instance returned plans accumulated from earlier walks.
- **LIKE wildcards are escaped on every engine.** Text filters relied on the
  engine's default escape character; SQLite has none, so searching for `%`
  matched nothing there. The predicate now declares `ESCAPE '!'`.
- **Date bounds include their own day on every engine.** Bounds were bound
  as datetimes; SQLite compares a DATE column as text, so `'1995-12-15'`
  sorted before `'1995-12-15 00:00:00'` and every bound shifted a day. They
  are typed as dates now, and `before`/`between` cover the whole named day
  like `on` already did.
- **Grouping drops a sort on its own field** instead of repeating it in
  ORDER BY, which SQL Server refuses. Deletion plans list referencing classes
  in name order rather than filesystem order, so they read the same on
  every machine.

## [0.2.0] - 2026-09-01

### Added

- **PHPStan at level 8, in CI.** `composer stan` analyses `src` and `tests`;
  `.github/workflows/ci.yml` runs it beside PHPUnit on PHP 8.3 and 8.4 and
  the UI's lint, type-check, tests and build. The sibling packages the path
  repositories point at are checked out next to the repo, so CI installs the
  way a development checkout does. The first run was worth having: the export
  path called `Ramsey\Uuid\Uuid` without the package requiring it, so a
  standalone install fataled on the first uuid-filtered export;
  `Filter::relationSubquery()` read `entityClass` off a nullable relation;
  `Summary::label()` promised a string it could return null for; and
  `drawerTitle()` passed a temporary to `reset()` by reference. The rest was
  array shapes and generics the code already honoured but never wrote down.

### Changed

- **`ramsey/uuid` is a declared dependency** rather than one borrowed from
  whichever application happened to install it.

- **`recordRouteParams()` and keyset navigation say what they need.** The
  default `recordRouteParams()` accepts any `getUuid()` returning a string or
  a `Stringable` — Ramsey and Symfony uids alike — and throws a
  `LogicException` naming the entity when there is none, instead of a method
  call on an unknown object. Arrow-key navigation likewise requires an integer
  `getId()`, since the tiebreak compares against `{alias}.id`, and says so.

- **`BlueprintRegistry` takes its overrides through the constructor.** The
  private `OVERRIDES` constant was empty by construction and unreachable by an
  application, so the map it documented could never be filled; it is now an
  optional `$overrides` argument beside `$namespace`. `conventionalClass()`
  returns a plain string, because a name built from a slug is a candidate,
  not a proven class — `locate()` still checks it exists and is a blueprint.

- **`BlueprintBuilder::add()` declares `$type` as a string.** It always
  verified the class at runtime because declarations arrive from config
  arrays and guessed metadata; the docblock claimed a `class-string` the
  callers could not honour, which made the guard look redundant to analysis.

- **`formFieldKeys()` rejects a non-string plain entry** with a message
  naming the `key => [overrides]` form, instead of casting whatever it was.

- **No `any` left in the UI, and lint fails on warnings.** `TableRecord` is
  `Record<string, unknown>`, with `recordId()` as the one place a row's id is
  asserted; cell, row and bulk action handlers share exported types instead of
  four inline `(record: any)` signatures; drawer stack data, link query params
  and relation items are `Record<string, unknown>`. `npm run lint` now runs
  with `--max-warnings 0`, so the count cannot creep back up.

### Changed

- **Ad-hoc conditions now speak the field types' filter vocabulary.**
  `Table\Constraint` carried a second operator table of its own — the same
  concepts under different names (`notContains` beside `not_contains`,
  `isEmpty` beside `empty`, `isTrue`/`isFalse` where a toggle declares `is`)
  and a second switch to keep in step. It now names a `FilterableFieldInterface`
  implementation per kind (text/number/boolean/date) and takes both the
  operator menu and the predicate from there, so a listing's query builder, a
  field's declared filters and modufolio/json-api's filters are finally one
  vocabulary. What stays behind is what a constraint knows and a type cannot:
  the entity field, each operator's arity, and how a query string's text
  becomes a bound value. **Breaking for saved URLs**: a condition using an old
  operator name no longer matches one that is declared, and is dropped.
  `DateType`'s `on` became a half-open day range — the same declaration is
  pointed at date and datetime columns alike, and equality matched only
  midnight on the latter.

### Changed

- **One frame around every field.** Twenty-two of the twenty-five field
  components rendered their own label, help text and error message, and they
  did not agree: `mb-1` / `mb-1.5` / `mb-2` under the label, `role="alert"` on
  some error paragraphs and not others, the required marker on some labels
  only, and `aria-describedby` hand-computed in eight files and simply missing
  from the rest — so a field could show help that no screen reader ever
  announced. `FieldPrimitive` now owns that scaffolding and hands the control
  what it needs through slot props (`describedBy`, `invalid`); the parts
  (`FieldLabel`, `FieldDescription`, `FieldMessage`) are exported for the two
  layouts the stacked frame cannot express — a checkbox and a switch, whose
  labels sit beside the control. Fields wrapping several controls (a date+time
  pair, a set, a colour picker) render as a `fieldset` with a `legend`, since
  `<label for>` may only point at one control. Borrowed wholesale from
  Keystatic's `@keystar/ui`, whose `field/` package makes the same split.

### Fixed

- **`ComputedType` and the write-path guards had no caller.** Nothing in the
  package invokes `FieldValidator::stripHidden()`, `FieldAccess::stripDenied()`
  or `Defaults::resolve()` — they are for the application's controller to call,
  and until an application did, the features they implement could not be
  observed anywhere. The reference wiring now lives in appkit-playground's
  `ResourceController` (strip denied → resolve defaults → strip hidden →
  validate), with `FieldAccess::resolve()` deciding which definitions are
  serialised at all. Worth folding into the package the day it owns a request.

- **A guessed form's `access` reached nobody.** `FormFieldGuesser::guess()`
  returned `$builder->fields()` and dropped `$builder->access()` on the floor,
  so per-field access declared through `formFieldKeys()` was parsed, validated,
  collected — and discarded before any caller could enforce it. The guesser now
  also offers `guessForm()`, returning a `Blueprint\FormDefinition` carrying
  both halves: `fields` is what may be serialised to the client, `access` is
  what must not be. `guess()` keeps its signature and delegates.
  `PanelResource::formAccess()` is the hand-written twin, for a resource that
  builds its own `formFields()` and so has no builder to ask.

### Added

- **Field-declared filtering.** Field types that know how they filter
  implement `FilterableFieldInterface`: a closed map of named operators plus
  the predicate each builds — text (contains/starts_with/…, LIKE wildcards
  escaped), numbers and decimals (gt/gte/lt/lte/between), dates
  (on/after/before — modufolio/json-api's DateFilter vocabulary), selects
  (is/is_not/in) and toggles. Operator keys deliberately match the JSON:API
  package's filters, one vocabulary across the ecosystem; an undeclared
  operator throws instead of no-oping.

- **Validation grown into one pipeline.** `Blueprint\FieldValidator` (moved
  up from the reference application) now skips fields whose `when` does not
  hold and `stripHidden()` removes their submitted values — a hidden input
  cannot smuggle data past the form. `requiredWhen` makes emptiness
  conditional using the same condition shape, `rules.messages` overrides the
  built-in wording per rule, and the builder refuses impossible declarations
  (min > max, invalid regex) where the blueprint is written.
  `Blueprint\Condition` is the server-side twin of the client evaluator,
  which gained the `not` combinator on both sides.

- **Per-field access.** `'access' => ['read' => fn($user, $record) …,
  'write' => …]` on any field: read-denied fields are removed from the
  serialized definitions entirely (never shipped, not merely not rendered),
  write-denied ones render read-only *and* have their submitted values
  stripped by `FieldAccess::stripDenied()` — disabling the input is
  presentation, the strip is the guard.

- **`ComputedType`** — a server-computed read-only value whose required
  `accessor` option names its source method; declaring one without it fails
  at build time. **`Defaults::resolve()`** fills blank submissions from
  declared defaults, resolving the `@now` / `@today` sentinels at submit
  time — a literal default evaluated at blueprint build freezes the worker's
  boot time into every record.

- **Drawer-stack editing safeguards.** Background frames scale down as they
  recede (the pile reads as depth, not overlap); every close path — the X,
  the back arrow, the backdrop, Escape, clicking a background frame —
  routes through one discard dialog while any frame it would remove reports
  unsaved changes. Forms opt in with `useDrawerDirtyGuard(isDirty)`;
  `NestedDrawerForm` is a real form (Enter submits) and its composable
  gained `getChangedData()` so edit submits can send only the delta.
  Design note, from studying Keystone's v6 drawer stack and its v8 removal:
  create-in-drawer flows, when they arrive, should *build* the child
  payload and let the parent persist it in one transaction rather than
  committing the child immediately — the v6 commit-then-link contract
  minted orphan records on abandon.

- **List-query machinery to go with the contracts.** `Modufolio\Panel\Query\
  {AbstractQuery, AbstractListQuery, SortQuery, FilterTrashedQuery}` — the
  package shipped `QueryInterface`/`ListQueryInterface` but every consumer
  re-implemented the same base class. `AbstractListQuery` closes the two
  classic drift hazards by construction: the fallback ordering derives from
  `defaultSort()`, and `forCount()` runs exactly `applyFilters()`, so a
  filter can never reach the listing without reaching the count. A resource's
  list query now declares only its `SORTABLE_FIELDS`/`FIELD_MAPPING`,
  `defaultSort()`, `applyFilters()` and optional `applyEagerLoads()`.
  `FilterTrashedQuery` implements the interface's `$trashed` contract against
  a `deletedAt` property.

## [0.1.1] - 2026-08-29

### Changed

- `.gitattributes` now marks `/ui`, `/tests` and other development files
  `export-ignore`, so Composer dist installs no longer copy the Vue source
  into `vendor/`. The `v0.1.0` tag predates this, which is why this release
  exists: Composer builds its archive from the tagged tree, so the exclusion
  only takes effect from a tag that contains it.

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
