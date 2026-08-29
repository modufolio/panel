import { describe, it, expect } from 'vitest'
import { DOMParser as PMDOMParser, DOMSerializer, Node as PMNode } from 'prosemirror-model'
import { schema, safeHref, HEADING_LEVELS, IMAGE_WIDTHS, CODE_LANGUAGES } from '../src/Builder/schema'

// Control characters a browser strips while resolving a URL, so each of these
// executes in an href exactly as the plain payload would.
const C = String.fromCharCode
const OBFUSCATED_JS = 'java' + C(10) + 'script:alert(1)'

function parseHtml(html: string): PMNode {
  const dom = new window.DOMParser().parseFromString(`<div>${html}</div>`, 'text/html')
  return PMDOMParser.fromSchema(schema).parse(dom.body.firstElementChild!)
}

function serialize(doc: PMNode): string {
  const fragment = DOMSerializer.fromSchema(schema).serializeFragment(doc.content)
  const div = document.createElement('div')
  div.appendChild(fragment)
  return div.innerHTML
}

describe('builder schema', () => {
  // ── The property the whole migration rests on ──────────────────────────────

  describe('documents contain no HTML', () => {
    it('stores markup as typed marks, never as a string', () => {
      const doc = parseHtml('<p>Hello <strong>world</strong></p>')
      const json = doc.toJSON()
      const para = json.content[0]

      expect(para.type).toBe('paragraph')
      expect(para.content).toEqual([
        { type: 'text', text: 'Hello ' },
        { type: 'text', marks: [{ type: 'strong' }], text: 'world' },
      ])
      // No value anywhere in the document looks like markup.
      expect(JSON.stringify(json)).not.toContain('<')
    })

    it('keeps a script payload as literal text', () => {
      const doc = parseHtml('<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>')
      expect(doc.textContent).toBe('<script>alert(1)</script>')
      expect(serialize(doc)).toContain('&lt;script&gt;')
      expect(serialize(doc)).not.toContain('<script')
    })
  })

  // ── Paste is filtered by the schema, not by a blocklist ────────────────────

  describe('parsing untrusted HTML', () => {
    it('drops elements that have no parseDOM rule', () => {
      const doc = parseHtml(
        // No iframe src: happy-dom would try to fetch it, and the point here is
        // that the element never reaches the document either way.
        '<p>before</p><script>alert(1)</script><iframe></iframe><p>after</p>',
      )
      const html = serialize(doc)
      expect(html).not.toContain('script')
      expect(html).not.toContain('iframe')
      expect(doc.textContent).toContain('before')
      expect(doc.textContent).toContain('after')
    })

    it('drops event-handler attributes', () => {
      const doc = parseHtml('<p onclick="alert(1)">text</p>')
      expect(serialize(doc)).not.toContain('onclick')
      expect(doc.textContent).toBe('text')
    })

    it('does not turn a pasted <img> into an image node', () => {
      // image has no parseDOM rule on purpose: images come from the media
      // picker, so a pasted remote URL has nowhere to land.
      const doc = parseHtml('<p>a</p><img src="https://evil.example/track.gif">')
      expect(doc.toJSON().content.some((n: { type: string }) => n.type === 'image')).toBe(false)
    })
  })

  // ── The vector ProseMirror does not close for us ───────────────────────────

  describe('link hrefs', () => {
    it('accepts ordinary links', () => {
      expect(safeHref('https://example.com')).toBe('https://example.com')
      expect(safeHref('/about')).toBe('/about')
      expect(safeHref('mailto:a@b.com')).toBe('mailto:a@b.com')
    })

    it('rejects dangerous schemes, including obfuscated ones', () => {
      expect(safeHref('javascript:alert(1)')).toBeNull()
      expect(safeHref('data:text/html,x')).toBeNull()
      expect(safeHref(OBFUSCATED_JS)).toBeNull()
    })

    it('rejects non-string attribute values', () => {
      expect(safeHref(['https://example.com'])).toBeNull()
      expect(safeHref(null)).toBeNull()
      expect(safeHref(42)).toBeNull()
    })

    it('strips the anchor on paste but keeps its text', () => {
      const doc = parseHtml(`<p>go <a href="${OBFUSCATED_JS}">here</a></p>`)
      expect(serialize(doc)).not.toContain('<a')
      expect(doc.textContent).toBe('go here')
    })

    it('refuses to serialize a dangerous href that reached the document', () => {
      // Belt and braces: a document built from JSON never went through parseDOM.
      const doc = PMNode.fromJSON(schema, {
        type: 'doc',
        content: [{
          type: 'paragraph',
          content: [{
            type: 'text',
            text: 'x',
            marks: [{ type: 'link', attrs: { href: 'javascript:alert(1)' } }],
          }],
        }],
      })
      const html = serialize(doc)
      expect(html).not.toContain('javascript:')
      expect(html).not.toContain('<a')
    })
  })

  // ── Type confusion (CVE-2024-40626 shape) ─────────────────────────────────

  describe('attribute validation', () => {
    const docWith = (attrs: unknown) => ({
      type: 'doc',
      content: [{ type: 'heading', attrs, content: [{ type: 'text', text: 'T' }] }],
    })

    it('rejects an array where a number was declared', () => {
      // Without `validate`, this array would be spliced into the DOM output
      // spec as an element. fromJSON must refuse it instead.
      expect(() => PMNode.fromJSON(schema, docWith({ level: ['script', { src: 'x' }] })))
        .toThrow()
    })

    it('rejects a string where a number was declared', () => {
      expect(() => PMNode.fromJSON(schema, docWith({ level: '3' }))).toThrow()
    })

    it('rejects an array where a string was declared', () => {
      expect(() => PMNode.fromJSON(schema, {
        type: 'doc',
        content: [{ type: 'image', attrs: { width: ['img', { onerror: 'alert(1)' }] } }],
      })).toThrow()
    })

    it('rejects an unknown node type', () => {
      expect(() => PMNode.fromJSON(schema, {
        type: 'doc',
        content: [{ type: 'evil', content: [] }],
      })).toThrow()
    })

    it('clamps an out-of-range heading level rather than emitting <h99>', () => {
      // A number is the declared type, so validate passes; the clamp in toDOM
      // is what keeps it out of the tag name.
      const doc = PMNode.fromJSON(schema, docWith({ level: 99 }))
      expect(serialize(doc)).toMatch(/^<h[2-6]>T<\/h[2-6]>$/)
    })
  })

  // ── Vocabulary, pinned so the PHP mirror can be compared against it ────────

  describe('vocabulary', () => {
    it('exposes exactly the node types the PHP renderer knows', () => {
      expect(Object.keys(schema.nodes).sort()).toEqual([
        'blockquote', 'bullet_list', 'code_block', 'doc', 'hard_break',
        'heading', 'image', 'list_item', 'ordered_list', 'paragraph', 'text',
      ])
    })

    it('exposes exactly the mark types the PHP renderer knows', () => {
      expect(Object.keys(schema.marks).sort()).toEqual(['code', 'em', 'link', 'strong'])
    })

    it('pins the attribute allowlists', () => {
      expect(HEADING_LEVELS).toEqual([2, 3, 4, 5, 6])
      expect(IMAGE_WIDTHS).toEqual(['default', 'wide', 'full'])
      expect(CODE_LANGUAGES).toContain('php')
      expect(CODE_LANGUAGES).toContain('plain')
    })

    it('forbids marks inside a code block', () => {
      expect(schema.nodes.code_block.spec.marks).toBe('')
    })
  })
})
