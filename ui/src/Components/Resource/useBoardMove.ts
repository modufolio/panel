import { ref, type Ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { apiFetch, ApiError } from '../../Utils/apiFetch'
import type { BoardCard } from '../Board/boardTypes'

/** Where the card landed: the column, and the cards either side of the gap. */
export interface BoardMove {
  card: BoardCard
  column: string
  after: string | null
  before: string | null
}

export interface UseBoardMoveOptions {
  /** The resource's base path, which the move endpoint hangs off. */
  baseUrl: () => string
  /** Which view was dragged in — a resource may declare more than one board. */
  viewKey: () => string | undefined
}

/**
 * Persisting one drag.
 *
 * The board reports where the card landed and never a position: two people can
 * drop into the same gap at the same instant, and only the server sees both.
 * A refusal reloads, which puts the card back where the server says it still
 * is — re-reading rather than undoing locally, because a hand-rolled undo
 * would disagree with the server the moment someone else moved something too.
 */
export function useBoardMove({ baseUrl, viewKey }: UseBoardMoveOptions): {
  /** Why the last drag was put back, if it was. */
  moveError: Ref<string | null>
  moveCard: (payload: BoardMove) => Promise<void>
} {
  const moveError = ref<string | null>(null)

  async function moveCard(payload: BoardMove): Promise<void> {
    // A previous refusal describes a move that is over; this one starts clean.
    moveError.value = null

    try {
      await apiFetch(`${baseUrl()}/${payload.card.id}/board-move`, {
        method: 'POST',
        body: {
          view: viewKey(),
          column: payload.column,
          after: payload.after,
          before: payload.before,
        },
      })

      return
    } catch (error) {
      console.error(error)
      // The server's own sentence when it gave one — ApiError already prefers
      // it over the status — and a plain refusal otherwise.
      moveError.value = error instanceof ApiError ? error.message : 'That move could not be saved.'
    }

    router.reload({ only: ['board', 'flash', 'errors'] })
  }

  return { moveError, moveCard }
}
