import { describe, expect, it } from 'vitest'
import { ref } from 'vue'
import { usePagination } from '../src/Composables/usePagination'

function items(totalPages: number, page: number) {
  const { paginationItems } = usePagination({
    pagination: ref({ totalPages }),
    currentPage: ref(page),
    onNavigate: () => {},
  })

  return paginationItems.value.map((item) =>
    item.type === 'page' ? item.value : '…',
  )
}

describe('usePagination', () => {
  it('lists every page up to seven', () => {
    expect(items(7, 4)).toEqual([1, 2, 3, 4, 5, 6, 7])
  })

  it('windows around the current page with ellipses on both sides', () => {
    expect(items(20, 10)).toEqual([1, '…', 8, 9, 10, 11, 12, '…', 20])
  })

  it('needs no leading ellipsis near the start', () => {
    expect(items(20, 2)).toEqual([1, 2, 3, 4, '…', 20])
  })

  it('needs no trailing ellipsis near the end', () => {
    expect(items(20, 19)).toEqual([1, '…', 17, 18, 19, 20])
  })

  it('never renders an ellipsis for a gap of one', () => {
    // Page 4's window reaches 2..6; the gap between 1 and 2 is not a gap.
    expect(items(20, 4)).toEqual([1, 2, 3, 4, 5, 6, '…', 20])
  })
})
