import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'

// Hoisted with the mock: a plain `const` here would be initialised after it.
const { reload, flushAll } = vi.hoisted(() => ({ reload: vi.fn(), flushAll: vi.fn() }))
vi.mock('@inertiajs/vue3', () => ({ router: { reload, flushAll } }))

import { useBoardMove } from '../src/Components/Resource/useBoardMove'
import type { BoardCard } from '../src/Components/Board/boardTypes'

const card: BoardCard = { id: 'c1', title: 'Ship it' }

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'content-type': 'application/json' },
  })
}

let fetchMock: ReturnType<typeof vi.fn>

beforeEach(() => {
  reload.mockClear()
  flushAll.mockClear()
  fetchMock = vi.fn(async () => jsonResponse({}))
  vi.stubGlobal('fetch', fetchMock)
})

afterEach(() => {
  vi.unstubAllGlobals()
})

function board(viewKey: string | undefined = 'status') {
  return useBoardMove({ baseUrl: () => '/panel/tasks', viewKey: () => viewKey })
}

describe('useBoardMove', () => {
  it('posts where the card landed, and never a position', async () => {
    const { moveCard, moveError } = board()

    await moveCard({ card, column: 'done', after: 'c0', before: 'c2' })

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toBe('/panel/tasks/c1/board-move')
    expect(init.method).toBe('POST')
    expect(JSON.parse(init.body)).toEqual({
      view: 'status',
      column: 'done',
      after: 'c0',
      before: 'c2',
    })
    expect(moveError.value).toBeNull()
  })

  it('leaves the board alone when the move is accepted', async () => {
    const { moveCard } = board()

    await moveCard({ card, column: 'done', after: null, before: null })

    expect(reload).not.toHaveBeenCalled()
  })

  it('re-reads the board on a refusal rather than undoing locally', async () => {
    fetchMock.mockImplementation(async () =>
      jsonResponse({ message: 'That column is full.' }, 422),
    )
    const errors = vi.spyOn(console, 'error').mockImplementation(() => {})

    const { moveCard, moveError } = board()
    await moveCard({ card, column: 'done', after: null, before: null })

    expect(moveError.value).toBe('That column is full.')
    expect(reload).toHaveBeenCalledWith({ only: ['board', 'flash', 'errors'] })
    errors.mockRestore()
  })

  it('falls back to its own sentence when the server gave none', async () => {
    fetchMock.mockImplementation(async () => { throw new TypeError('offline') })
    const errors = vi.spyOn(console, 'error').mockImplementation(() => {})

    const { moveCard, moveError } = board()
    await moveCard({ card, column: 'done', after: null, before: null })

    expect(moveError.value).toBe('That move could not be saved.')
    expect(reload).toHaveBeenCalled()
    errors.mockRestore()
  })

  it('clears the previous refusal before trying again', async () => {
    fetchMock.mockImplementation(async () => jsonResponse({ message: 'No.' }, 422))
    const errors = vi.spyOn(console, 'error').mockImplementation(() => {})

    const { moveCard, moveError } = board()
    await moveCard({ card, column: 'done', after: null, before: null })
    expect(moveError.value).toBe('No.')

    fetchMock.mockImplementation(async () => jsonResponse({}))
    await moveCard({ card, column: 'done', after: null, before: null })

    expect(moveError.value).toBeNull()
    errors.mockRestore()
  })
})
