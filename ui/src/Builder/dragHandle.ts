/**
 * Drag handles for reordering top-level blocks.
 *
 * ProseMirror moves `draggable` nodes on its own, but only when the drag starts
 * on the node itself — fine for an image, useless for a paragraph, where
 * dragging from the text is a text selection. So the gutter gets a grip that
 * starts the drag on the block's behalf.
 *
 * The move itself is not implemented here. Setting `view.dragging` hands the
 * drop to `prosemirror-view`, which deletes the source and inserts at the
 * target inside one transaction — so the document is never briefly invalid, the
 * move is one undo step, and the drop position is decided by the same code that
 * handles every other drop into the editor. `prosemirror-dropcursor` draws the
 * indicator. Reimplementing any of that by splicing the document would be a
 * second, worse copy of it.
 */

import { NodeSelection, Plugin, type EditorState } from 'prosemirror-state'
import type { EditorView } from 'prosemirror-view'
import type { Node as PMNode } from 'prosemirror-model'

/** Vertical inset from a block's top edge, so the grip lines up with the text. */
const GRIP_OFFSET = 2

/**
 * The node at `pos`, if there is one and it can be selected.
 *
 * Bounds-checked, because `nodeAt()` throws rather than returning null for a
 * position past the end of the document — and the handle caches the position it
 * last hovered, which the document can outgrow between the hover and the click
 * or drag that uses it.
 */
function selectableNodeAt(state: EditorState, pos: number): PMNode | null {
  if (pos < 0 || pos >= state.doc.content.size) {
    return null
  }

  const node = state.doc.nodeAt(pos)

  return node && NodeSelection.isSelectable(node) ? node : null
}

/**
 * Begin dragging the top-level block at `pos`.
 *
 * Exported so it can be tested without a layout engine: everything above it is
 * hover geometry, and everything below it is ProseMirror's.
 *
 * Returns false when the position does not hold a draggable block, leaving the
 * event alone rather than starting a drag that cannot finish.
 */
export function startBlockDrag(view: EditorView, pos: number, event: DragEvent): boolean {
  const { state } = view

  if (!selectableNodeAt(state, pos)) {
    return false
  }

  // Select the block first: the slice is taken from the selection, and leaving
  // it selected is also what makes the drag visible while it is in flight.
  view.dispatch(state.tr.setSelection(NodeSelection.create(state.doc, pos)))

  const slice = view.state.selection.content()

  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
    // Firefox refuses to start a drag without data on the transfer.
    event.dataTransfer.setData('text/plain', '')

    const dom = view.nodeDOM(pos)
    if (dom instanceof HTMLElement) {
      event.dataTransfer.setDragImage(dom, 0, 0)
    }
  }

  // `move: true` is what makes this a move rather than a copy.
  view.dragging = { slice, move: true }

  return true
}

/**
 * The top-level block at a viewport coordinate, or null.
 *
 * `x` is pulled inside the content box before asking, so hovering the gutter —
 * which is outside the editable area — still resolves the block beside it.
 */
function blockPosAt(view: EditorView, x: number, y: number): number | null {
  const box = view.dom.getBoundingClientRect()
  const inside = Math.min(Math.max(x, box.left + 1), box.right - 1)

  const found = view.posAtCoords({ left: inside, top: y })
  if (!found) return null

  const $pos = view.state.doc.resolve(found.pos)

  // depth 0 means the position is directly in the doc, with no block around it.
  return $pos.depth === 0 ? null : $pos.before(1)
}

function grip(): HTMLElement {
  const handle = document.createElement('div')
  handle.className = 'pm-drag-handle'
  handle.setAttribute('draggable', 'true')
  handle.setAttribute('contenteditable', 'false')
  handle.title = 'Drag to move this block'

  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg')
  svg.setAttribute('viewBox', '0 0 12 20')
  svg.setAttribute('fill', 'currentColor')
  svg.setAttribute('aria-hidden', 'true')

  for (const cy of [4, 10, 16]) {
    for (const cx of [4, 8]) {
      const dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle')
      dot.setAttribute('cx', String(cx))
      dot.setAttribute('cy', String(cy))
      dot.setAttribute('r', '1.5')
      svg.appendChild(dot)
    }
  }

  handle.appendChild(svg)

  return handle
}

class DragHandleView {
  private handle: HTMLElement
  private container: HTMLElement
  private pos: number | null = null

