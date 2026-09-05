# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **A field type nothing renders is caught, and says how to fix itself.**
  `Field\FieldComponents::BUILT_IN` lists the components `@modufolio/panel`
  ships, pinned to `ui/src/Components/Fields/fieldTypes.json` by a test on
  each side of the boundary, and `FieldComponents::missing($fields,
  $registered)` reports what a form needs that neither the package ships nor
  the host registered — a lint over every resource, before a page. On the
  client, `BlueprintForm` shows an unregistered type in the form with the
  `createPanel({ fields: … })` snippet that registers it, logs the same, and
  renders the fields it can; `missingFieldTypes()` and
  `unknownFieldTypeMessage()` are exported for hosts that want the check
  elsewhere. Before, the type failed inside an async import and the field
  was simply absent.
- **`ResourcePage` and `ResourceForm` render a resource from its props.**
  The generic listing page — table or board, filters, column toggle, export,
  the create action, the drawer stack with each record's tabs and lists, and
  the over-drawer add-row form — is now a component of `@modufolio/panel`,
  fed the server's props as they arrive: `<ResourcePage v-bind="$attrs" />`.
  The host keeps only the chrome around it. `#cell-{key}` slots replace one
  generated cell; any other slot dresses a drawer tab. `ResourceForm` is the
  create/edit half. `useResourceListing()` and `useListFilters()`, which the
  page is built on, ship with it, so a page wanting its own markup calls
  the same composable the generic page does. The reference application's
  three generic pages went from 630 lines to shells.
- **The menu entry is declared where the resource is registered.**
  `$panel->resource(X::class)->menu('Events', icon: 'calendar', group:
  'Main', order: 16)` puts the resource in the sidebar. The loader stores the
  entry on the generated index route, and `Routing\ResourceMenu::fromRoutes()`
  hands a host every declared entry with the roles the route enforces, so
  the host's navigation renders them beside its own and no separate menu
  file can be forgotten — the one step of adding a resource that nothing
  errored for skipping.

### Changed

- **The menu entry is declared on the resource; registration is a list.**
  `PanelResource::menu(): ?Menu` — `Menu::make('Events', icon: 'calendar',
  group: 'Main', order: 16)` — replaces `->menu(...)` on the registration. The
  loader stores it on the index route exactly as before, so
  `Routing\ResourceMenu::fromRoutes()` and every host reading it are
  unchanged. With roles and the menu both on the resource,
  `config/panel_resources.php` is a list — `$panel->resources([...])` — or a
  directory: the new `PanelResourceConfigurator::discover($directory,
  $namespace)` registers every concrete `PanelResource` subclass found under
  it, alphabetically. `->only()`, `->except()` and `->prefix()` stay
  registration options, because which routes exist is a routing decision.
  New `docs/coming-from-filament.md` maps Filament's and EasyAdmin's
  vocabulary onto the package's.

- **The controller ships with the package.** `Http\ResourceController` serves
  every generated route — index, show, create, store, edit, update, destroy,
  bulk delete, delete preview, export, relation options, relation create,
  relation store and board move — on top of the services the package already
  owned. An appkit `AppAwareInterface` controller: the kernel hands it the
  application after construction and it pulls its own services, so a host
  registers nothing to use it. `SharedPropsInterface` and
  `PageRendererInterface` are read from the container; a
  `Contracts\ExportAdapterProviderInterface` (with
  `Contracts\ExportAdapterInterface` adapters) and a `FormResolver` naming
  the media entity are read when registered. Without a provider the export
  route answers 422. `PanelResourceRouteLoader` takes the resolver before the
  controller class, which now defaults to the package's:
  `new PanelResourceRouteLoader($locator, $resources)`. Responses are built
  with `modufolio/http`, and `symfony/http-foundation` is a declared
  dependency for the flash bag. The reference application's 761-line
  `ResourceController` is deleted.

