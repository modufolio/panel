/**
 * Editing behaviour for the builder: input rules, keymap, and the queries the
 * toolbar needs to show which formats are active.
 *
 * Everything here goes through ProseMirror commands and transactions, so the
 * schema stays the only thing that decides what a document may contain. There
 * is no `document.execCommand` and no `innerHTML` anywhere in this file — that
 * is the whole point of the migration.
 */

import { type Command, type EditorState, NodeSelection, TextSelection } from 'prosemirror-state'
import {
  toggleMark, setBlockType, wrapIn, lift,
  chainCommands, exitCode, baseKeymap,
} from 'prosemirror-commands'
import { keymap } from 'prosemirror-keymap'
import { history, undo, redo } from 'prosemirror-history'
import {
  inputRules,
  wrappingInputRule,
  textblockTypeInputRule,
  smartQuotes,
  ellipsis,
  undoInputRule,
} from 'prosemirror-inputrules'
import { wrapInList, splitListItem, liftListItem, sinkListItem } from 'prosemirror-schema-list'
import type { MarkType, NodeType } from 'prosemirror-model'

import { schema, HEADING_LEVELS } from './schema'

const {
  paragraph, heading, blockquote, code_block: codeBlock,
  bullet_list: bulletList, ordered_list: orderedList, list_item: listItem,
  hard_break: hardBreak,
} = schema.nodes

const { strong, em, code, link } = schema.marks

// ── Queries ──────────────────────────────────────────────────────────────────

/** Whether `type` applies anywhere in the current selection. */
export function isMarkActive(state: EditorState, type: MarkType): boolean {
  const { from, $from, to, empty } = state.selection

  return empty
    ? !!type.isInSet(state.storedMarks || $from.marks())
    : state.doc.rangeHasMark(from, to, type)
}

/** Whether the selection sits inside a `type` block with these attrs. */
export function isBlockActive(
  state: EditorState,
  type: NodeType,
  attrs: Record<string, unknown> = {},
): boolean {
  const { selection } = state

  if (selection instanceof NodeSelection) {
    return selection.node.hasMarkup(type, attrs)
  }

  const { $from, to } = selection

  return to <= $from.end() && $from.parent.hasMarkup(type, attrs)
}

/** Whether the selection is inside a list of this type. */
export function isInList(state: EditorState, type: NodeType): boolean {
  const { $from } = state.selection

  for (let depth = $from.depth; depth > 0; depth--) {
    if ($from.node(depth).type === type) return true
  }

  return false
}

/** The href on the link mark under the cursor, or '' if there is none. */
export function activeLinkHref(state: EditorState): string {
  const { $from, from, to } = state.selection
  const mark = link.isInSet($from.marks()) ?? state.doc.rangeHasMark(from, to, link)

  if (mark && typeof mark !== 'boolean' && typeof mark.attrs.href === 'string') {
    return mark.attrs.href
  }

  // Selection spanning a link: find the first link mark inside it.
  let href = ''
  state.doc.nodesBetween(from, to, (node) => {
    if (href !== '') return false
    const found = node.marks.find((m) => m.type === link)
    if (found && typeof found.attrs.href === 'string') href = found.attrs.href
    return true
  })

  return href
}

// ── Commands ─────────────────────────────────────────────────────────────────

