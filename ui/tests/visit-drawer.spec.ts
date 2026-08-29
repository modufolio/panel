import { describe, it, expect, vi, beforeEach } from 'vitest'

const visit = vi.fn()
vi.mock('@inertiajs/vue3', () => ({ router: { visit } }))

const { visitDrawer, withDrawerParams } = await import('../src/Components/Drawer/visitDrawer')
const { DRAWER_HEADER } = await import('../src/Components/Drawer/useIsDrawer')

/**
 * The drawer protocol is a convention held in three places at once — the
 * header, the partial-reload key, and the preserve flags. A caller that gets
 * one third of it wrong still *looks* right: fetching only the stack renders
 * a drawer even when the server never took the drawer path. That is exactly
 * how a hand-written `X-Drawer` header survived a manual check, so the trio
 * is asserted here rather than trusted.
 */
describe('visitDrawer', () => {
  beforeEach(() => {
    visit.mockClear()
  })

  it('sends the protocol header the server keys off', () => {
    visitDrawer('/panel/movies/42')

    expect(visit).toHaveBeenCalledWith('/panel/movies/42', expect.objectContaining({
      headers: { [DRAWER_HEADER]: '1' },
    }))
  })

  it('reloads the stack alone, keeping the page underneath mounted', () => {
    visitDrawer('/panel/movies/42')

    expect(visit).toHaveBeenCalledWith('/panel/movies/42', expect.objectContaining({
      only: ['stack'],
      preserveState: true,
      preserveScroll: true,
    }))
  })

  it('carries the listing state so record pagination stays in the same set', () => {
    visitDrawer('/panel/movies/42', { queryParams: { sort: 'title', genre: 'Drama' } })

    expect(visit).toHaveBeenCalledWith(
      '/panel/movies/42?sort=title&genre=Drama',
      expect.anything(),
    )
  })

  it('still sends the header when a caller overrides the reload keys', () => {
    visitDrawer('/panel/movies/42', { only: ['stack', 'flash'] })

    expect(visit).toHaveBeenCalledWith('/panel/movies/42', expect.objectContaining({
      only: ['stack', 'flash'],
      headers: { [DRAWER_HEADER]: '1' },
    }))
  })
})

describe('withDrawerParams', () => {
  it('keeps empty values, which the server reads as "set to nothing"', () => {
    expect(withDrawerParams('/panel/movies/1', { search: '', sort: 'title' }))
      .toBe('/panel/movies/1?search=&sort=title')
  })

  it('drops absent values rather than sending the string "null"', () => {
    expect(withDrawerParams('/panel/movies/1', { search: null, sort: undefined, page: 2 }))
      .toBe('/panel/movies/1?page=2')
  })

  it('appends to a URL that already carries a query string', () => {
    expect(withDrawerParams('/panel/movies/1?tab=cast', { sort: 'title' }))
      .toBe('/panel/movies/1?tab=cast&sort=title')
  })

  it('leaves the URL alone when there is nothing to add', () => {
    expect(withDrawerParams('/panel/movies/1')).toBe('/panel/movies/1')
  })
})
