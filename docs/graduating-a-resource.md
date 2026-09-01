# Graduating a resource

A [resource](adding-a-resource.md) is scaffolding, not a destination. It gets a
screen into production in an afternoon; the screen then grows requirements the
schema was never meant to carry, and at some point it becomes a
`adding-a-custom-page.md` (in the reference application).

That transition is not a rewrite. It is a **ladder of four rungs**, and each
rung gives up exactly one generated thing while keeping everything else.

Five of the thirteen resources in this repo have already climbed to rung 3.

---

## The rungs

| Rung | You give up | You keep |
|---|---|---|
| **1. Fully generated** | — | routes, `Resource/Index.vue`, drawer, everything |
| **2. Your page, generated routes** | the generic Vue page | routes, schema, presenter, list query, drawer |
| **3. Your controller, resource kept** | route generation (partially) | schema, presenter, list query, drawer tabs |
| **4. Fully custom** | the listing machinery | whatever still fits |

The schema survives every rung. `ContactController` is entirely hand-written
and still calls `ContactResource::tableSchema()` and `drawerTabs()` — it
graduated the *controller* without giving up the *declaration*.

---

## Rung 1 → 2: your own page

**When:** the data is a listing, but the screen isn't — a board, a calendar, a
split view, a designed header, an extra panel beside the table.

Override one method:

```php
public function indexComponent(): string
{
    return 'Events/Index';   // instead of the default 'Resource/Index'
}
```

`ResourceListing::render()` reads it, and `ResourceController` renders through
`ResourceListing` — so this works on **generated routes with no controller of
your own**. Nothing else changes: same props, same filters, same drawer stack.

Then write the page. Every derived value comes from one call:

```vue
<script setup lang="ts">
import { useResourceListing, type ResourceMeta } from '@/Composables/useResourceListing'

const props = defineProps({
  filters: Object,
  resource: { type: Object as PropType<ResourceMeta>, required: true },
  table: { type: Object as PropType<TableSchema>, required: true },
  stack: { type: Array as PropType<any[]>, default: () => [] },
})

const {
  records, title, singularLabel, breadcrumbs, visibleColumns, drawerTab,
  drawerStack, form, computedParams, computedSortColumn, computedSortDirection,
  updateSearch, handleSort, goToPage, updatePerPage, setFilter,
} = useResourceListing(props as any)

defineOptions({ layout: Layout })
</script>
```

`useResourceListing()` is the rung-2 tool. It knows the `ResourceListing` prop
contract: rows arrive under the resource's own key in `$attrs`, visible columns
seed from the schema, filters bind to `/{key}`. Take what you need and write
only the markup that differs.

`Resource/Index.vue` uses the same call — so the generic page is not a
privileged thing you fork, it is the first consumer of the same composable.

> **Register the page** in `assets/panel/js/Pages/{group}.js`, or the route
> renders a component the client cannot resolve. See
> `adding-a-custom-page.md` in the consuming application, under "Register the
> page module" — page registration is the application's concern, so that recipe
> lives with it.

---

## Rung 2 → 3: your own controller

**When:** the *request* needs something the loader doesn't generate — extra
props, a bespoke sub-route, a create form with a wizard, an action that isn't
CRUD.

Take the listing as an action parameter:

```php
#[Route(path: '/events', name: 'events', methods: ['GET'])]
public function index(
    #[Resource(EventResource::class)] ResourceListing $listing,
    SomeOtherRepository $extra,
): ResponseInterface {
    return $listing->render();          // or ->withDrawer($stack)->render()
}
```

Filter parsing, pagination, the presenter and the table schema still come from
the resource. You are unwrapping the controller, not replacing the resource.

### Do this partially

Graduating the controller does **not** mean hand-writing every route. The
configurator generates a subset:

```php
$panel->resource(EventResource::class)
    ->roles(['ROLE_USER'])
    ->except(['create', 'edit']);   // or ->only(['index', 'show'])
```

Keep index and show generated while you hand-write the form pages. This is the
rung that used to look like a cliff; `only()`/`except()` are the steps in it.

Note that create, edit, update and delete routes are generated **only when
`formFieldKeys()` or `formFields()` returns non-null** — so a resource with no
form fields is already index-and-show only, without any configuration.

---

## Rung 3 → 4: fully custom

**When:** the screen has stopped being a listing.

At this point the resource is either deleted, or kept purely as a schema object
the controller reads for its table. Both are legitimate — `tableSchema()` is a
public method on a plain object, so reading it from a controller that no longer
renders through `ResourceListing` costs nothing.

---

## Why this shape

The ladder is what lets the declarative layer stay small.

A schema that has to express *everything* grows without bound — every field
type, every conditional, every layout permutation, because there is nowhere
cheap to go when it runs out. If the schema only has to carry the common case
and the rest graduates, it can stay comprehensible: `src/Table` and `src/Panel`
are ~7,400 lines together.

So when a screen needs something the schema cannot say, the first question is
**"which rung?"** — not "what should we add to the schema?". Adding to the
vocabulary is right when many resources want it. Graduating is right when one
does.

---

## Worked examples

Resources live in the consuming application, not in this package. The ones
below are from **appkit-playground**, the harness this package is developed
against:

| Resource | Rung | Why |
|---|---|---|
| `Movie`, `Actor`, `Screening` | 1 | plain list-and-drawer, registered in `panel_resources.php` |
| — | 2 | *nothing yet — see below* |
| `User`, `FormSubmission` | 3 | own controller **and** own page: create/edit forms, bulk actions, bespoke drawers |

**Rung 2 is currently empty**, and that is the point of writing this down. Every
screen that outgrew the generic page jumped straight to rung 3 —
taking on a hand-written controller it may not have needed — because overriding
`indexComponent()` used to mean forking 470 lines of `Resource/Index.vue`
plumbing. `useResourceListing()` exists to remove that reason.

So if a screen needs different *markup* but the same *request*, rung 2 is now
the cheaper answer, and it is the untrodden one. Expect to be the first.

---

## Silent failures

| Symptom | Cause |
|---------|-------|
| Blank page after overriding `indexComponent()` | The new component isn't in `Pages/{group}.js` |
| Graduated page renders no rows | Reading a `records` prop instead of `$attrs[resource.key]` — use `useResourceListing()` |
| Columns all visible despite `hiddenByDefault` | Visible columns not seeded via `visibleColumnDefaults()` |
| Filters do nothing | `useResourceFilters` bound to the wrong endpoint; it must be `/{resource.key}` |
| Hand-written controller, drawer stops working | `->render()` used where `->withDrawer($stack)->render()` was needed |

---

## See also

- [adding-a-resource.md](adding-a-resource.md) — rung 1, from scratch
- `adding-a-custom-page.md` (in the reference application) — the far end of the ladder
- [panel-resources.md](panel-resources.md) — `ResourceListing`, `#[Resource]`, the props it emits