- **Rows are read off the entity; the presenter is the override.**
  `PanelResource::present()` is no longer abstract. Its default,
  `Resource\RecordPresenter`, emits the id (the uuid where there is one) and
  each column's key resolved against the entity through appkit's Kirby-ported
  query language — a bare key reads its accessor (`released_on` reaches
  `getReleasedOn()`), a `value('studio.name')` path walks the relation, and
  the new `Column::text('{{ movie.title }} ({{ movie.year }})')` renders a
  template with `record` and the resource's singular key as roots. A null
  along a path is a null cell; a segment nothing can answer is refused by
  column name. Values travel normalised: dates as ISO 8601, backed enums as
  their value, uuids and Stringables as strings, arrays keep their keys,
  collections list what their items show as. `presentOne()` defaults to the
  row plus every form key and declared field, a `{relation}_id` key reading
  back the related record's public id. `PanelResource::presentsItself()` says
  whether the default is in use; `ResourceListing` then withholds `valueKey`
  from the client, since the path is already resolved into the column's own
  key. Needs appkit's `Query\Segment` accessor fallback (get/is/has,
  snake_case to camelCase), landed alongside.

- **The list query is derived from the table; the class is the override.**
  `listQueryClass()` is no longer abstract and defaults to null, and
  `ResourceListing` then builds `Query\DerivedListQuery` from the table:
  sortable columns (those reading a mapped scalar, `released_on` → `releasedOn`),
  the new `TableSchema::defaultSort()`, a case-insensitive LIKE across the new
  `Column::searchable()` columns (`Query\SearchQuery`, joining the to-one a
  `value('studio.name')` path crosses), and `FilterTrashedQuery` when the
  entity has a `deletedAt`. It is built from the same `QueryInterface` objects
  a hand-written class chains, so the two paths cannot disagree. A new
  `PanelResource::queries($params)` hook chains more objects onto either
  (`Query\ChainedListQuery`), for the rows and the count alike. A class named
  by `listQueryClass()` keeps working unchanged. `ListQueryInterface` moved
  from static to instance methods — `sortable()`, `defaultOrder()`,
  `mapSort()` — so the listing asks the object it applies, never a class name;
  `AbstractListQuery` implements them from its constants and its static
  `defaultSort()`, which subclasses still declare. `TableSchema::toArray()`
  takes the query instance, or nothing to serialise the columns as declared.
  A `searchable()` column that maps no field is refused by name.

- **A resource is five parts, each its own object.** `table(): ?TableSchema`
  replaces `tableSchema()`; `form(): ?Form` replaces `formFields()`, taking
  the same entries — bare keys, `key => [options]`, separators — plus
  `Field::make('notes')->textarea()->width('1/2')`, the entry array with
  autocomplete; `drawer(): Drawer` replaces `drawerTabs()`, wrapping the same
  `DrawerTab`s; `board(): ?Board` replaces `views()`, and the table is no
  longer a view among views but the default the switcher offers the board
  beside — `views()` is final and derived. New: `fields(): list<Field>`, what
  each key is said once. A column with no label, a drawer key list, and a
  form entry with no options all look their key up there, so the drawer shows
  a subset of what the form edits without repeating a label. Two levels of
  precedence, no more: what a part says wins over `fields()`, which wins over
  Doctrine's mapping. `PanelResource::drawerTabsFor($record, $formFields)`
  collects the drawer for a record with those labels applied;
  `DrawerTab::collect()` takes the labels as a fourth argument.
  `Column::hasDeclaredLabel()` tells a humanised fallback from a declaration.

