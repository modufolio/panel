/**
 * The builder's ProseMirror schema.
 *
 * This is the contract for stored content. A document that satisfies it holds
 * no HTML: text is literal text, formatting is a closed set of marks, and
 * attributes are typed scalars. That is what lets the PHP renderer *construct*
 * markup from a fixed vocabulary instead of sanitizing arbitrary HTML — see
 * `src/Content/ProseMirror/Renderer.php`, and `docs/prosemirror-builder.md`
 * for why the old HTML-in-JSON format could not be made safe.
 *
 * Two rules apply to everything below, both load-bearing:
 *
 *  1. **Every attribute declares `validate`.** `Node.fromJSON` then type-checks
 *     attrs and throws on corrupt input, instead of handing an attacker-shaped
 *     value to `toDOM`. Without it, an attribute declared as a string can
 *     arrive as an array and be spliced into the DOM output spec as an element
 *     — the type-confusion shape behind CVE-2024-40626 in Outline.
 *  2. **No attribute is ever interpolated into a tag name**, and none is passed
 *     through to `toDOM` unvalidated. Editors that store serialized HTML can
 *     get away with ``toDOM: node => [`h${node.attrs.level}`, 0]``, because
 *     their content only ever enters through `parseDOM` and `level` can only
 *     come from the tag that matched. Storing JSON makes `nodeFromJSON` the
 *     entry point, so the same line becomes a hole.
 */

import { Schema, type DOMOutputSpec, type Mark, type Node as PMNode } from 'prosemirror-model'
import { sanitizeUrl } from '../Utils/url'

// ── Shared vocabulary ────────────────────────────────────────────────────────
// Exported so the parity test can assert the PHP mirror allows exactly this.

export const HEADING_LEVELS = [2, 3, 4, 5, 6] as const
export const IMAGE_WIDTHS = ['default', 'wide', 'full'] as const
export const CODE_LANGUAGES = [
  'plain', 'bash', 'css', 'html', 'json', 'js', 'ts',
  'php', 'python', 'sql', 'vue', 'yaml', 'markdown',
] as const

export type HeadingLevel = (typeof HEADING_LEVELS)[number]
export type ImageWidth = (typeof IMAGE_WIDTHS)[number]
export type CodeLanguage = (typeof CODE_LANGUAGES)[number]

// ── Attribute coercion ───────────────────────────────────────────────────────
// `validate` rejects the wrong *type*; these clamp the wrong *value*. Both are
// needed: `level: 99` is a valid number and still must not reach a tag name.

function clampLevel(value: unknown): HeadingLevel {
  const n = typeof value === 'number' ? Math.trunc(value) : NaN
  return (HEADING_LEVELS as readonly number[]).includes(n) ? (n as HeadingLevel) : 2
}

function clampWidth(value: unknown): ImageWidth {
  return (IMAGE_WIDTHS as readonly string[]).includes(value as string)
    ? (value as ImageWidth)
    : 'default'
}

function clampLanguage(value: unknown): CodeLanguage {
  return (CODE_LANGUAGES as readonly string[]).includes(value as string)
    ? (value as CodeLanguage)
    : 'plain'
}

/**
 * An href we are willing to render, or null.
 *
 * Applied on the way in (`parseDOM`, so a pasted link cannot smuggle a scheme
 * into the document) and again on the way out (`toDOM`, so a document that
 * reached us some other way still cannot render one). ProseMirror does not
 * validate URLs — this is the one classic XSS vector the document model does
 * not close on its own, and it has produced High-severity advisories in other
 * ProseMirror-based CMSs (see docs/prosemirror-builder.md §3.3).
 */
export function safeHref(value: unknown): string | null {
  if (typeof value !== 'string') return null
  const href = sanitizeUrl(value)
  return href === '' ? null : href
}

// ── Schema ───────────────────────────────────────────────────────────────────

