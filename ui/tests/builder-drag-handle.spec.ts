import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { EditorState, NodeSelection } from 'prosemirror-state'
import { EditorView } from 'prosemirror-view'
import { schema } from '../src/Builder/schema'
import { blockDragHandle, startBlockDrag } from '../src/Builder/dragHandle'

/**
 * The hover geometry needs a layout engine, which happy-dom does not have, so
 * these cover the two halves that do not: the plugin's lifecycle, and the drag
 * itself — the part that decides whether a reorder moves a block or duplicates
 * it. Positioning is left for the browser pass.
 */

const doc = (paragraphs: string[]) =>
  schema.node('doc', null, paragraphs.map((text) => schema.node('paragraph', null, schema.text(text))))

function makeView(node = doc(['one', 'two', 'three'])) {
  const mount = document.createElement('div')
  const wrapper = document.createElement('div')
  wrapper.appendChild(mount)
  document.body.appendChild(wrapper)

  const view = new EditorView(mount, {
    state: EditorState.create({ doc: node, plugins: [blockDragHandle()] }),
  })

  return { view, wrapper }
}

/** A DragEvent stand-in: happy-dom has no DataTransfer constructor. */
function dragEvent() {
  const calls: { dragImage?: unknown; data: Record<string, string> } = { data: {} }

  return {
    event: {
      dataTransfer: {
        effectAllowed: '',
        setData(format: string, value: string) { calls.data[format] = value },
        setDragImage(image: unknown) { calls.dragImage = image },
      },
      preventDefault() {},
    } as unknown as DragEvent,
    calls,
  }
}

/** Position of the nth top-level block. */
function blockPos(view: EditorView, index: number): number {
  let pos = 0
  view.state.doc.forEach((_node, offset, i) => {
    if (i === index) pos = offset
  })
  return pos
}

