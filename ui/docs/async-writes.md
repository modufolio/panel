# Async writes: ordering, staleness and in-flight state

Admin panels do a lot of small writes — a toggle in a row, a debounced text
field, an optimistic star. Two of them overlapping is normal, and the
failure modes are quiet: no error, just the wrong value left on screen.

These primitives exist for that. All are exported from the package root.

## The rule they share

> The order that matters is the order requests were **dispatched** in, not
> the order responses **settled** in.

A slow first request must never overwrite what a faster second one already
applied. Nothing here retries or queues; they only decide which result is
allowed to land.

## `writeGate` — deciding which write wins

```ts
import { writeGate, writeKey, LOCAL_WRITE } from '@modufolio/panel'

const key = writeKey('users', user.id, 'is_active')   // "users/42/is_active"

const stamp = writeGate.next(true)                    // take a sequence number
// … dispatch the request …
if (writeGate.admit(key, stamp)) {
  // still the newest write for this key — safe to apply
}
```

| Member | Purpose |
|---|---|
| `next(record: boolean)` | Take the next sequence number. Call at **dispatch**, not on settle. |
| `admit(key, stamp)` | May this write land? Recording stamps also book the key. |
| `seal(key)` | The record is gone server-side — reject every write still in flight for it. |
| `clear()` | Drop all bookkeeping. Intended for tests. |
| `writeKey(resource, id, field?)` | One spelling for keys, so call sites agree on identity. |
| `LOCAL_WRITE` | A write with no dispatch order — seeding state, a value that never went over the wire. Always admitted, records nothing. |

Three details that are load-bearing:

- **Only mutating requests should record** (`next(true)`). A read that
  recorded its number would gate out a save dispatched before it. Pass
  `next(false)` for reads, or to *ask* whether your write is still newest
  without moving the gate.
- **`LOCAL_WRITE` is a value you name, not a parameter you omit.** The
  un-ordered case should be a decision at the call site.
- **`seal()` takes a fresh number**, not the delete's own. A delete becomes
  true when it *settles*; anything still in flight at that moment is dead
  data and must not re-create the record.

The counter is global and monotonic; comparison is per key. A per-key
counter restarting at zero would let a number minted for one key match a
later slot's — which is how a deleted record comes back to life.

## `optimistic()` — apply now, roll back if refused

```ts
import { optimistic, writeKey } from '@modufolio/panel'

const ok = await optimistic(
  () => {                                    // apply, returning a rollback
    const previous = item.starred
    item.starred = !previous
    return () => { item.starred = previous }
  },
  () => apiFetch(url, { method: 'PATCH', body: { starred: item.starred } }),
  writeKey('items', item.id, 'starred'),     // optional, but see below
)
```

Resolves `true` on success, `false` if the request failed (already rolled
back).

**Pass a key whenever the same state can be mutated twice concurrently.**
Without one, two quick toggles interleave so that the *first* failing rolls
state back to before either ran — silently erasing the second toggle, whose
request succeeded. With a key, a failure only rolls back while its own write
is still the newest.

Bulk actions that span many records are the honest exception: one key cannot
represent them, so leave it off.

## `useFieldSaver()` — debounced saves with status

```ts
const { saveStatus, save, debouncedSave } = useFieldSaver(
  (value: string) => apiFetch(url, { method: 'PATCH', body: { title: value } }),
)
```

`saveStatus` is `null | 'saving' | 'saved' | 'error'`. Ordering is built in:
a slow save settling after a faster later one cannot report its stale
outcome in either direction — no stale `error` over a newer success, no
stale `saved` hiding a newer failure. Timers are cleared on scope dispose.

## `usePendingKeys()` — which row is busy

```ts
const { isPending, anyPending, run } = usePendingKeys()

const toggle = (row) => run(row.id, () => apiFetch(/* … */))
```

```vue
<Spinner v-if="isPending(row.id)" />
```

One boolean per component answers "is anything saving"; a list needs "is
*this row* saving", or every row spins together. `run` also guards
re-entry — a key already in flight refuses a second call and resolves
`undefined`, which is what a double-click should mean.

## `useUnsavedChangesWarning()` — don't discard edits silently

```ts
const { allowNextNavigation } = useUnsavedChangesWarning(form)

function submit() {
  allowNextNavigation()   // this navigation *is* the save
  form.post(url)
}
```

Hooks Inertia's `before` event, so it covers every ordinary visit —
including logout, which is a POST visit like any other — plus `beforeunload`
for tab closes.

Takes Inertia's form object, or **a getter** for anything else that knows
whether it is dirty:

```ts
useUnsavedChangesWarning(() => draft.isDirty.value || other.isDirty.value)
```

The getter is read at event time, never captured, so state that flips after
setup is still seen. `allowNextNavigation()` skips exactly one prompt and
re-arms, so a save that leaves the form dirty does not open a hole.

## `moduleSingleton()` — state that survives a duplicated module

```ts
const state = moduleSingleton('toast-store', () => ({
  items: ref([]),
  nextId: 0,
}))
```

A bundler can instantiate the same module twice — Vite's dep pre-bundler,
for example, inlines a package's TS while leaving its `.vue` SFCs as
external raw-source imports. A store imported from both sides of that
boundary splits: one copy is written, the other is rendered, and nothing
appears. It fails silently, which is what makes it expensive to find.

Keying the state off `globalThis` with `Symbol.for` hands every copy the
same object. Use it **only** for process-wide state. Anything scoped to a
component or an app instance belongs in provide/inject — a global would leak
across SSR requests.