export const commands = {
  toggleStrong: toggleMark(strong),
  toggleEm: toggleMark(em),
  toggleCode: toggleMark(code),

  setParagraph: setBlockType(paragraph),
  setHeading: (level: number): Command => setBlockType(heading, { level }),
  setCodeBlock: setBlockType(codeBlock),

  toggleBulletList: wrapInList(bulletList),
  toggleOrderedList: wrapInList(orderedList),

  wrapBlockquote: wrapIn(blockquote),
  lift,

  undo,
  redo,

  /**
   * Apply or replace the link mark over the selection.
   *
   * `href` is not validated here — `schema.marks.link` does it in `toDOM`, and
   * the field validates before calling. Passing '' removes the mark.
   */
  setLink: (href: string): Command => (state, dispatch) => {
    const { from, to, empty } = state.selection

    if (empty) return false

    if (dispatch) {
      const tr = state.tr.removeMark(from, to, link)
      if (href !== '') tr.addMark(from, to, link.create({ href }))
      dispatch(tr.scrollIntoView())
    }

    return true
  },

  /**
   * Replace the current (empty) textblock with a node of `type`.
   * Used by the slash menu, where the user has typed `/` into a fresh block.
   */
  replaceBlockWith: (type: NodeType, attrs: Record<string, unknown> = {}): Command =>
    (state, dispatch) => {
      const { $from } = state.selection
      const start = $from.before($from.depth)
      const end = $from.after($from.depth)

      if (dispatch) {
        const node = type.createAndFill(attrs)
        if (!node) return false

        const tr = state.tr.replaceWith(start, end, node)
        // Put the caret inside the new node when it can hold text.
        const pos = Math.min(start + 1, tr.doc.content.size)
        tr.setSelection(TextSelection.near(tr.doc.resolve(pos)))
        dispatch(tr.scrollIntoView())
      }

      return true
    },
}

// ── Input rules ──────────────────────────────────────────────────────────────

/**
 * Markdown-ish shortcuts. These are *not* a Markdown parser — each rule fires
 * on a literal prefix as it is typed and produces a schema node directly, so
 * there is no intermediate HTML and nothing to keep in sync with the server.
 */
export function builderInputRules() {
  return inputRules({
    rules: [
      ...smartQuotes,
      ellipsis,

      // "> " at the start of a textblock
      wrappingInputRule(/^\s*>\s$/, blockquote),

      // "- " / "* " / "+ "
      wrappingInputRule(/^\s*([-+*])\s$/, bulletList),

      // "1. "
      wrappingInputRule(
        /^(\d+)\.\s$/,
        orderedList,
        (match) => ({ order: Number(match[1]) }),
        (match, node) => node.childCount + node.attrs.order === Number(match[1]),
      ),

      // "## " … "###### " — level 1 is the page title, so headings start at 2.
      textblockTypeInputRule(
        new RegExp(`^(#{${HEADING_LEVELS[0]},${HEADING_LEVELS[HEADING_LEVELS.length - 1]}})\\s$`),
        heading,
        (match) => ({ level: match[1].length }),
      ),

      // "```"
      textblockTypeInputRule(/^```$/, codeBlock),
    ],
  })
}

// ── Keymap ───────────────────────────────────────────────────────────────────

const isMac = typeof navigator !== 'undefined' && /Mac|iP(hone|ad|od)/.test(navigator.platform)
const mod = isMac ? 'Meta' : 'Ctrl'

/**
 * `onLink` is called for the link shortcut; the field opens its own input
 * rather than a `window.prompt`, so the keymap only signals intent.
 */
export function builderKeymap(onLink: () => void) {
  const insertBreak: Command = (state, dispatch) => {
    if (dispatch) {
      dispatch(state.tr.replaceSelectionWith(hardBreak.create()).scrollIntoView())
    }
    return true
  }

  const bindings: Record<string, Command> = {
    [`${mod}-b`]: commands.toggleStrong,
    [`${mod}-i`]: commands.toggleEm,
    [`${mod}-e`]: commands.toggleCode,
    [`${mod}-z`]: undo,
    [`Shift-${mod}-z`]: redo,
    [`${mod}-y`]: redo,
    [`${mod}-k`]: () => { onLink(); return true },

    'Shift-Enter': chainCommands(exitCode, insertBreak),

    Enter: splitListItem(listItem),
    Tab: sinkListItem(listItem),
    'Shift-Tab': liftListItem(listItem),

    Backspace: undoInputRule,

    [`Shift-${mod}-0`]: commands.setParagraph,
    [`Shift-${mod}-\\`]: commands.wrapBlockquote,
  }

  for (const level of HEADING_LEVELS) {
    bindings[`Shift-${mod}-${level}`] = commands.setHeading(level)
  }

  return [keymap(bindings), keymap(baseKeymap)]
}

export function builderHistory() {
  return history()
}
