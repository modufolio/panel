/**
 * Reading and writing the builder's stored value.
 *
 * The only format the runtime understands is a ProseMirror document. The older
 * formats — EditorJS-shaped block JSON, and bare Markdown or prose — are
 * converted once by `scripts/migrate-content.ts` and are not read here.
 *
 * That is deliberate. Accepting them at runtime meant every reader had to carry
 * a converter, and a converter is a parser: the previous builder ended up with
 * one in the browser and a different one on the server, disagreeing about the
 * same input (docs/prosemirror-builder.md §1.5). One format in storage, one
 * parser, no fallbacks.
 *
 * A value that is not a valid document is treated as *unreadable*, never as
 * something to guess at. The field surfaces that rather than silently opening
 * an empty editor over content it did not understand — which would destroy it
 * on the next save.
 */

import { Node as PMNode } from 'prosemirror-model'
import { schema } from './schema'

export type ParseStatus = 'ok' | 'empty' | 'unreadable'

export interface ParseResult {
  doc: PMNode
  status: ParseStatus
  /** Set when status is 'unreadable': why, for the field to show. */
  reason?: string
}

export function emptyDoc(): PMNode {
  return schema.node('doc', null, [schema.node('paragraph')])
}

/**
 * Read a stored field value.
 *
 * Never throws. A document that cannot be parsed comes back as `unreadable`
 * with an empty document alongside, so the caller can refuse to overwrite it.
 */
export function parseStoredValue(value: string): ParseResult {
  const trimmed = String(value ?? '').trim()

  if (trimmed === '') {
    return { doc: emptyDoc(), status: 'empty' }
  }

  let decoded: unknown

  try {
    decoded = JSON.parse(trimmed)
  } catch {
    return {
      doc: emptyDoc(),
      status: 'unreadable',
      reason: 'not valid JSON — it may still be in a pre-migration format',
    }
  }

  if (!decoded || typeof decoded !== 'object' || (decoded as { type?: unknown }).type !== 'doc') {
    return {
      doc: emptyDoc(),
      status: 'unreadable',
      reason: 'not a ProseMirror document — it may still be in a pre-migration format',
    }
  }

  try {
    const doc = PMNode.fromJSON(schema, decoded)
    // fromJSON checks node and mark types but not content expressions, so a
    // `doc` with no blocks in it parses happily and then misbehaves in the
    // editor. check() is what actually holds the document to the schema.
    doc.check()
    return { doc, status: 'ok' }
  } catch (error) {
    // Written against a different schema, or corrupt. Refuse to guess.
    return {
      doc: emptyDoc(),
      status: 'unreadable',
      reason: String((error as Error)?.message ?? error),
    }
  }
}

export function serializeDoc(doc: PMNode): string {
  return JSON.stringify(doc.toJSON())
}
