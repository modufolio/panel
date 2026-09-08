import { describe, it, expect, vi, beforeEach } from 'vitest'

const visit = vi.fn()
vi.mock('@inertiajs/vue3', () => ({ router: { visit, prefetch: vi.fn(), flushAll: vi.fn() } }))
const apiFetch = vi.fn()
vi.mock('../src/Utils/apiFetch', () => ({ apiFetch }))

const { useGlobalSearch } = await import('../src/Components/Search/useGlobalSearch')

const answer = {
  query: 'he',
  groups: [
    { key: 'movies', label: 'Movies', total: 2, items: [{ title: 'Heat', details: ['1995'], href: '/panel/movies/a' }, { title: 'The Heist', details: [], href: '/panel/movies/b' }] },
    { key: 'users', label: 'Users', total: 1, items: [{ title: 'Helen', details: [], href: '/panel/users/c' }] },
  ],
}

/**
 * The composable behind the dialog: a debounced request, hits in one order
 * across groups, a cursor the arrows move through them, and Enter opening
 * the one under it. A slow earlier answer never overwrites a newer one.
 */
describe('useGlobalSearch', () => {
  beforeEach(() => {
    visit.mockClear()
    apiFetch.mockReset()
  })

  it('asks the server and flattens the groups into one list of hits', async () => {
    apiFetch.mockResolvedValue(answer)
    const search = useGlobalSearch('/panel/search')

    await search.run('he')

    expect(apiFetch).toHaveBeenCalledWith('/panel/search?q=he')
    expect(search.hits.value.map((hit) => hit.title)).toEqual(['Heat', 'The Heist', 'Helen'])
    expect(search.active.value?.title).toBe('Heat')
  })

  it('walks the hits with wrap-around and opens the active one', async () => {
    apiFetch.mockResolvedValue(answer)
    const search = useGlobalSearch('/panel/search')
    await search.run('he')

    search.move(1)
    search.move(1)
    expect(search.active.value?.title).toBe('Helen')
    search.move(1)
    expect(search.active.value?.title).toBe('Heat')
    search.move(-1)
    expect(search.active.value?.title).toBe('Helen')

    search.open()
    expect(visit).toHaveBeenCalledWith('/panel/users/c')
  })

  it('clears on an empty query and keeps the newest answer', async () => {
    const search = useGlobalSearch('/panel/search')
    let resolveSlow: (value: unknown) => void = () => {}
    apiFetch.mockImplementationOnce(() => new Promise((resolve) => { resolveSlow = resolve }))
    apiFetch.mockResolvedValueOnce({ query: 'hel', groups: [answer.groups[1]] })

    const slow = search.run('he')
    const fast = search.run('hel')
    await fast
    expect(search.hits.value.map((hit) => hit.title)).toEqual(['Helen'])

    resolveSlow(answer)
    await slow
    // The earlier, slower answer is dropped.
    expect(search.hits.value.map((hit) => hit.title)).toEqual(['Helen'])

    await search.run('   ')
    expect(search.hits.value).toEqual([])
    expect(apiFetch).toHaveBeenCalledTimes(2)
  })
})