export const schema = new Schema({
  nodes: {
    doc: { content: 'block+' },

    text: { group: 'inline' },

    paragraph: {
      content: 'inline*',
      group: 'block',
      parseDOM: [{ tag: 'p' }],
      toDOM: (): DOMOutputSpec => ['p', { class: 'pf-block pf-block-paragraph' }, 0],
    },

    heading: {
      content: 'inline*',
      group: 'block',
      defining: true,
      attrs: { level: { default: 2, validate: 'number' } },
      parseDOM: HEADING_LEVELS.map((level) => ({ tag: `h${level}`, attrs: { level } })),
      // clampLevel, not node.attrs.level: this value reaches a tag name.
      toDOM: (node: PMNode): DOMOutputSpec => [`h${clampLevel(node.attrs.level)}`, 0],
    },

    blockquote: {
      content: 'block+',
      group: 'block',
      defining: true,
      parseDOM: [{ tag: 'blockquote' }],
      toDOM: (): DOMOutputSpec => ['blockquote', { class: 'pf-block pf-block-quote' }, 0],
    },

    code_block: {
      content: 'text*',
      group: 'block',
      marks: '',          // no inline formatting inside code
      code: true,
      defining: true,
      attrs: { language: { default: 'plain', validate: 'string' } },
      parseDOM: [{ tag: 'pre', preserveWhitespace: 'full' }],
      toDOM: (node: PMNode): DOMOutputSpec => [
        'pre',
        { class: 'pf-block pf-block-code' },
        ['code', { class: `language-${clampLanguage(node.attrs.language)}` }, 0],
      ],
    },

    bullet_list: {
      content: 'list_item+',
      group: 'block',
      parseDOM: [{ tag: 'ul' }],
      toDOM: (): DOMOutputSpec => ['ul', { class: 'pf-block pf-block-list pf-block-list-ul' }, 0],
    },

    ordered_list: {
      content: 'list_item+',
      group: 'block',
      parseDOM: [{ tag: 'ol' }],
      toDOM: (): DOMOutputSpec => ['ol', { class: 'pf-block pf-block-list pf-block-list-ol' }, 0],
    },

    list_item: {
      content: 'paragraph block*',
      defining: true,
      parseDOM: [{ tag: 'li' }],
      toDOM: (): DOMOutputSpec => ['li', 0],
    },

    image: {
      group: 'block',
      atom: true,
      draggable: true,
      attrs: {
        id:            { default: null, validate: 'string|null' },
        url:           { default: '',   validate: 'string' },
        thumbnail_url: { default: '',   validate: 'string' },
        alt:           { default: '',   validate: 'string' },
        caption:       { default: '',   validate: 'string' },
        width:         { default: 'default', validate: 'string' },
      },
      // Deliberately no parseDOM rule. Images enter through the media picker,
      // which supplies a real media id; a pasted <img> has nowhere to become
      // one, so remote URLs cannot be smuggled in by pasting.
      toDOM: (node: PMNode): DOMOutputSpec => [
        'figure',
        { class: `pf-block pf-block-image pf-block-image-${clampWidth(node.attrs.width)}` },
      ],
    },

    hard_break: {
      inline: true,
      group: 'inline',
      selectable: false,
      parseDOM: [{ tag: 'br' }],
      toDOM: (): DOMOutputSpec => ['br'],
    },
  },

  marks: {
    strong: {
      parseDOM: [{ tag: 'strong' }, { tag: 'b' }, { style: 'font-weight=bold' }],
      toDOM: (): DOMOutputSpec => ['strong', 0],
    },
    em: {
      parseDOM: [{ tag: 'em' }, { tag: 'i' }, { style: 'font-style=italic' }],
      toDOM: (): DOMOutputSpec => ['em', 0],
    },
    code: {
      parseDOM: [{ tag: 'code' }],
      toDOM: (): DOMOutputSpec => ['code', 0],
    },
    link: {
      inclusive: false,
      attrs: { href: { default: null, validate: 'string|null' } },
      parseDOM: [{
        tag: 'a[href]',
        getAttrs: (dom: HTMLElement) => {
          const href = safeHref(dom.getAttribute('href'))
          // Returning false rejects the rule, so the <a> is dropped and only
          // its text survives — the link never enters the document.
          return href === null ? false : { href }
        },
      }],
      toDOM: (mark: Mark): DOMOutputSpec => {
        const href = safeHref(mark.attrs.href)
        return href === null ? ['span', 0] : ['a', { href, rel: 'noopener' }, 0]
      },
    },
  },
})

export type BuilderSchema = typeof schema