  /**
   * Set while the pointer is held on the grip.
   *
   * A drag does not begin until the mouse has moved a few pixels with the
   * button down — and those moves are ordinary mousemove events, which would
   * otherwise re-resolve the hovered block and hide the handle. Hiding the drag
   * source cancels the gesture, so the handle simply stops tracking until the
   * pointer is released.
   */
  private held = false

  constructor(private view: EditorView) {
    this.handle = grip()
    this.hide()

    // The editor's own wrapper, which the field gives a left gutter and
    // `position: relative`. Falls back to the editor itself so the plugin is
    // still safe to use in a bare mount.
    this.container = view.dom.parentElement ?? view.dom
    this.container.appendChild(this.handle)

    this.container.addEventListener('mousemove', this.onMouseMove)
    this.container.addEventListener('mouseleave', this.onMouseLeave)
    this.handle.addEventListener('dragstart', this.onDragStart)
    this.handle.addEventListener('click', this.onClick)
    this.handle.addEventListener('mousedown', this.onMouseDown)
    this.handle.addEventListener('dragend', this.onRelease)
    document.addEventListener('mouseup', this.onRelease)
  }

  private onMouseMove = (event: MouseEvent): void => {
    if (this.held) return

    if (!this.view.editable) {
      this.hide()
      return
    }

    // Moving onto the handle itself must not re-resolve the block: the pointer
    // is over the gutter, and the answer would flicker as the handle moves.
    if (this.handle.contains(event.target as globalThis.Node)) return

    const pos = blockPosAt(this.view, event.clientX, event.clientY)

    if (pos === null) {
      this.hide()
      return
    }

    const dom = this.view.nodeDOM(pos)

    if (!(dom instanceof HTMLElement)) {
      this.hide()
      return
    }

    this.pos = pos

    const blockBox = dom.getBoundingClientRect()
    const containerBox = this.container.getBoundingClientRect()

    this.handle.style.top = `${blockBox.top - containerBox.top + GRIP_OFFSET}px`
    this.show()
  }

  private onMouseLeave = (): void => {
    if (this.held) return
    this.hide()
  }

  private onMouseDown = (): void => {
    this.held = true
  }

  private onRelease = (): void => {
    this.held = false
  }

  /**
   * Clicking the grip selects the block, so it can be deleted or replaced
   * without dragging.
   *
   * Bound to `click`, not `mousedown`: calling preventDefault() on mousedown
   * cancels the native drag before it starts, which silently turns the handle
   * into a select-only control. `click` also does not fire after a drag, so the
   * two gestures do not fight.
   */
  private onClick = (event: MouseEvent): void => {
    if (this.pos === null || !this.view.editable) return

    event.preventDefault()

    const { state } = this.view

    if (selectableNodeAt(state, this.pos)) {
      this.view.dispatch(state.tr.setSelection(NodeSelection.create(state.doc, this.pos)))
      this.view.focus()
    }
  }

  private onDragStart = (event: DragEvent): void => {
    if (this.pos === null || !this.view.editable || !startBlockDrag(this.view, this.pos, event)) {
      event.preventDefault()
    }
  }

  /**
   * Explicit display values in both directions.
   *
   * Clearing the inline style instead would hand visibility back to the
   * stylesheet, whose default for this element is `display: none` — so the
   * handle could be positioned correctly and still never appear.
   */
  private show(): void {
    this.handle.style.display = 'flex'
  }

  private hide(): void {
    this.handle.style.display = 'none'
    this.pos = null
  }

  update(view: EditorView, prevState: EditorState): void {
    this.view = view

    // A changed document invalidates both the cached position and where the
    // handle is sitting. Hiding is honest: the next mousemove re-resolves it.
    if (!view.editable || !prevState.doc.eq(view.state.doc)) {
      this.hide()
    }
  }

  destroy(): void {
    this.container.removeEventListener('mousemove', this.onMouseMove)
    this.container.removeEventListener('mouseleave', this.onMouseLeave)
    this.handle.removeEventListener('dragstart', this.onDragStart)
    this.handle.removeEventListener('click', this.onClick)
    this.handle.removeEventListener('mousedown', this.onMouseDown)
    this.handle.removeEventListener('dragend', this.onRelease)
    document.removeEventListener('mouseup', this.onRelease)
    this.handle.remove()
  }
}

export function blockDragHandle(): Plugin {
  return new Plugin({
    view: (view) => new DragHandleView(view),
  })
}