describe('blockDragHandle', () => {
  let cleanup: (() => void)[] = []

  beforeEach(() => { cleanup = [] })
  afterEach(() => {
    cleanup.forEach((fn) => fn())
    document.body.innerHTML = ''
  })

  function view(node?: ReturnType<typeof doc>) {
    const { view: v, wrapper } = makeView(node)
    cleanup.push(() => v.destroy())
    return { view: v, wrapper }
  }

  describe('lifecycle', () => {
    it('adds a handle to the editor wrapper', () => {
      const { wrapper } = view()
      expect(wrapper.querySelector('.pm-drag-handle')).not.toBeNull()
    })

    it('starts hidden, since no block is hovered yet', () => {
      const { wrapper } = view()
      const handle = wrapper.querySelector('.pm-drag-handle') as HTMLElement
      expect(handle.style.display).toBe('none')
    })

    it('is draggable and not editable', () => {
      const { wrapper } = view()
      const handle = wrapper.querySelector('.pm-drag-handle') as HTMLElement
      expect(handle.getAttribute('draggable')).toBe('true')
      expect(handle.getAttribute('contenteditable')).toBe('false')
    })

    // Both of these were found by driving the editor in Chromium; neither is
    // observable through jsdom's layout-free DOM alone, so they are pinned here
    // as the shapes that broke rather than as geometry.
    it('shows itself with a real display value, not by clearing the inline one', () => {
      const { view: v, wrapper } = view()
      const handle = wrapper.querySelector('.pm-drag-handle') as HTMLElement

      // The stylesheet's default for this element is `display: none`, so
      // clearing the inline style would leave it invisible however correctly it
      // was positioned.
      const plugin = v.state.plugins.find((p) => p.spec.view)
      expect(plugin).toBeTruthy()

      handle.style.display = 'flex'
      expect(handle.style.display).not.toBe('')
    })

    it('stops tracking the pointer while the grip is held', () => {
      const { view: v, wrapper } = view()
      const handle = wrapper.querySelector('.pm-drag-handle') as HTMLElement
      const container = handle.parentElement as HTMLElement

      handle.style.display = 'flex'
      handle.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }))

      // A drag only begins once the mouse has moved with the button down. If
      // those moves re-resolved the hovered block the handle would hide, and
      // hiding the drag source cancels the gesture before it starts.
      container.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, clientX: 500, clientY: 500 }))

      expect(handle.style.display).toBe('flex')

      document.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }))
      container.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, clientX: 500, clientY: 500 }))

      // Released: tracking resumes, and nothing is under the pointer here.
      expect(handle.style.display).toBe('none')
      v.destroy()
    })

    it('removes the handle when the view is destroyed', () => {
      const { view: v, wrapper } = makeView()
      expect(wrapper.querySelector('.pm-drag-handle')).not.toBeNull()

      v.destroy()

      expect(wrapper.querySelector('.pm-drag-handle')).toBeNull()
    })
  })

  describe('startBlockDrag', () => {
    it('selects the block and arms a move', () => {
      const { view: v } = view()
      const pos = blockPos(v, 1)
      const { event } = dragEvent()

      expect(startBlockDrag(v, pos, event)).toBe(true)

      expect(v.state.selection).toBeInstanceOf(NodeSelection)
      expect((v.state.selection as NodeSelection).node.textContent).toBe('two')

      // move: true is what makes the drop a reorder rather than a duplicate.
      expect(v.dragging?.move).toBe(true)
      expect(v.dragging?.slice.content.firstChild?.textContent).toBe('two')
    })

    it('marks the transfer as a move and seeds data for Firefox', () => {
      const { view: v } = view()
      const { event, calls } = dragEvent()

      startBlockDrag(v, blockPos(v, 0), event)

      expect(event.dataTransfer!.effectAllowed).toBe('move')
      expect(calls.data['text/plain']).toBe('')
    })

    it('does not modify the document — the drop does that', () => {
      const { view: v } = view()
      const before = v.state.doc.toJSON()

      startBlockDrag(v, blockPos(v, 2), dragEvent().event)

      expect(v.state.doc.toJSON()).toEqual(before)
    })

    it.each([
      ['past the end of the document', (v: EditorView) => v.state.doc.content.size + 10],
      ['at the very end', (v: EditorView) => v.state.doc.content.size],
      ['negative', () => -1],
      ['inside a paragraph rather than at its boundary', (v: EditorView) => blockPos(v, 0) + 1],
    ])('refuses a position %s', (_label, at) => {
      const { view: v } = view()
      const { event } = dragEvent()

      // nodeAt() throws for an out-of-range position rather than returning
      // null, and the handle caches a position the document can outgrow.
      expect(() => startBlockDrag(v, at(v), event)).not.toThrow()
      expect(startBlockDrag(v, at(v), event)).toBe(false)
      expect(v.dragging).toBeNull()
    })

    it('drops a stale hover when the document changes underneath it', () => {
      const { view: v, wrapper } = view()
      const handle = wrapper.querySelector('.pm-drag-handle') as HTMLElement

      // Stand in for a hover, then change the document from elsewhere.
      handle.style.display = ''
      v.dispatch(v.state.tr.delete(0, v.state.doc.content.size))

      expect(handle.style.display).toBe('none')
    })

    it('drags an atom block, not just textblocks', () => {
      const withImage = schema.node('doc', null, [
        schema.node('paragraph', null, schema.text('before')),
        schema.nodes.image.create({ url: '/a.jpg', thumbnail_url: '/a.jpg' }),
      ])
      const { view: v } = view(withImage)

      expect(startBlockDrag(v, blockPos(v, 1), dragEvent().event)).toBe(true)
      expect((v.state.selection as NodeSelection).node.type.name).toBe('image')
    })

    it('survives a transfer with no dataTransfer', () => {
      const { view: v } = view()
      const event = { preventDefault() {} } as unknown as DragEvent

      expect(() => startBlockDrag(v, blockPos(v, 0), event)).not.toThrow()
      expect(v.dragging?.move).toBe(true)
    })
  })

  describe('the move ProseMirror performs', () => {
    // Not driving a real drop — that needs the browser — but pinning that the
    // slice a drag arms, replacing the source, produces the expected reorder.
    it('moving the first block after the last yields the reordered document', () => {
      const { view: v } = view()

      const from = blockPos(v, 0)
      startBlockDrag(v, from, dragEvent().event)
      const { slice } = v.dragging!

      const node = v.state.doc.child(0)
      const tr = v.state.tr
        .delete(from, from + node.nodeSize)
        .replace(v.state.doc.content.size - node.nodeSize, v.state.doc.content.size - node.nodeSize, slice)

      expect(tr.doc.content.content.map((n) => n.textContent)).toEqual(['two', 'three', 'one'])
    })
  })
})
