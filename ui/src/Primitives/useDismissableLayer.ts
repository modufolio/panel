import { nextTick, onScopeDispose, toValue, watch, type MaybeRefOrGetter } from 'vue'
import { hideOthers } from './hideOthers'

export type DismissReason = 'escape' | 'pointer-outside'

export interface DismissableLayerOptions {
  /**
   * Everything that counts as "inside" this layer. A teleported panel and the
   * trigger that opened it live in different parts of the DOM, and a press on
   * the trigger must not read as an outside interaction — the trigger's own
   * click handler is what closes the layer in that case.
   */
  elements: () => Array<HTMLElement | null | undefined>
  /**
   * Called when this layer is the top of the stack and the user pressed Escape
   * or interacted outside it. The layer decides what to do: a non-closable
   * dialog can ignore it, and Escape still will not reach the layer beneath.
   */
  onDismiss: (reason: DismissReason) => void
  /** Dismiss on a press outside the layer. @default true */
  dismissOnOutsidePointer?: boolean
  /**
   * The panel that owns the screen while this layer is on top. Supplying it
   * marks the layer modal: the rest of the page is hidden from assistive
   * technology, since `aria-modal="true"` is not reliably honoured on its own.
   * Only the highest modal layer applies, so stacked overlays cannot each hide
   * the other and leave the page with nothing announced.
   */
  modalElement?: () => HTMLElement | null
}

interface Layer {
  elements: () => Array<HTMLElement | null | undefined>
  onDismiss: (reason: DismissReason) => void
  dismissOnOutsidePointer: boolean
  modalElement?: () => HTMLElement | null
}

/**
 * Open layers, oldest first. Only the last one reacts to Escape or an outside
 * press — every overlay used to listen on `document` independently, so one
 * Escape closed a dialog and the drawer behind it in the same press.
 */
const layers: Layer[] = []

function topmost(): Layer | undefined {
  return layers[layers.length - 1]
}

/** The undo for the aria-hidden pass currently applied, if any. */
let releaseHidden: (() => void) | undefined
let hiddenFor: HTMLElement | undefined

/**
 * Hide the page behind the highest modal layer. Re-run whenever the stack
 * changes, so the treatment always follows the overlay the user is looking at.
 */
function syncAriaHidden(): void {
  if (typeof document === 'undefined') return

  let target: HTMLElement | undefined
  for (let index = layers.length - 1; index >= 0; index--) {
    const element = layers[index].modalElement?.()
    if (element) {
      target = element
      break
    }
  }

  if (target === hiddenFor) return

  releaseHidden?.()
  releaseHidden = undefined
  hiddenFor = target

  if (!target) return

  const exempt = layers
    .flatMap((layer) => layer.elements())
    .filter((element): element is HTMLElement => !!element && element !== target)

  releaseHidden = hideOthers(target, exempt)
}

/** The panel of the highest modal layer — the overlay that currently owns focus. */
function topmostModalElement(): HTMLElement | undefined {
  for (let index = layers.length - 1; index >= 0; index--) {
    const element = layers[index].modalElement?.()
    if (element) return element
  }
  return undefined
}

/** Whether any modal overlay is open. */
export function hasModalLayer(): boolean {
  return topmostModalElement() !== undefined
}

/**
 * Whether `element` is (or is inside) the overlay that owns focus.
 *
 * A focus trap has to ask: an overlay teleported next to an open drawer sits
 * outside the drawer's subtree, so the drawer's trap would otherwise drag focus
 * straight back out of whatever opened on top of it.
 */
export function isTopmostModalLayer(element: HTMLElement | null): boolean {
  if (!element) return false

  const modal = topmostModalElement()
  return !!modal && (modal === element || modal.contains(element) || element.contains(modal))
}

function isInside(layer: Layer, target: Node | null): boolean {
  if (!target) return false
  return layer.elements().some((element) => element?.contains(target))
}

/** `Esc` is what older browsers report, and what Vue's `.esc` modifier sends. */
export function isEscapeKey(event: KeyboardEvent): boolean {
  const key = event.key?.toLowerCase()
  return key === 'escape' || key === 'esc'
}

function onKeydown(event: KeyboardEvent): void {
  if (!isEscapeKey(event)) return
  topmost()?.onDismiss('escape')
}

function onOutsidePointer(event: Event): void {
  const layer = topmost()
  if (!layer || !layer.dismissOnOutsidePointer) return
  if (isInside(layer, event.target as Node | null)) return

  layer.onDismiss('pointer-outside')
}

let listening = false

function startListening(): void {
  if (listening || typeof document === 'undefined') return
  listening = true

  // On `document` rather than `window`: an Escape dispatched straight at the
  // document (which is what a test does) has no bubble phase to reach window.
  document.addEventListener('keydown', onKeydown)
  // `pointerdown` is what a real browser sends first; `click` is the fallback
  // for environments that never synthesise pointer events. Dismissing twice
  // for one gesture is a no-op, so listening to both is safe.
  document.addEventListener('pointerdown', onOutsidePointer, true)
  document.addEventListener('click', onOutsidePointer, true)
}

function stopListening(): void {
  if (!listening) return
  listening = false

  document.removeEventListener('keydown', onKeydown)
  document.removeEventListener('pointerdown', onOutsidePointer, true)
  document.removeEventListener('click', onOutsidePointer, true)
}

/**
 * Register an overlay in the global layer stack for as long as `active` holds.
 *
 * The stack is what makes nesting behave: Escape and outside presses go to the
 * layer the user can actually see, and nothing reaches the layers beneath it.
 */
export function useDismissableLayer(
  active: MaybeRefOrGetter<boolean>,
  options: DismissableLayerOptions,
): void {
  const layer: Layer = {
    elements: options.elements,
    onDismiss: options.onDismiss,
    dismissOnOutsidePointer: options.dismissOnOutsidePointer ?? true,
    modalElement: options.modalElement,
  }

  function register(): void {
    if (layers.includes(layer)) return
    layers.push(layer)
    startListening()
    // The panel is not in the DOM yet on the tick it opens.
    void nextTick(syncAriaHidden)
  }

  function unregister(): void {
    const index = layers.indexOf(layer)
    if (index === -1) return
    layers.splice(index, 1)
    syncAriaHidden()
    if (layers.length === 0) stopListening()
  }

  watch(() => toValue(active), (isActive) => {
    if (isActive) register()
    else unregister()
  }, { immediate: true })

  onScopeDispose(unregister)
}
