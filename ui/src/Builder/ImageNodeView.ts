/**
 * NodeView for the `image` node.
 *
 * ProseMirror renders atoms through `toDOM` by default, which is fine for the
 * published page but useless in the editor — an image block needs a picker
 * trigger, an alt/caption field, and a width control. A NodeView takes over the
 * DOM for that one node.
 *
 * This file builds DOM imperatively (`createElement` / `textContent`), never
 * `innerHTML`, so no attribute value can become markup. That is a deliberate
 * constraint rather than a style preference: NodeViews are the one place the
 * schema stops protecting us (docs/prosemirror-builder.md §2.4).
 */

import type { EditorView, NodeView } from 'prosemirror-view'
import type { Node as PMNode } from 'prosemirror-model'
import { sanitizeUrl } from '../Utils/url'
import { IMAGE_WIDTHS, type ImageWidth } from './schema'

export interface ImageNodeViewOptions {
  /** Opens the media library; resolves with the chosen image, or null. */
  pickImage: () => Promise<{
    id: string
    url: string
    thumbnail_url: string
    alt_text?: string
    caption?: string
  } | null>

  /** Opens the media detail view for an image, as double-click does in the library. */
  openMedia?: (id: string) => void
}

export class ImageNodeView implements NodeView {
  dom: HTMLElement

  private img: HTMLImageElement
  private captionEl: HTMLElement
  private placeholder: HTMLButtonElement

  constructor(
    private node: PMNode,
    private view: EditorView,
    private getPos: () => number | undefined,
    private options: ImageNodeViewOptions,
  ) {
    this.dom = document.createElement('figure')
    this.dom.className = 'pm-image group relative my-3 rounded-lg border border-transparent hover:border-gray-200'
    this.dom.setAttribute('data-node', 'image')

    this.img = document.createElement('img')
    this.img.className = 'block max-w-full rounded'
    this.img.setAttribute('decoding', 'async')
    this.img.addEventListener('dblclick', (e) => {
      const id = String(this.node.attrs.id ?? '')
      if (id === '' || !this.options.openMedia) return
      e.preventDefault()
      this.options.openMedia(id)
    })

    this.placeholder = document.createElement('button')
    this.placeholder.type = 'button'
    this.placeholder.className =
      'flex w-full items-center justify-center gap-2 rounded-lg border-2 border-dashed ' +
      'border-gray-300 py-10 text-sm text-gray-400 hover:border-primary-400 hover:text-primary-600'
    this.placeholder.textContent = 'Choose an image…'
    this.placeholder.addEventListener('click', (e) => {
      e.preventDefault()
      void this.pick()
    })

    this.captionEl = document.createElement('figcaption')
    this.captionEl.className = 'mt-1 text-xs text-gray-500'

    this.dom.append(this.placeholder, this.img, this.captionEl, this.buildToolbar())
    this.render()
  }

  private buildToolbar(): HTMLElement {
    const bar = document.createElement('div')
    bar.className =
      'absolute right-2 top-2 hidden gap-1 rounded-md bg-gray-900/90 p-1 group-hover:flex'
    bar.setAttribute('contenteditable', 'false')

    const button = (label: string, title: string, onClick: () => void) => {
      const el = document.createElement('button')
      el.type = 'button'
      el.title = title
      el.textContent = label
      el.className = 'rounded px-1.5 py-0.5 text-xs text-white hover:bg-white/20'
      el.addEventListener('mousedown', (e) => e.preventDefault())
      el.addEventListener('click', (e) => {
        e.preventDefault()
        onClick()
      })
      bar.appendChild(el)
      return el
    }

    for (const width of IMAGE_WIDTHS) {
      button(width[0].toUpperCase(), `Width: ${width}`, () => this.setAttrs({ width }))
    }

    button('Alt', 'Edit alt text', () => {
      const alt = window.prompt('Alt text (describe the image for screen readers)', String(this.node.attrs.alt ?? ''))
      if (alt !== null) this.setAttrs({ alt })
    })

    button('Replace', 'Replace image', () => void this.pick())

    return bar
  }

  private async pick(): Promise<void> {
    const chosen = await this.options.pickImage()
    if (!chosen) return

    this.setAttrs({
      id:            chosen.id,
      url:           chosen.url,
      thumbnail_url: chosen.thumbnail_url,
      alt:           chosen.alt_text ?? '',
      caption:       chosen.caption ?? '',
    })
  }

  private setAttrs(attrs: Record<string, unknown>): void {
    const pos = this.getPos()
    if (pos === undefined) return

    const tr = this.view.state.tr.setNodeMarkup(pos, undefined, {
      ...this.node.attrs,
      ...attrs,
    })
    this.view.dispatch(tr)
  }

  private render(): void {
    const { attrs } = this.node
    const src = sanitizeUrl(String(attrs.thumbnail_url || attrs.url || ''))

    if (src === '') {
      this.placeholder.style.display = ''
      this.img.style.display = 'none'
    } else {
      this.placeholder.style.display = 'none'
      this.img.style.display = ''
      this.img.src = src
    }

    // textContent, never innerHTML — these are content values.
    this.img.alt = String(attrs.alt ?? '')
    this.captionEl.textContent = String(attrs.caption ?? '')
    this.captionEl.style.display = this.captionEl.textContent === '' ? 'none' : ''

    const width = (IMAGE_WIDTHS as readonly string[]).includes(String(attrs.width))
      ? (attrs.width as ImageWidth)
      : 'default'
    this.dom.setAttribute('data-width', width)
  }

  update(node: PMNode): boolean {
    if (node.type !== this.node.type) return false

    this.node = node
    this.render()

    return true
  }

  /** The node has no editable content, so ignore mutations inside it. */
  ignoreMutation(): boolean {
    return true
  }

  stopEvent(event: Event): boolean {
    // Let our own controls handle their clicks without ProseMirror intercepting.
    return event.target instanceof HTMLElement && event.target.closest('button') !== null
  }
}
