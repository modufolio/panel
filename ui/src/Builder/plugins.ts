/**
 * Small view plugins: an empty-document placeholder and the slash-menu trigger.
 *
 * Both are decoration/state only — neither writes to the document except through
 * the commands in `editing.ts`.
 */

import { Plugin, PluginKey, type EditorState } from 'prosemirror-state'
import { Decoration, DecorationSet } from 'prosemirror-view'

// ── Placeholder ──────────────────────────────────────────────────────────────

/**
 * Show `text` on the first block while the document is empty.
 *
 * Implemented as a widget-free node decoration carrying a CSS class, so the
 * prompt lives in a `::before` and can never be mistaken for content.
 */
export function placeholder(text: string): Plugin {
  return new Plugin({
    props: {
      decorations(state: EditorState) {
        const { doc } = state
        const isEmpty =
          doc.childCount === 1 &&
          doc.firstChild?.isTextblock === true &&
          doc.firstChild.content.size === 0

        if (!isEmpty) return null

        return DecorationSet.create(doc, [
          Decoration.node(0, doc.firstChild!.nodeSize, {
            class: 'pm-placeholder',
            'data-placeholder': text,
          }),
        ])
      },
    },
  })
}

// ── Slash menu ───────────────────────────────────────────────────────────────

export const slashMenuKey = new PluginKey<SlashMenuState>('builderSlashMenu')

export interface SlashMenuState {
  /** Position of the `/` in the document, or null when the menu is closed. */
  from: number | null
  /** What the user has typed after the `/`, for filtering. */
  query: string
}

/**
 * Track a `/` typed at the start of an empty paragraph, plus whatever is typed
 * after it, so the field can render a filtered block menu.
 *
 * State only — the menu component decides what to show and dispatches the
 * command. Closing is driven by the text no longer matching, so Escape and any
 * edit elsewhere both dismiss it without extra bookkeeping.
 */
export function slashMenu(): Plugin<SlashMenuState> {
  return new Plugin<SlashMenuState>({
    key: slashMenuKey,

    state: {
      init: (): SlashMenuState => ({ from: null, query: '' }),

      apply(tr, _value, _oldState, newState): SlashMenuState {
        const meta = tr.getMeta(slashMenuKey) as { close?: boolean } | undefined
        if (meta?.close) return { from: null, query: '' }

        const { selection } = newState
        if (!selection.empty) return { from: null, query: '' }

        const $pos = selection.$from
        const parent = $pos.parent

        if (!parent.isTextblock || parent.type.name !== 'paragraph') {
          return { from: null, query: '' }
        }

        const textBefore = parent.textBetween(0, $pos.parentOffset, undefined, '￼')

        // Only a `/` that opens the block counts, and only while what follows
        // still looks like a command word.
        const match = /^\/(\w*)$/.exec(textBefore)
        if (!match) return { from: null, query: '' }

        return { from: $pos.start(), query: match[1] }
      },
    },
  })
}

export function getSlashMenuState(state: EditorState): SlashMenuState {
  return slashMenuKey.getState(state) ?? { from: null, query: '' }
}

/** Close the menu and remove the `/query` text the user typed. */
export function closeSlashMenu(state: EditorState, clearText: boolean) {
  const { from } = getSlashMenuState(state)
  const tr = state.tr.setMeta(slashMenuKey, { close: true })

  if (clearText && from !== null) {
    tr.delete(from, state.selection.from)
  }

  return tr
}
