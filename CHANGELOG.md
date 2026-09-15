# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **`ActionGroupSeparator` — a rule between groups of menu items.** Place one
  above a destructive item so the split is structural and the colour only
  confirms it. `SchemaTable`'s generated row menu draws one before the first
  `danger` action on its own.

### Fixed

- **Action menus in dark mode: the clipped focus ring, the invisible active
  row, and icons under 3:1.** A focused item showed the host's 2px offset
  focus ring, which the menu's `overflow-hidden` cut down to two orange
  stripes bleeding into the neighbouring rows. Items now show keyboard focus
  as the highlighted row plus an inset accent bar, which cannot be clipped.
  The hover/focus tint steps up from `--hover` (5%, ~1.1:1 on the raised menu
  surface) to `--pressed`, and item icons use `--ink-2` instead of `--ink-3`
  (2.8:1 on the dark menu).
- **Destructive menu items are quiet red, not a red icon on a grey label.**
  Label and icon both take `--danger-on-surface` at rest, the softened mix
  rather than the raw 500 that glowed on a dark menu; hover keeps the tinted
  row.

## [0.9.0] - 2026-09-12

### Added

- **The layout field: rows of columns, each column a stack of blocks.**
  `LayoutField` and `Modufolio\Panel\Field\LayoutType`, stored as
  `[{id, columns: [{width, blocks: [{type, content}]}]}]` — the shape flat-file
  layout content already travels in, so an import round-trips unchanged. A row
  takes one of the `layouts` presets, each written the way a blueprint states
  it (`'1/2 1/2'`); the `blocks` option names the types a column will offer
  (`heading`, `text`, `quote`, `image`). Host-registered rather than built in,
  like the builder field, because its blocks need the media picker and the
  ProseMirror editor: `createPanel({ fields: { layout: … } })`.

- **`RecordNavigation` — previous/next through a list, from the record's own
  page.** The pair every editor was drawing by hand, once: two chevrons that
  step to the neighbouring records, plus the left/right arrow keys. Both sides
  are always rendered — an absent neighbour is a dead button rather than
  nothing, so the arrows keep their place instead of moving under the cursor as
  you step. Real links, not buttons, so middle-click and cmd-click still open a
  record in a new tab. `label` names what a record is ("post", "movie") for the
  tooltips and the screen reader; `keyboard` turns the keys off for a page that
  wants them for something else. A keystroke aimed at a focused field is left to
  that field, and any chord to the browser.

- **The generated edit page carries that navigation.** `ResourceController`
  resolves each neighbour through the new
  `ResourceListing::navigationRecords()` and hands the page a
  `recordNavigation` prop. Three things it gets right: the order is the
  listing's own, read from the request, so "next" is the row below the one the
  page was opened from; that list state rides along on the links, so stepping
  keeps the sort and the filters alive; and a neighbour this viewer may not
  edit is no link at all rather than a link onto a refusal. A rejected save
  redraws the page with its navigation intact.

- **`InputIcon` — an icon inside an input, positioned once.** Every field
  placed its own `absolute inset-y-0 {side}-0 flex items-center p{l|r}-3`, and
  the copies had drifted (`pl-9` here, `pl-10` there). One definition is what
  each field's own padding lines up against now. `side` and, for a clear button
  or a calendar toggle, `interactive`.

- **Icons for the block editors.** `columns`, `duplicate`, `arrow-up`,
  `arrow-down`, and the builder's own glyphs — paragraph, headings, blockquote,
  bullet list, code block, image — kept as raw path data so a toolbar does not
  shift when the heroicons set changes.

- **`ResourceMenu::fromRouter()` reads menu entries from the router's
  build-time cache** instead of walking a live `RouteCollection`, so the
  host's navigation no longer loads every route from source on each request.
  `fromRoutes()` stays for callers that already hold a collection.

### Changed

