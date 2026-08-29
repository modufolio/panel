# Overlay primitives

Dialogs, drawers, dropdowns and popovers in this package share a small set of
primitives in `src/Primitives/` rather than each carrying its own copy of the
behaviour. They are exported, so an application can build its own overlays on
the same foundations.

## The layer stack — `useDismissableLayer`

Every open overlay registers itself while it is open. Escape and outside
presses go to the **top of the stack only**.

```ts
useDismissableLayer(isOpen, {
  // Everything that counts as "inside": a teleported panel and the trigger
  // that opened it live in different parts of the DOM.
  elements: () => [triggerRef.value, panelRef.value],
  onDismiss: (reason) => { isOpen.value = false },
  dismissOnOutsidePointer: true,
  // Supplying this marks the layer modal: the rest of the page is hidden from
  // assistive technology while it is on top.
  modalElement: () => panelRef.value,
})
```

The layer decides what to do with a dismissal, so a non-closable dialog can
ignore Escape without the press falling through to the drawer beneath it.

`modalElement` exists because `aria-modal="true"` is not reliably honoured: a
screen reader can otherwise walk out of an open dialog and read a page the user
cannot reach. The page behind is marked `inert` rather than `aria-hidden` —
the browser refuses the latter over the focused element ("Blocked aria-hidden
on an element because its descendant retained focus"), while `inert` moves
focus out on its own and takes the page out of the tab order too. Only the
highest modal layer applies, and the other registered layers are exempt — so a
dropdown opened inside a dialog is not silenced by the dialog containing it,
and a toast region keeps announcing.

**An overlay the host app renders itself must register too.** A raw
`<Teleport to="body">` panel is invisible to the stack: Escape closes the
drawer beneath it instead of the panel, and that drawer's focus trap reaches
into it — which shows up as a combobox inside the panel closing the instant it
opens. Registering it is the whole fix:

```ts
const panelRef = ref<HTMLElement | null>(null)

useDismissableLayer(() => isVisible.value, {
  elements: () => [panelRef.value],
  onDismiss: (reason) => { if (reason === 'escape') close() },
  dismissOnOutsidePointer: false,   // the panel draws its own scrim
  modalElement: () => panelRef.value,
})
```

## Scroll lock — `useBodyScrollLock`

Reference counted across every overlay. The page scrolls again only once the
last holder releases, so a dialog opened from inside a drawer cannot unlock the
page while the drawer is still covering it. It also compensates for the
scrollbar's width, so the page behind does not jump sideways, and exposes that
width as `--panel-scrollbar-width`.

```ts
const locked = useBodyScrollLock(props.isOpen)
watch(() => props.isOpen, (open) => { locked.value = open })
```

## Positioning — `useAnchoredPosition`

A thin wrapper over `@floating-ui/vue`: flipping, viewport clamping, optional
width matching and height capping, and — through `autoUpdate` — following the
anchor through scrolls and resizes without per-component listeners.

```ts
const { floatingStyles } = useAnchoredPosition(triggerRef, panelRef, isOpen, {
  placement: 'bottom-end',
  matchWidth: true,
})
```

The available height is applied as `max-height` and published as
`--panel-available-height` for panels that scroll an inner region instead.

## Keyboard — `useArrowNavigation`, `useTypeahead`

`useArrowNavigation(container)` gives a menu the WAI-ARIA keyboard model:
arrows move focus between items, Home/End jump to the ends, and typing jumps to
an item by name. `resolveNavigationIndex()` is the same key model as a pure
function, for lists driven by a highlighted index — a combobox, where focus
stays in the text field and `aria-activedescendant` points at the row.

## Ids — `useId`

Wraps Vue's own `useId()`, which is stable across a server render and its
hydration. Ids count per application, so two components in the same app always
differ; two independently created apps restart the sequence.

## Where overlays render — `teleportTarget`

Overlays teleport to `body` by default. An application whose overlays must live
inside a themed or transformed subtree sets it once:

```ts
app.use(createPanel({ teleportTarget: '#panel-overlays' }))
```