- **Permissions are a class the application writes.** `Resource\Permissions`
  replaces the six hooks on `PanelResource` (`canView()`, `canCreate()`,
  `canEdit()`, `canDelete()`, `scopeQuery()`, `readonlyFields()`), the
  `canMoveTo()` board hook, the per-field `access` option, and `->roles()` at
  registration — one object, returned from `PanelResource::permissions()`,
  whose every method answers "yes" until overridden: `roles()`, `view()`,
  `create()`, `edit()`, `delete()`, `export()`, `scope($qb, $alias, $user)`,
  `readable($field, $user, $record)`, `writable($field, $user, $record)` and
  `move($record, $lane, $user)`. A resource gated by roles alone returns
  `new Permissions(['ROLE_USER'])` and writes no class. Rules are behaviour —
  typed record, typed user, testable without a resource, reusable through a
  base class, free to take a service through the resource's constructor —
  which is why they are a class and not closures in a builder. The loader
  reads `roles()` off the resource for every generated route, so there is no
  second role list to keep in step. `writable()` answers both questions the
  package used to ask apart ("frozen on this record", "off limits to this
  role"): a field it refuses renders disabled, and leaves the submission with
  none of its rules run, so a required field frozen on a closed record still
  lets the rest of the record save. `FieldAccess::resolve()` and
  `stripDenied()` take the permissions and the fields; `Blueprint\FormDefinition`,
  `FormFieldGuesser::guessForm()`, `FormResolver::formFor()` and
  `accessFor()` are gone with the access map they carried.
  `PermissionInspector` interrogates the class directly: its report names the
  permissions class, `overrides` lists the methods it answers itself, and the
  field verdicts drop `frozen` (now part of `writeDenied`).

- **One form declaration.** `formFields()` is now the only form hook, and it
  takes what `formFieldKeys()` took: a list of entries in display order —
  a mapped field's key, a key with options, a `Separator`. New is that an
  entry with a `type` is declared outright: the type is taken as written,
  ahead of the column and of `#[FormType]`, and the key need not be mapped
  at all, so a set, an embed, a computed value or a hidden import reference
  sits in the same list as the guessed fields. What the mapping knows still
  applies to a mapped key, so a `TextareaType` pinned over a string column
  keeps the column's `max`. Per-field `access` is an option on the entry,
  and the guesser carries the callables to `FormDefinition::$access` as it
  always did. A hand-written form is therefore the list with types written
  in, not a `BlueprintBuilder` held apart from the guesser — one path, one
  validation, one place to read a form. See [fields.md](docs/fields.md).
- **A linked cell knows where the record is.** `Column::linksToRecord()`
  used to need a matching `TableSchema::recordUrl()`, and the docs warned
  that forgetting either left rows that looked clickable and did nothing.
  The listing now derives the record URL from the resource's generated show
  route, so the common case is one declaration; `recordUrl()` remains as the
  override for rows that open something other than their own drawer. A
  linking column on a resource with neither is refused at render with the
  column's name and both remedies, and so is a child-table column linking
  with no child `recordUrl()`. Schemas that still declare `recordUrl()` are
  unaffected.

- **A non-nullable boolean column is not guessed as required.** A toggle
  has no blank state and NOT NULL is satisfied by false; the guessed
  `required` only turned an unchecked box, or a client that omitted the
  key, into a validation error. Surfaced by the reference application's
  screening form, which used to declare its toggles by hand.

### Removed

- **`PanelResource::formFieldKeys()` and `formAccess()`.** Both folded into
  `formFields()`, above. A resource that returned `$builder->fields()` and
  `$builder->access()` now returns the entries with their `type` and `access`
  written in; the route loader reads `formFields()` alone.

### Fixed

- **A prefixed resource's generated UI targets its own prefix.**
  `ResourceListing` and `FormPresenter` built the `resource.baseUrl` they
  send as `/panel/{key}`, whatever the loader's prefix or a per-resource
  `->prefix('/admin')` said — so create, edit, delete, board moves, filters
  and drawer navigation all pointed at pages that did not exist. The base
  URL is now asked of the router: `Routing\ResourceBaseUrl::resolve()`
  generates it from the index route, falling back through the store, create,
  show, update, destroy and edit routes for a resource routed without an
  index, and only then to the historical default. On the client,
  `useResourceListing()` hands that URL to `useListFilters()` as-is — a new
  `absolute` option skips `panelUrl()` — instead of rebuilding the endpoint
  from the key, and `goToPage()` without a page size now omits it rather
  than sending `undefined`.

- **Arrow-key navigation walks the listing's actual order.** The 0.4.0 notes
  below describe this fix, but the release carried only the note: the change
  to `ResourceListing` was still in the working tree when the tag was cut, so
  it lands here. A request naming a field the query cannot sort on is ordered
  by the query's default, but "previous" reversed the request's own entry —
  reversing nothing — and so ran ascending and answered with the first row
  below instead of the nearest one. The reversal now uses the resolved sort
  field and direction, so both directions describe the same order whatever
  the request said.

  The case that surfaced it was `?sort=`, which the reference application
  appended to every listing URL to "clear" a sort the server never
  remembered, and which parsed to a sort on the empty string. Both halves
  are gone: the application no longer sends it, and `modufolio/json-api`
  now parses an empty sort as no sort, so the listing never sees one.

## [0.4.0] - 2026-09-05

### Changed

- **Resources are built by the host's container, and only there.**
  `PanelResourceRouteLoader` takes a resolver closure and never instantiates a
  resource itself, so a resource may declare constructor dependencies and still
  have its routes generated — the zero-argument constructor rule is gone, and
  with it the advice to pull services in after construction.
- **`ResourceListing` takes its viewer as appkit's `UserInterface`** rather
  than any object. The resource hooks still accept `?object`, so nothing
  downstream changes; a host passing something else to the listing now hears
  about it at the constructor.
- **Guessed labels read as words.** `connectedContact` is "Connected
  Contact" at the top level and "Connected contact" in a repeater row —
  camelCase split, one register per context — where it used to be the raw
  property name.

### Removed

- **`ResourceListingFactory`.** Its two jobs — locating a resource by class and
  binding a listing to a request — belong to the host: a container get, and a
  factory method on the application. The reference application exposes them as
  `App::resource()` and `App::resourceListing()`.
- **`ramsey/uuid` as a dependency.** The one production use validated ids for
  export; it now checks against the package's own `Routing\Uuid::PATTERN`.
  Tests still receive the library through `ramsey/uuid-doctrine`.

### Added

- **The details grid reads the way the form does.** A `DrawerTab::details()`
  without a field list shows the form's fields, in its order, with its
  separators and full-width rows, relations by their presented key — and
  nothing the form does not name. One layout, declared once. A tab wanting
  more still lists its fields, and that list may carry separators too.
- **`DrawerTab::group()`** — a tab that is only its sections, with no grid of
  the record's own values above them: a contact's Communication tab, holding
  its meetings.
- **`DrawerRecordFrame`** renders any stacked frame from what the server
  declared — its data, tabs, sections and field lists — so a record of
  another resource opened over a listing needs no slot on that page. The
  drawer field grid also renders a presented relation as its name, and as a
  link when the presenter gave it an `href`.
- **Repeater rows lay themselves out.** A `text` column takes the whole row
  and does not count towards how the others share it; and `fields` on a
  guessed repeater accepts a map of sub-field key to overrides, merged onto
  the guessed row, so one column can be widened without writing the row out.

- **`#[FormType]` on entity properties.** A property whose column type
  under-describes it — a `string` that is an email, a URL, a colour — names its
  field type beside the column, and `FormFieldGuesser` believes it for every
  form over that entity. The attribute carries the type and nothing else;
  layout, access and conditions stay in the resource. Read through the
  metadata's reflection, so Doctrine itself never sees it.
- **`DateTimeType`.** A datetime column now guesses as a `datetime` control
  rather than a date picker, which could not read the stored time and showed
  the field blank. Filters by day, as `DateType` does.
- **`enumType` columns guess as selects.** The cases are the options, labelled
  by the enum's `getLabel()` where it has one, and a submitted value reaches
  the setter as the case — an empty choice as null.
- **`#[LabelField]` on entity properties.** Marks the column a record is
  referred to by in lookups, checked before the `name`/`title`/`label`
  convention. Declared once on the target, honoured by every relation that
  points at it.
- **Separators between fields.** A `Separator::Line` or `Separator::Space`
  entry in `formFieldKeys()` — a plain list entry between the keys it
  separates — draws a rule or leaves a gap across the full row, so a long form
  reads as runs of fields instead of one grid. `BlueprintBuilder::separator()`
  does the same for a hand-written form. Never validated, never written.
- **Listings can offer more than one view.** `PanelResource::views()` declares
  them; `?view=<key>` selects one; the client renders a switcher only when a
  resource declares more than one, so existing listings are unchanged. The
  first view is the default.
- **Board view.** `ResourceView::board($groupBy)` groups a resource's records
  into columns as draggable cards, with the columns coming from the declaration
  rather than the rows — so an empty column is still shown. It is a different
  *query*, not a different renderer: one query per column, ordered by position
  and limited per column. `BoardView.vue` and `ViewSwitcher.vue` ship with it.
- **`BoardPosition`** — sparse 64-bit integer positions, so dropping a card
  between two others writes one row instead of renumbering the column, and two
  people dragging into the same gap at once get distinct positions instead of a
  tie. A board's position column must be `bigint`.

  Integers rather than decimals: a decimal column takes REAL affinity on
  SQLite, so two positions the server computed as distinct came back equal —
  silently, which is the failure the scheme exists to prevent. The integer
  version is exact everywhere and needs no `ext-bcmath`.

  When a gap does close, `BoardMover` rebalances the column and places the card
  again, so an arithmetic limit never surfaces as a refused drag.
- **`PanelResource::canMoveTo()`** — a resource's own rule about which drags
  are legal, asked before the move is written. Returns a message rather than a
  bare refusal, so the board can say why it put the card back. Defaults to
  allowing every move.
- **`ResourceView::quickMove()`** — a button per card for each column it may
  move to, with the targets computed from the resource's own `canMoveTo()`. A
  button is offered exactly when the move behind it would be accepted, so the
  buttons and the drag cannot disagree; a guarded transition disappears while
  its guard blocks.
- **`resource.canMove`** in the listing props — whether board cards can be
  dragged. Separate from `canEdit`, which also requires the edit form route: a
  board groups records by a field they already have and needs no form.

See [panel-resources.md](docs/panel-resources.md#views).

### Fixed

- **Arrow-key navigation walks the listing's actual order.** With no column
  chosen the panel sends `?sort=`, which parses to a sort on an empty field.
  The listing ignores it and orders by the query's default, but "previous"
  reversed the ignored entry — reversing nothing — and so ran ascending and
  answered with the first row below instead of the nearest one. The reversal
  now uses the resolved sort field and direction.

- **Arrow-key record navigation is one visit at a time.** A held or
  double-pressed arrow fired a second visit while the first was in flight, to
  the same link; when one of the two failed, the page's state and the drawer
  on screen stopped agreeing, and the next press navigated from a frame the
  user was no longer looking at. A press during an in-flight visit is now
  dropped.

- **Clicking the dimmed page closes the drawer stack again.** Each drawer
  marks itself modal and the rest of the page is made `inert` — including the
  stack's shared backdrop, a sibling under the teleport root, which then still
  dimmed the page but ignored the click meant to close everything. The
  backdrop is now exempt, the way live regions already were.

- **A repeater row keeps a second relation to the parent's class.** The
  guesser dropped every to-one association whose target was the parent
  entity, which is right for the inverse side and wrong for a row that also
  references *another* record of that class — a contact's connections, each
  pointing at a different contact. It now excludes exactly the property the
  OneToMany names as `mappedBy`.

- **`DrawerStack` no longer warns about frames with no matching slot.** The
  warning dated from when the fallback was a raw key/value dump, which looked
  enough like content for a typo to go unnoticed. The fallback is now
  `DrawerRecordFrame`, which draws the tabs and fields the server declared — so
  having no `#type` slot is the ordinary path for every generated resource, and
  the warning fired on correct code while advising a slot nobody needs.
- **Inline edits no longer swallow the server's flash message.**
  `useInlineEdit` passed the caller's `only` list to Inertia untouched, and a
  partial reload returns only the props it names — so a table using
  `only: ['users']` never received `flash`, Inertia kept the previous value,
  and `AppLayout`'s watcher never fired. A refused edit looked like nothing at
  all: the row snapped back with no explanation. `flash` and `errors` are now
  appended to any caller-supplied `only`, for both `updateField()` and
  `updateRecord()`. Callers pass `only` to avoid refetching expensive list
  props, not to opt out of being told what happened.

### Documentation

- **The guard wiring is described as it is.** The README, [fields.md](docs/fields.md#wiring-the-guards)
  and the reference application's README said the package *declares* the
  field guards and the host's controller *calls* them, in a fixed order. That
  stopped being true when `SubmissionHandler` and `FormPresenter` took the
  calls over; the host now hands them the request and serialises through
  them, and calls none of the helpers itself. Stale prose about a security
  guard is worse than none, so all three now say where the guards run and
  when a hand-written write path has to reproduce the order.
- **UI specs wait for async fields instead of counting flushes.** Every
  `BlueprintForm` spec mounted the form and flushed a fixed number of times
  before asserting; that settled the dynamic field imports on a warm module
  cache and not on a cold one, which is why the separator spec failed only as
  the first test of a CI run. A shared `mountBlueprintForm` helper waits for
  the rendered fields, and the three specs that guessed now use it.
- **Exports are documented** ([panel-resources.md](docs/panel-resources.md#exports)):
  that the generated route is gated on `canView()` alone, that the *client*
  names the exported columns in the request body with the table schema as mere
  fallback, and that exports present through `present()` rather than
  `presentOne()`.
- **`access` is scoped to the form it guards** ([fields.md](docs/fields.md#per-field-access)).
  The previous wording — a read-denied field is "never shipped" — read as an
  application-wide rule about the value. It is not: presenters, exports and
  hand-written responses never consult `access`, so a field kept out of an
  export is kept out by the presenter, not by a denial. Now stated as a table
  of which paths honour it.

## [0.3.0] - 2026-09-02

### Added

- **Child tables.** `TableSchema::children([ChildTable::relation('cast', 'Cast')->columns([...])])`
  lists related rows in a nested table under each row — the read half of
  master–detail, beside the drawer tabs' write half. Rows come from the
  presented parent row under `source`; the listing checks the relation
  against Doctrine's metadata (one-to-many only, and says why) and loads it
  for the page in one query, so a declared child cannot cost an N+1.
  `SchemaTable` derives row expansion from the schema; expansion survives a
  reload and row focus walks only the table's own rows.
- **A permission inspector.** `Inspection\PermissionInspector` reads the four
  permission layers back together without a request: per resource and role,
  routes admitted, hook verdicts, `scopeQuery()` overridden or not, fields
  readable / read-denied / write-denied / frozen, plus notes where the layers
  disagree. The reference application exposes it as `panel:permissions` and
  as a super-admin page.
- **The write side lives in the package.** `Form\FormResolver`,
  `Form\FormPresenter` and `Form\SubmissionHandler` carry what a generated
  form route does between the request and the response — from which form a
  resource has to persisting the validated, access-filtered submission with
  its repeater rows and to-many links. `Delete\PlanExecutor` carries out a
  deletion plan in one transaction and `Resource\RecordLocator` finds a
  record through the resource's scope. A host controller is routing and
  responses now, and the package's own suite exercises the pipeline against
  a real database. `symfony/validator` is a declared dependency.
- **Filter defaults.** `Filter::default()` names the value that applies when
  the request says nothing; the `trashed` filter takes it from the resource's
  `defaultTrashed()`.
- **CI collects coverage** on the PHP 8.4 leg and uploads it to Codecov, and
  PHPStan runs with `phpstan-phpunit`.

### Fixed

- **A default filter value is no longer an active filter.** A resource with
  `defaultTrashed('with')` echoed the value back as the `trashed` filter, so
  the client counted it, showed a chip, and a reset cleared it only until
  the next request refilled it. A value equal to the filter's default is now
  neither counted nor chipped, and reset returns to it.
- **A decimal reaches a string setter as a string.** A number field's float
  hit `setRating(?string)` as a TypeError under strict types; the setter's
  signature now decides the shape, as it already did for dates.
- **`DrawerStack` warns about a frame no slot claims,** once per type, instead
  of silently falling back to key/value output when a `#type` slot is missing
  or misspelled.

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
