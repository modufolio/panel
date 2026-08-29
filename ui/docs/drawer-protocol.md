# Drawer Stack — server/client protocol

The Drawer components render a stack of overlay panels driven by ordinary
Inertia visits ("URL = state"). Any backend can drive them by honoring this
contract; the reference implementation is appkit-portfolio's
`src/Inertia/DrawerStack.php`.

## Request side

Every visit initiated from inside a drawer (DrawerLink, useDrawerStack
navigation, Drawer record-pagination) carries the header:

```
X-Inertia-Drawer-Stack: 1
```

The server uses its presence to decide to render the *underlying page* with a
drawer stack on top, instead of a full page swap.

## Response side

The page component receives a **`stack`** prop: an ordered array of stack
items, bottom-most first. Each item:

| Field | Type | Meaning |
|---|---|---|
| `type` | string | Which drawer content to render — the client maps types to components (consumer-defined) |
| `data` | object | Payload for that drawer (the record, options, ...) |
| `title` | string | Header title |
| `description` | string | Optional header subtitle |
| `width` | string | `sm` \| `md` \| `lg` \| ... (Drawer width presets) |
| `href` | string | Canonical URL of this drawer (used when re-opening / deep-linking) |
| `nextRecordUrl` | string\|null | Optional record-pagination target (renders next/prev arrows) |
| `previousRecordUrl` | string\|null | Optional record-pagination target |
| `tabs` | array\|null | Optional sections for the drawer body — key, label, count, and per-type extras (`fields`, `sections`, `primary`, `empty`, …) |
| `presentation` | `'drawer'` \| `'dialog'` | Which frame renders this item. Absent means `drawer` — see [Two frames](#two-frames) |

An empty array (or absent prop) means no drawers are open.

## Two frames

A drawer is a side panel you read a record in and can page through. A dialog is
a centred modal for one decision or one short form. They are the **same stack**:
same header, same URL-is-state contract, same per-type slots — a frame declares
which one it wants and nothing else changes.

```php
DrawerStack::create()->push(
    type: 'publish',
    data: [...],
    title: 'Publish post',
    href: '/panel/posts/{uuid}/publish',
    width: 'sm',
    presentation: DrawerStack::AS_DIALOG,
);
```

```vue
<DrawerStack :stack="stack" base-url="/panel/posts">
  <template #publish="{ item }">…the dialog body…</template>
  <template #footer-publish="{ item }">…its buttons…</template>
</DrawerStack>
```

A second protocol for dialogs would have been a second set of things to get
subtly wrong, and it would have bought nothing: because a dialog is a stack
item, it is addressable, deep-linkable and closed by navigating away — which a
dialog held in page state is not. `Dialog` draws its own overlay, so the
stack's shared overlay stands down when no item in the stack is a drawer.

**Frames are pointers, which is the point.** A row action can carry a dialog
URL (`RowAction::dialog()`) instead of naming a behaviour the table has to
implement, so a new interaction is a new route rather than a new case in the
client. See [table-schema.md](../../../docs/table-schema.md#actions).

## Client components

- `<DrawerStack :stack="stack" :base-url="...">` — renders the items;
  `baseUrl` is where closing the whole stack navigates (Inertia visit).
  Provides push/pop/close via `provide/inject` (`useDrawerStackContext`).
- `<DrawerLink href="...">` — opens a URL as a new drawer on the stack
  (adds the header, keeps the underlying page).
- `useDrawerPage()` — inside drawer content: access the current item's
  `data` with page-like ergonomics.
- `useIsDrawer()` — is this component rendering inside a drawer?
- `useFocusedStackRow(props, type, records)` — index of the listing row the
  top stack item is showing, or -1. `SchemaTable` uses this internally when
  given a `stack` prop, so the highlighted row follows record pagination;
  reach for the composable directly only outside `SchemaTable`.

## Never hand-write the visit

The request side is three things at once — the header, `only: ['stack']`, and
the preserve flags that keep the page underneath mounted. Get one third wrong
and it still *looks* right: fetching only the stack renders a drawer even when
the server never took the drawer path. A hand-written `X-Drawer` header did
exactly that, and survived a manual check.

So there is one way to navigate the stack, and every caller uses it:

```ts
import { visitDrawer } from '@modufolio/panel'

visitDrawer('/panel/movies/42', { queryParams: { sort: 'title' } })
```

`DrawerLink`, `Drawer`'s record pagination, `useDrawerStack` and `SchemaTable`'s
declared `drawer` action all route through it; `visit-drawer.spec.ts` asserts
the trio so a fifth caller cannot quietly drop a third of it. Reach for
`drawerVisitOptions()` only when Inertia needs the options themselves (a form
submission rather than a navigation).

`queryParams` keeps empty strings — the server distinguishes "this filter is
set to nothing" from "this filter was not sent", and dropping them silently
reset filters whenever a drawer opened.

## Server responsibilities (checklist for a new backend)

1. Detect the `X-Inertia-Drawer-Stack` header.
2. For drawer requests, resolve the *base* page (the URL under the drawer)
   and its props as usual.
3. Build the stack array (deep-linkable: a fresh GET with a drawer URL should
   reconstruct the same stack) and pass it as the `stack` prop.
4. Set `presentation` on any item that should render as a dialog; omit it for
   drawers.
5. Closing is plain navigation — no special endpoint needed.

A dialog route is an ordinary route: it renders the *listing* underneath with
one extra frame on top. `PostController::publishDialog()` is the reference —
it differs from `show()` by one argument.