- **Requires PHP 8.4 and `modufolio/appkit` ^0.20.** CI now runs 8.4, 8.5 and
  8.6 (8.6 non-blocking while it's still in development); the PHP 8.3 proxy
  fallback in Doctrine test setup is gone along with it.

- **`--color-ember-500` is brighter** (`#e08838` → `#f08119`): the dark theme's
  primary was reading closer to brown than to amber against the near-black
  chrome.

- **The builder's block-insert bar can be turned off** (`insertBar`), and its
  toolbar and slash menu now draw through `Icon` rather than carrying their own
  inline paths. Inside a layout column the slash menu is enough on its own.

- **Fields place their icons through `InputIcon`** — `BelongsToSelect`,
  `ColorPickerField`, `DatePickerField`, `DateRangePickerField`,
  `DateTimePickerField`, `RangeField`, `SelectField`, `TextField`.

- **The drawer stack's shared overlay stands down while an `overlays` panel is
  open**, the same way it already did for a dialog: that panel draws its own
  scrim, and two at once dims the page twice.

### Fixed

- **Leaving a live-updating listing and coming back no longer crashes it.** A
  Centrifugo `Subscription` stays registered on the client by channel name
  after `unsubscribe()` — only `removeSubscription()` forgets it — so
  remounting a page for the same channel called `newSubscription()` on a
  channel that still existed and threw "Subscription to the channel … already
  exists". `useLiveUpdates()` now reuses a leftover registration and drops it
  on dispose.

## [0.8.0] - 2026-09-11

### Added

- **Live updates, if a host wants them.** `ResourceController` announces every
  write it performs — create, update, soft-delete, delete — through the new
  `Realtime\ChangePublisher`, and `ResourceIndexPage` subscribes to its own
  resource key with `useLiveUpdates()`, so a generated listing follows its
  resource without a line of application code. The message is a *nudge*: the
  resource key and the record's public identifier, never record data. A
  listening page answers with a partial reload through its normal route, which
  is what keeps authorization in one place instead of restating it as channel
  permissions that would drift. The module binds `NullChangePublisher` by
  default, so a panel with no broker behind it pays one method call; a host
  that wants live listings declares its own `ChangePublisher`. The client half
  speaks Centrifugo (`centrifuge` is a dependency) and knows two things worth
  naming: every visit and every `apiFetch` call carries the tab's connection id
  (`X-Panel-Client`) so a tab ignores the echo of its own write, and a reload a
  nudge caused is marked `X-Panel-Nudge` so the server can leave someone else's
  flash message alone.

- **`Column::colorWhen(ColorRule …)` — red below zero, green above a
  thousand.** A threshold declared on the column and matched against each
  row's value, with named constructors over the shared operator vocabulary:
  `ColorRule::below()`, `atMost()`, `above()`, `atLeast()`, `between()`,
  `equals()`, `empty()`, and `when()` for the rest. First match wins, and a
  matching rule stands in for the column's own `color()` and `icon()` for that
  row only. The rule crosses the wire and the *comparison happens on the
  client* — deliberately: a row replaced by a live update recolours with no
  second request, an in-place edit recolours as the number changes, and a
  threshold cannot go stale relative to the value printed beside it. A missing
  value matches no comparison, only `empty`; calling an absent number small
  would be a claim about data that is not there.

- **`showConfirm()` — the panel's answer to `window.confirm()`.** An awaited
  question backed by `ConfirmDialog`, as module state with one host mounted by
  `AppLayout`, the same shape `showErrorModal()` already had. Native confirm()
  blocks the tab, cannot be styled, reads as a browser warning rather than as
  part of the panel, and on some platforms offers a "don't show me these again"
  checkbox that silently turns every later question into a yes. `ConfirmDialog`
  gained a `tone` prop (`danger` by default, `primary` for restoring,
  approving, switching) because not every question worth asking is destructive.

- **`date()` and `fromUnix()` — a timestamp, fluently.** `date(value)` returns
  an immutable `DateValue` or `null`, so the client reads the way the
  presenters write: `date(issue.due_date)?.format('MMM D') ?? '—'`, the shape of
  `$issue->getDueDate()?->format(…)`. `format()` without an argument picks
  `MMM D, YYYY`, adding `HH:mm` when the source carried a time. A bare number
  is *not* accepted — PHP counts unix time in seconds and `new Date(n)` counts
  milliseconds — so `fromUnix()` says which, the way
  `createFromFormat('U', …)` does on the server.

- **`niceSize()` — the client half of `F::niceSize()`.** Same unit ladder, same
  two decimals, same locale formatting, same `0 KB` for nothing, so an upload
  does not change size when the page reloads and the server starts answering
  instead. Six copies of the same KB/MB/GB arithmetic went with it.

- **`flattenErrors()`, `errorMessages()`, `apiErrorMessage()` and
  `useFormErrors()`.** Appkit's `ValidationResult::errors()` keys every field
  to a *list* of messages while every field component here takes one string,
  and pages were rendering `["Enter a valid photo URL."]` into the message
  slot. The two shapes now meet in one place. `apiErrorMessage()` is the other
  half: an `ApiError` refuses in one of three shapes — a validation bag, an
  `error` string, a `message` — and every caller that caught one had to know
  all three.

- **Resources can carry metrics.** `PanelResource::metrics()` declares numbers
  shown above the listing, in the three shapes those ecosystems settled on:
  `Metric::value()` (optionally `->compare()`d against the preceding window),
  `Metric::trend()` over a date field, and `Metric::partition()` by a field,
  with colours from the same enum the table and board already read.
  `MetricCalculator` computes them through one door that applies
  `Permissions::scope()` and hides soft-deleted rows, so a metric can never
  count what its viewer cannot open; `MetricRow` and the three cards render
  them. Deliberately not narrowed by the table's filters — a column's
  `summarize()` is what describes the filtered set.

- **The generic resource pages ship with the package.** `resourcePages` is an
  Inertia resolver map covering `Resource/Index`, `Resource/Create`,
  `Resource/Edit` and `Resource/Permissions` — spread it into the app's own
  map (`{ ...resourcePages, ...appPages }`) and a full-CRUD resource needs no
  Vue file anywhere. Every consumer was hand-copying the same four shells,
  which is four chances to drift from what the routes actually send. The
  components are exported individually as well (`ResourceIndexPage` and
  friends), and none of them declares a layout: whatever the app's resolver
  assigns applies to them like any other page.
- **Component styles are part of the package.** `styles/components.css`, now
  imported by `styles/index.css`, carries the `ui-*` rules the package's own
  components emit — PageHeader, Table and its columns, BelongsToSelect,
  RelationManager, StatsWidget, Wizard, the sidebar's icon weight. They lived
  in the consuming application, so anything but that one app rendered those
  components unstyled. The two entrance animations are written as keyframes
  rather than `@apply animate-in …`, so the package needs no Tailwind plugin
  and honours `prefers-reduced-motion`.
- **`useResourceFilters(endpoint, filters, table, perPage)`** — `useListFilters`
  with the schema's own defaults applied and `setFilter`/`goToPage` bound, the
  three lines every generated index page repeated.
- **`useFieldRules(specs, values, serverErrors)`** — client-side checking of
  the rules a PHP blueprint declared, with messages that stay quiet until a
  field is touched or a save is attempted, and a server message that stands
  until the user edits that field.
- **`useTusUploadQueue` and `UploadQueue`** — a domain-free resumable-upload
  queue and its progress panel. `tus-js-client` is an optional peer, reached
  through a dynamic import the first time an upload starts, so an app that
  never uploads never loads it.
- **`RecordFormDrawer`** — the slide-over that adds or edits one related
  record above a drawer, registered in the overlay layer stack so Escape
  closes the panel rather than the drawer beneath it.
- **The panel owns both halves of error reporting.** `errorMessages` entries
  now say *how* a status is reported as well as what it says: a plain string
  is a toast, `{ as: 'modal', title, message }` is a dialog the viewer has to
  dismiss. `ErrorModal` (mounted by `AppLayout`) and the `showErrorModal()`
  store behind it are the new pieces; both are exported for an app that lays
  out its own chrome. When the response carried a JSON:API error body, its
  `title` and `detail` are what the modal shows — the server already redacts
  `detail` outside dev, so the client needs no environment check of its own.
- **`ChangePasswordDialog`** — current password, new, confirm, posted to
  `{baseUrl}/profile/password` (override with `endpoint`), server errors
  landing back on the fields. The package already shipped every other auth
  screen.
- **A default user menu.** `AppLayout` with no `userMenuItems` now renders
  profile, two-factor, change password and log out, wired to the routes every
  panel serves — the change-password entry opens the dialog the layout
  mounts. Passing a list still replaces them wholesale.

- **`ResourceCapabilities` — one object for "may this viewer do this here".**
  Every write button the panel offers is a conjunction of two facts from two
  places: the router says whether the resource *generated* the route, the
  resource's `Permissions` say whether *this viewer* may use it. The pair was
  spelled out at seven sites across the listing, the form presenter and the
  drawer frame; it is now asked once per resource and viewer, with route
  existence memoised (the generator has no cheap "does this route exist",
  only a generation to try and a throw to catch). `ResourceListing::capabilities()`
  exposes it to a controller building its own frame.
- **`SchemaResolver` — the table pipeline, out of the listing.** Labels from
  `fields()`, the mapping's guesses, the record URL from the show route, the
  trashed default, actions from the routes and the permissions, then
  editability: a `TableSchema → TableSchema` pipeline whose steps are public
  and unit-tested without a database. `FilterOptionResolver` holds the one
  step that reads one. `ResourceListing` lost ~400 lines to the two.
- **A control the viewer may not use is not drawn.** An `editable()` column
  used to render its control for everyone and refuse on click for a viewer
  the resource's `writable()` denied. The schema now asks the same question
  before the control exists — at the type level, so a rule about *this
  record* still answers on the write — and a viewer who may not `edit()` the
  type gets no inline control at all.
- **`Inspection\SchemaInspector` — the file a generator would have written.**
  The panel derives everything at render time, which keeps a resource a dozen
  lines and means a column that renders wrong has no file to read. The
  inspector runs the listing's own `SchemaResolver` over the real routes for
  a stand-in viewer and reports every column and form field with the layer
  that decided its label, type, options and control: `column`/`form`,
  `fields`, `mapping`, `route`, `permissions` or `default`. `SchemaReport` is
  the plain-array result a console command tabulates.
- **The resource sends its own labels.** `PanelResource::title()` ('Movies')
  and `label()` ('Movie') travel in the listing's `resource` prop, every key
  a drawer tab lists arrives labelled (from `fields()`, or humanised on the
  server), and a form field placed in a group or fieldset the form never
  declared gets that container declared and labelled in `layout`. The
  client's `useResourceListing` reads `resource.title` and `resource.label`
  and humanises nothing; `titleLabel`/`sentenceLabel` remain exported for a
  page that builds its own props.
- **`RecordVerdicts::verdict()` and `verdictsEach()`** answer `can` and `why`
  from one round of questions. `canEach` then `whyEach` asked every ability
  twice per row; the listing, the board and the drawer frame now ask once.
- **`Permissions` states its cost contract.** Every answer must be pure and
  cheap: the record-level questions are asked once per row per ability on
  every render, and nothing is memoised. A rule that needs data the record
  does not carry loads it in the constructor or narrows the rows with
  `scope()`.
- **`Column::currentLabel()` and `currentType()`** read a column's label and
  type as they stand, for an inspector that wants to compare before and
  after resolution.

### Changed

- **`ResourceController` takes an optional `?ChangePublisher` last.** Nullable
  and last, so a host wiring it positionally is unaffected; without one the
  controller announces nothing.

- **`useTusUploadQueue().formatBytes` and `FileUploadField` render through
  `niceSize()`.** Deprecated rather than removed, so nothing downstream breaks,
  but the output changes: `Bytes` reads `B`, and an empty file reads `0 KB`
  rather than `0 Bytes` — what the server has always said.

- **`DrawerFieldGrid` and `ResetPassword` read through the new utilities.**
  `readableDate()` is `date(value)?.format()`, and the password page's
  hand-written `props.errors?.password?.[0]` is `flattenErrors()` — it was the
  only place in the package that already knew the server sends lists.

- **BREAKING: every `?object $user` parameter is typed `?UserInterface`.**
  `Permissions`, `ResourceCapabilities`, `RecordVerdicts`, `RecordLocator`,
  `RelationAddUrls`, `FieldPickUrls`, `FieldAccess`, `FormPresenter`,
  `SubmissionHandler`, `MetricCalculator`, `PermissionInspector` and
  `SchemaInspector` — every method that received the viewer as `?object` now
  takes `Modufolio\Appkit\Security\User\UserInterface`. The package already
  required `modufolio/appkit ^0.18`, so the interface was always available;
  the untyped parameter was a leftover from before that coupling existed.
  Callers passing an object that does not implement the interface hear about
  it at the call site now rather than inside a hook.

- **BREAKING: `MetricCalculator`, `ResourceListing`, `ResourceController` and
  `GlobalSearch` require a `ClockInterface`.**  `MetricCalculator` takes
  `Psr\Clock\ClockInterface` as its second constructor argument and calls
  `$clock->now()` instead of `new \DateTimeImmutable('now')`. The clock
  threads through `ResourceListing` (new fifth argument), `ResourceController`
  (new `$clock` parameter) and `GlobalSearch` (new third argument). The kernel
  answers `ClockInterface` with `Symfony\Component\Clock\Clock` — the facade
  that delegates to `ClockSensitiveTrait::mockTime()` in tests — so the module
  wires nothing, and `psr/clock ^1.0` is a declared dependency.

- **BREAKING: the controller is handed its resource instead of resolving one.**
  `ResourceController::handle()` now takes a `?PanelResource $resource` in
  place of the `string $resourceClass` it used to look up, and its constructor
  lost the `$resources` dependency. Hosts must add a parameter resolver that
  fills a `PanelResource` argument from the route's `resourceClass` default —
  the same shape as appkit's `MapEntity` resolver — and the route's defaults
  are unchanged, so nothing else moves. The two spanning routes (search, the
  permission page) carry no resource, which is why the argument is nullable.

- **BREAKING: `GlobalSearch` and `PanelResourceRouteLoader` take a closure.**
  Both now accept `\Closure(class-string<PanelResource>): PanelResource` where
  they took a locator. The loader already accepted a closure and every known
  host passed one, so in practice only its type narrowed.

- **401, 403 and 5xx are modals, not toasts.** Each one ends whatever the
  viewer was doing — a dead session, a refused action, a broken server — so a
  notice that fades on its own timer is the wrong shape for it; applications
  were already reaching around the toast to say so. Map the status to a plain
  sentence (`errorMessages: { 500: 'Something broke.' }`) for the old
  behaviour.

- **`ResourceMeta.title` and `ResourceMeta.label` are required.** The
  generated pages and `useResourceListing` read them instead of humanising
  `key` and `drawerType`; a hand-written page that builds the `resource`
  prop itself passes both.
- **`ExportButton` takes a `title`.** The printed table's heading, passed by
  `ResourcePage` from the server's resource title; absent, the filename is
  humanised as before.
- **A drawer tab's listed keys never arrive unlabelled.** `DrawerTab::collect()`
  used to send `null` for a key neither the tab nor `fields()` labelled and
  leave the grid to humanise it; it now sends the sentence-case label.

### Removed

- **BREAKING: `ResourceLocatorInterface` and `ContainerResourceLocator`.** The
  interface named something the host's container already is, and the adapter
  wrapped a container in an object whose only job was to call that container —
  in the playground, `App::resource()` asked the container for a locator built
  over the same container, then checked the answer's type three times over.
  A resource is an ordinary service: declare its collaborators in its
  constructor, register it, and ask the container for it. Where the package
  needs one it is handed one, and appkit's `get($id, $interface)` already
  performs the type check the adapter was written for.

- **`useDragReorder`.** Gone from `@modufolio/panel`: no caller in the
  package, no test, no mention in the docs, and no use in any consuming app.
  Drag reordering in the panel happens through `vuedraggable` and
  `Builder/dragHandle` instead.
- **`startOfMonth`.** Gone from the date utilities — the only one of the
  twenty with no caller. `monthMatrix` covers what a calendar needs.

### Fixed

- **A plain calendar date showed the day before, west of Greenwich.**
  `DateColumn` parsed its value with `new Date('2026-08-02')`, which is UTC
  midnight — so every `YYYY-MM-DD` column rendered a day early for anyone in a
  negative offset. It reads through `date()` now, which is local midnight, and
  the regression test fails under `TZ=America/New_York` without the fix.

- **A dialog opened from inside a drawer had its buttons behind the drawer.**
  `Dialog` and the drawer stack were both `z-50`, so DOM order decided and the
  drawer won: a confirmation, an error modal or the media picker opened from a
  record drawer was unreachable. Dialogs sit at `z-[100]` now — above every
  drawer, still below the toasts and the upload queue, which report on work
  rather than block it.

- **Windowed metrics no longer depend on the runner's wall clock.**
  `MetricCalculator` called `new \DateTimeImmutable('now')` for the window
  boundary, so a test seeding rows at a fixed hour of the day broke on any
  CI runner whose clock had not yet passed that hour. The calculator now
  reads "now" from the injected `ClockInterface`, and the test suite freezes
  time with `ClockSensitiveTrait::mockTime()`.

- **A date preset means the same day everywhere.** `DateRangeFilter`'s presets
  build their dates at local midnight and formatted them with
  `toISOString()`, which is UTC: east of Greenwich "This month" began on the
  last day of the previous one, "Last month" ended a day early, and "Today"
  flipped to yesterday for anyone filtering before their offset had elapsed.
  Days are now formatted in the viewer's own timezone, and the range a preset
  stands for is defined once rather than computed separately for applying it
  and for marking the one in force.
- **Zero is a value a number filter can hold.** `NumberFilter` initialised
  through `||`, so a `0` — `seats > 0`, a range starting at zero — arrived as
  an empty input with no Clear button, while the watch that re-syncs the same
  fields kept it. The two now agree.

## [0.7.0] - 2026-09-08

### Fixed

- **A prefetch that fails says so.** Inertia fires `prefetched` for every
  hover or viewport prefetch and never `httpException` for one that failed:
  the response is kept for the click that replays it, and only then did the
  configured toast appear. A 500 from broken server wiring does not fix
  itself by then, so `AppLayout` now listens on `prefetched` too and shows the
  same toast the moment the failure arrives. `notifyPrefetchedError(status)`
  in `httpErrors.ts` is the reusable piece.
- **A container key is never guessed twice.** `Form::slug()` returned
  `'section'` for any label the slug pattern reduced to nothing — a CJK or
  Cyrillic heading, an emoji — so two such tabs shared a key, and since the key
  is the client's slot name *and* the `group` on every field beneath it, their
  fields silently merged into one tab. It now refuses the label and says to
  pass `key:` instead. Two containers declaring the same key are refused as
  well, in `Form` (tabs and fieldsets) and in `DrawerTab::collect()`.

### Added

- **The permission inspector has a page.** `GET {prefix}/_permissions`,
  generated beside the resource routes and gated by a configurable role
  (`ROLE_SUPER_ADMIN` by default), renders `PermissionsMatrix`: per resource,
  which routes admit each role, what its hooks answer, which fields each role
  may read and write, and where two layers disagree. `PermissionReport` was
  already computed and only the console command could see it. The report stays
  the host's to build — routes, roles and a stand-in user are things only an
  application knows — through the new
  `Contracts\PermissionReportProviderInterface`; without one the route answers
  404 rather than an empty grid.

- **A list you looked at can be named.** Saved views: the filters, search, sort
  and visible columns of a listing, stored per resource under a label and
  offered beside the column toggle — "Overdue issues" rebuilt by hand every
  morning is now one click. Remembered in the browser like column preferences,
  since a view is a URL with a name and needs no table, migration or endpoint;
  `Composables/savedViews.ts` is the seam to move behind one later. Applying a
  view blanks the form before writing it, so a view is the whole list state
  rather than a patch over the last one, and a filter the resource has since
  dropped is reconciled away.

- **A cell can be edited from the list, on any resource.** `PATCH
  {prefix}/{key}/{uuid}` is generated beside the edit routes and writes one
  field: `ResourcePage` wires a handler for every `editable` column to it, so
  an editable select, text input or `toggleIcon()` saves on a generated page
  without a hand-written one. Four gates, in order — the record's `edit`
  permission, the column having declared `editable()` (being in the form is
  not enough), the field's `writable()` access, then the form's own coercion
  and rules through `SubmissionHandler`, told to consider only the fields that
  arrived. The body is keyed by column, so a `value()` mapping is translated
  server-side rather than restated in a page's save handler, and the current
  filters, sort and page ride the URL so the reload lands where the edit was
  made.

- **A boolean can be one clickable icon.** `Column::toggleIcon()` renders a
  flag — featured, pinned, enabled — as a single glyph that flips the value
  where it stands, with `onIcon()`/`offIcon()`, `onColor()`/`offColor()` and
  `onLabel()`/`offLabel()` for the two states. Editable by definition, since
  an icon nobody can click is `boolean()` with a nicer glyph; the click saves
  through the page's `cellHandlers` entry like every other in-place edit, and
  `disabledWhen()` / `readOnlyWhen()` apply as they do to an editable select.
- **Every word of a search must match somewhere.** The listing splits the
  search into words — a phrase in double quotes stays one — and requires
  each in any searchable column, so "john smith" finds a first name in one
  column and a last name in another. `SearchQuery::terms()` is the shared
  splitter.
- **A refusal can say why.** `Permissions::reason($ability, $record, $user)`
  returns a sentence for a refused `edit` or `delete` — "Admins cannot be
  deleted" — and the listing carries it as `meta.why` beside `meta.can`, the
  drawer frame as `why` beside `can`. The UI shows such an action disabled
  with the sentence as its tooltip instead of dropping it; a refusal without
  a reason is not offered, as before.
- **A selected relationship filter value is always labelled.** When the
  option list had to be cut at a hundred, the value in force is fetched on
  its own and appended, so the control and the chip above the table show
  "Studio 101", never a bare id.
- **Failed requests say something useful.** `createPanel({ errorMessages })`
  maps HTTP statuses to sentences — the defaults cover 401, 403, 404, 409,
  419 (session expired), 429 and the 5xx range; 422 stays the forms' — and
  the layout turns a response Inertia cannot use into that toast instead of
  the raw error modal; a request that never got a status says so. `apiFetch`
  falls back to the same sentences when the server sent no message.
- **A header click walks ascending, descending, unsorted.** The third click
  returns the list to the resource's default order instead of trapping it in
  the last direction chosen.
- **Column choices are remembered per resource.** Which columns a viewer
  turned off is kept in the browser and reconciled against the schema on
  every load: a column the resource no longer has is forgotten, a new one
  starts as declared, and a column that is not toggleable is always shown.
- **An action can ask before it runs.** `RowAction::form('reject', '/panel/issues/{id}/reject')`
  opens a dialog: `->fields([Field::make('reason')->textarea()->required(), …])`
  declared like a form's, rendered by the blueprint form, and posted to the
  URL with `->method('patch')` (POST by default) and `->submitLabel()`; a
  `->confirm('…')` without fields is a confirmation. `BulkAction::fields()`
  does the same for a selection, posting the values beside `ids`. Validation
  errors land on the dialog's fields the Inertia way. "Change status with a
  reason", "assign to", "reject with a note" no longer need a page of their own.
- **Search across resources.** A resource opts in with
  `searchableGlobally()`, is searched the way its own listing is — same
  searchable columns, same scope, every word required — and answers a few
  hits with `globalSearchTitle()` (title or name by default),
  `globalSearchDetails()` and the record's URL. The route loader adds one
  `GET {prefix}/search` beside the resources, admitted by any role a resource
  declared, dispatched to `ResourceController` as the `search` operation; the
  module wires `Search\GlobalSearch` from the routes. In the UI,
  `<AppLayout global-search>` turns the top-bar button and ⌘K / Ctrl+K into a
  dialog with hits grouped by resource, arrow keys and Enter.
- **A bulk delete reports its outcome reason by reason.** "7 of 10 movies
  deleted." and one line per reason something was skipped — the permission's
  reason, "no longer exists", "already in the trash", "referenced by
  protected records" — with a count each, instead of one number for
  everything that did not happen.
- **A relation tab as a table.** `DrawerTab::relation('tasks')->columns([...])`
  renders the related rows with a header row and one cell per column — the
  same `Column` objects the listing uses, drawn by the same cell renderer — so
  a record's tasks show their title, state and dates at a glance instead of a
  primary line with one value underneath. Without `columns()` the two-line
  list stays. A column that declares a summary, is editable or links to the
  record is refused: a drawer has nothing to aggregate over, no save path, and
  the row already opens its record (`recordUrl()`). The UI exports
  `DrawerRelationTable` beside `DrawerRelationList`.
- **Drawer fields can span rows.** A details-tab field declared with
  `'cover' => ['rows' => 3]` claims that many grid rows, and the fields that
  follow fill the rows beside it. A media reference declared this way renders
  as a square the height of those rows, cropped rather than stretched, which
  is how a poster sits next to a title, director and genre instead of shrinking
  into one cell; without an image the square stays blank so every record lays
  out the same. `rows` joins `label` and `width` as the options a drawer
  field understands.

- **Prefetching where a visit is likely.** Sidebar and top-navigation links and
  the pagination links prefetch on hover with a ten-second cache, and an open
  drawer prefetches the records its next and previous arrows lead to, with the
  same drawer request a key press would make, so walking a list or a calendar
  month is served from the cache. Every completed write — a router visit that
  is not a GET, or a JSON call through `apiFetch` — flushes the prefetched
  pages, since a listing is a snapshot. Needs appkit's prefetch-aware flash
  (0.17.1): a prefetched page must not consume the toast meant for the page
  the user lands on.

- **A colour column guesses a colour picker.** A string column of nine
  characters or fewer named `color` or `colour` — `brandColor`,
  `poster_colour` — is guessed as `ColorType` instead of a text input asking
  for `#6366f1` by hand. Named and measured both, since a `string(7)` is a
  postcode as often as a swatch, and tried last: a declared `type`, a
  `#[FormType]` and declared options all still win.
- **A table column reads the mapping too.** A `date` or `datetime` column
  renders as a date, and an `enumType` column carries its cases as options —
  so a cell shows "On Hold" rather than the stored `on_hold` — and becomes a
  badge in the enum's own colours when the enum implements
  `HasColorInterface`. `#[ORM\Column(enumType: Status::class)]` is now the
  whole declaration where a listing used to repeat
  `->type('badge')->options(Status::class)->colors(Status::class)`. Guesses
  both, so a declared type, options or colours still win, and a column reading
  a path or a presenter-only key is left alone. A badge also renders its
  option's label instead of the raw value, and a colour written as a plain hue
  (`green`, `yellow` — what `getColor()` usually returns) is translated to the
  semantic token the components draw with, instead of falling back to grey.
- **A drawer reads dates and colours.** A generated drawer printed whatever
  the presenter sent, so a timestamp arrived as `2026-09-08T07:26:29+00:00`
  and a colour as its hex literal. A value that is ISO-8601 now reads as
  `Sep 8, 2026 09:26` (or without the time, for a plain date) and a hex
  literal shows the colour beside it. Strictly recognised, so text that
  merely starts with digits is left alone, and the date formatter is now one
  definition shared with the table's date column — the same moment cannot
  read one way in a cell and another in a drawer.

### Fixed

- **An addable list on a stacked frame can now be added to.** Every addable
  list on a drawer frame carries `addUrl` — its own resource's
  `{key}_relation_store` endpoint, resolved for that record by
  `RelationAddUrls`, and stamped only where the routes exist and
  `Permissions::edit()` admits the viewer. The client offers "+ Add" exactly
  where an endpoint is, and posts to it. Before, the URL was composed from the
  page's own resource: a film stacked over an actor showed a "+ Add" on its
  cast that addressed the actors endpoint and did nothing.

  `DrawerRecordFrame` drops its `canAdd` prop with it: the decision is the
  server's now, per record, so a page has nothing left to withhold.
- **A form no longer dies on a resource that generates no form routes.** A
  relation asked the URL generator for `{key}_relation_options` unguarded, so a
  resource routed for reading only threw on the way out of `FormPresenter` —
  taking down the page that merely *showed* one of its records. Without that
  endpoint the options travel with the field and the control filters them in
  the browser, and the "Create …" row is withheld along with the POST behind
  it.
- **`defaultSort()` reads a column key.** The declaration names a column, in
  the snake_case column keys are written in, but the key was passed through
  unmapped while every column's own field is camelised — so
  `defaultSort('created_at')` reached the query as a field the entity does not
  have and the listing answered with a DQL error.
- **`options` may name a backed enum.** `Field::options()` has always been
  typed for a class name, the way `Filter::select()->options()` and
  `Column::colors()` take one, but only an `enumType` column's cases were ever
  expanded; a declared class name reached the blueprint builder as a string
  and was refused. A string that is not a backed enum now says so by name.
- **A stacked drawer no longer shrinks the ones beneath it.** Each level down
  the stack was scaled a little to read as a pile; since the top of the stack
  is never scaled, what it read as was one panel shrinking while its
  neighbour kept its size. The panels keep their width now and only shift
  left. The panel that adds a row to a list stands on the stack too — same
  width as the drawers, and they shift left for it — rather than being a
  narrower thing pasted over a stack that had not moved. Its close button
  moved to the left, where every drawer carries one, and its scrim is marked
  `data-overlay-backdrop`: without that it was made inert
  along with everything else beside the panel, so pressing the dimmed page —
  the one gesture a scrim exists for — did nothing. Pressing it now puts the
  whole stack away, form included, rather than peeling off one panel per
  press.

## [0.6.0] - 2026-09-07

### Changed

- **Pages are appkit's Inertia pages.** `ResourceController` and
  `ResourceListing` return `Modufolio\Appkit\Inertia\Inertia` values and the
  kernel finishes them; the package no longer merges shared props or renders
  anything itself. `Contracts\PageRendererInterface` and
  `Contracts\SharedPropsInterface` are gone, and so are the two constructor
  parameters that carried them: a host wires appkit's `InertiaModule` with
  its root view and shared props, and declares nothing for the panel.
  Requires modufolio/appkit 0.17.

### Added

- **The server names every URL and every verdict.** A listing's `resource`
  prop carries `urls` — index, create, store, show, edit, update, destroy,
  deletePreview, bulkDestroy, export, boardMove — asked of the router, with
  `{id}` where a record goes and null where the resource opted out; a form
  page's `resource.urls` holds index, store and the record's own update and
  destroy; a drawer frame carries the record's `urls` and `can`. The client
  assembles nothing from `baseUrl` any more (it remains, for hand-written
  pages that predate this). Beside the rows, `meta.can` answers per record
  what `Permissions::edit()` and `delete()` say *with the record in hand*,
  keyed by id — a board column carries the same `can` beside its cards — so
  a record this viewer may not delete shows no Delete instead of a refusal.
  Row actions gate on it by name (`edit`; `delete` and `restore` together),
  the drawer footer on the frame's verdict, and `FormPresenter`'s
  `canDelete` now asks the permission with the record, not only the route.
- **Messages travel as toasts.** A page carries them on Inertia 3's own
  `flash` key, as `flash.toasts: [{type, message}]` — the host's flash store
  drains the flash bag into it (appkit's `FlashStoreInterface`) — and the UI's layout
  shows each once and clears it through the router; no duplicate-window
  heuristic. A host still sharing only a `flash` prop keeps the old path. A
  JSON reply from `ResourceController` carries the same list as `_toasts`,
  drained, so a caller that never navigates still hears it; `apiFetch` shows
  them.

### Changed

- **One JSON envelope.** Every error reply is `{message}`, with `errors` by
  field on validation; the `error` key is gone (board move, relation
  endpoints, delete preview, deny). `apiFetch` already read `message`.
- **303 after PUT, PATCH and DELETE.** A redirect that follows one of those
  is a 303, as Inertia requires, so the browser re-requests the listing with
  GET instead of replaying the method against it. POST keeps 302.
- **`ResourceController` takes its collaborators through the constructor
  and holds no application.** The `AppAwareInterface` hand-over is gone:
  entity manager, URL generator, validator, token storage, flash bag,
  `SharedPropsInterface`, `PageRendererInterface`, a
  `Contracts\ResourceLocatorInterface`, and optionally a `FormResolver` and an
  `ExportAdapterProviderInterface` are constructor parameters. Resources come
  from the locator — `Resource\ContainerResourceLocator` adapts any PSR-11
  container — so the controller never asks a container for anything.
  `PanelResourceRouteLoader` accepts a `ResourceLocatorInterface` as well as
  the closure it took before.
- **The panel is an appkit module.** List `Modufolio\Panel\PanelModule` in
  `config/modules.php` (with `['media_entity' => Media::class]` when there
  is a media library): its controller map wires `ResourceController` by name,
  and its services are the defaults a host declared by hand until now —
  the resource locator over the host's container, the `FormResolver` for the
  configured media entity, and `Export\NoExportAdapters`, an export provider
  that offers no formats (the export route answers 422 as before). Module
  definitions sit under the application's `config/services.php`, so a host
  overrides any of them by declaring the same id. Hosts drop their
  `FormResolver` line; the `ExportAdapterProviderInterface` alias stays where
  formats are offered.

  Upgrade: add the module to `config/modules.php`; remove the `FormResolver`
  entry from `config/services.php` (or keep it — the application's wins).
  Anything that constructed `ResourceController` by hand passes the
  collaborators.

## [0.5.0] - 2026-09-06

A resource is one class with five parts, everything else is derived, and the
package ships the controller. Every entry under *Changed* is breaking. The
short version, for a resource written against 0.4.0:

| 0.4.0 | 0.5.0 |
|---|---|
| `tableSchema()` | `table(): ?TableSchema` |
| `formFields(): ?array` | `form(): ?Form` — `Form::make()->fields([...])`, same entries plus `Field`, `Tab`, `Fieldset` |
| `drawerTabs(): array` | `drawer(): Drawer` — `Drawer::make()->tabs([...])` |
| `views()` with `ResourceView::board()` | `board(): ?Board`; `views()` is final and derived |
| `DrawerTab::details('Label', 'key')`, `group('Label', 'key')`, `relation('source', 'Label')`, `custom('key', 'Label', source:)` | one argument, the key: `record('details')`, `group('communication')`, `relation('addresses')`, `custom('files')`; `->label()` and `->source()` for the rest |
| `canView()`, `canCreate()`, `canEdit()`, `canDelete()`, `scopeQuery()`, `readonlyFields()`, `canMoveTo()`, the field `access` option, `->roles()` at registration | `permissions(): Permissions` — one class, `roles()`, `view()`, `create()`, `edit()`, `delete()`, `export()`, `scope()`, `readable()`, `writable()`, `move()` |
| `->menu(...)` at registration | `menu(): ?Menu` on the resource |
| `present()` and `presentOne()` required | optional; rows are read off the entity through the columns |
| `listQueryClass()` required | optional; the query is derived from the table, `queries()` chains extra `QueryInterface` objects |
| `ListQueryInterface` statics `sortableFields()`, `defaultSort()`, `mapSortField()` | instance `sortable()`, `defaultOrder()`, `mapSort()`; `TableSchema::toArray()` takes the query instance |
| `new PanelResourceRouteLoader($locator, $controllerClass, $resolver)` | `new PanelResourceRouteLoader($locator, $resolver)`; the package's `Http\ResourceController` is the default |
| a host-written resource controller | delete it; alias `Contracts\ExportAdapterProviderInterface` to your adapter factory for downloads |
| write-denied fields marked `props.readonly` | `props.disabled`, which every field component honours |
| `Blueprint\FormDefinition`, `FormResolver::formFor()`, `accessFor()`, `FormFieldGuesser::guessForm()` | gone; `FieldAccess::resolve()` and `stripDenied()` take the fields and the permissions |
| `config/panel_resources.php` with per-resource `roles` and `menu` | `$panel->resources([...])` or `$panel->discover($dir, $namespace)`; `only()`, `except()`, `prefix()` stay |
| the reference application's `ResourcePage`, `ResourceForm`, `useResourceListing` | shipped by `@modufolio/panel`; `useListFilters()` takes `absolute: true` for a server-sent base URL |

### Added

- **Forms have tabs and fieldsets.** `Form\Tab::make('General')->fields([…])`
  hides what is not selected; `Form\Fieldset::make('Name', help: '…')->fields([…])`
  draws a box with a heading. Both are containers among a form's entries and
  both flatten: every field still lands in the one ordered list, carrying
  its tab as `group` and its box as the new `fieldset` option, so guessing,
  access, validation and the drawer following the form are unchanged.
  `Form::tabs([...])` is `fields()` spelled for a form that is tabs from the
  top; `Form::layout()` carries the containers, and `FormPresenter::props()`
  sends them as `layout`. On the client, `BlueprintForm` takes a `layout`
  prop, draws a `FormTabs` bar with an error count per tab, opens the first
  tab that has an error when the open one is clean, drops a tab whose every
  field a condition hides, and boxes fieldsets with a legend. Neither
  container nests.

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

- **Sibling packages are required at their tagged versions**: `modufolio/appkit`
  `^0.16` (the `Query\Segment` accessor fallback and the PHP 8.5 fixes),
  `modufolio/http` `^0.2`, `modufolio/json-api` `^0.9`. The path repositories
  that symlinked the neighbouring checkouts into `vendor/` are gone from
  `composer.json`; a wildcard on all three let the panel run against
  uncommitted sibling work without noticing.
- **A drawer tab takes one argument, its key.** `DrawerTab::record('details')`
  (was `details()`) is the tab that shows the record itself; `group('communication')`,
  `relation('addresses')` and `custom('files')` take their key alone. The label
  is humanised from the key until `->label('Connected contacts')` says
  otherwise, and a relation reads its rows from its key until `->source('tag_list')`
  says otherwise — the same convention as `Column::make()`, so nothing about a
  tab is positional. The wire shape is unchanged.

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
  each column's key resolved against the entity through appkit's
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
  Doctrine's mapping. A details tab's key list takes the same three
  spellings the form does — bare key, `key => label`,
  `Field::make('note')->width('full')` — and a width there is the drawer's
  own, independent of the form's: `full` spans the two-column grid, anything
  else takes one column; a Field option the drawer cannot use is refused by
  name. `PanelResource::drawerTabsFor($record, $formFields)`
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

- **The details grid reads the way the form does.** A `DrawerTab::record()`
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
