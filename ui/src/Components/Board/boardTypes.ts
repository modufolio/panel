/**
 * The shapes the server sends for a board view.
 *
 * Mirrors ResourceView::toArray() and ResourceListing::board() — the client
 * derives nothing about a board's structure, because which columns exist and
 * whether cards can be reordered are facts the declaration owns.
 */

export interface BoardCard {
  /** Whatever the presenter calls `id` — across this panel, the public uuid. */
  id: string | number
  [key: string]: unknown
}

/** A column a card may be moved to, as offered on the card itself. */
export interface BoardMoveTarget {
  value: string
  label: string
  color: string | null
}

export interface BoardColumn {
  value: string
  label: string
  color: string | null
  /** Cards matching this column, before the per-column limit is applied. */
  total: number
  cards: BoardCard[]
  /**
   * Per card id, the columns it may move to — the server asked its own
   * canMoveTo() for each, so a button offered is a move that will be accepted.
   * Empty unless the view declares `quickMove()`.
   */
  moves: Record<string, BoardMoveTarget[]>
}

export interface BoardViewSpec {
  key: string
  label: string
  icon: string | null
  type: 'board'
  groupBy: string
  columns: Array<{ value: string; label: string; color: string | null }>
  /** False when the view declares no position field: moves between columns
   *  are saved, order within one is not, so the board must not pretend. */
  sortable: boolean
  cardTitle: string | null
  cardFields: string[]
  limit: number
  /** Whether cards carry a button per column they may move to. */
  quickMove: boolean
}

export interface BoardPayload {
  view: BoardViewSpec
  columns: BoardColumn[]
}

/** One entry in the listing's view switcher. */
export interface ResourceViewOption {
  key: string
  label: string
  icon: string | null
  type: string
}
