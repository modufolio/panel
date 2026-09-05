import { describe, it, expect, vi } from 'vitest'

// Hoisted with the mock: the static imports above load the composable's
// module graph before any plain `const` here would be initialised.
const { get } = vi.hoisted(() => ({ get: vi.fn() }))
vi.mock('@inertiajs/vue3', () => ({ router: { get, visit: vi.fn() } }))
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import { useResourceListing, humanize } from '../src/Composables/useResourceListing'
import type { TableSchema } from '../src/Components/Table/tableSchema'
import { setPanelBaseUrl } from '../src/Utils/url'

const table = {
  columns: [
    { key: 'title', label: 'Title', type: 'text' },
    { key: 'location', label: 'Location', type: 'text', hiddenByDefault: true },
  ],
} as unknown as TableSchema

const resource = {
  key: 'events',
  baseUrl: '/events',
  drawerType: 'event',
  canCreate: false,
  canEdit: false,
  canDelete: false,
  exportUrl: null,
}

/**
 * The composable reads rows out of $attrs, so it has to run inside a component
 * with those attrs actually bound — not from a bare call.
 */
function mountWith(attrs: Record<string, unknown> = {}, meta: Partial<typeof resource> = {}) {
  let api: ReturnType<typeof useResourceListing>

  const Host = defineComponent({
    props: {
      filters: { type: Object, default: () => ({}) },
      resource: { type: Object, required: true },
      table: { type: Object, required: true },
      stack: { type: Array, default: () => [] },
    },
    setup(props) {
      api = useResourceListing(props as any)
      return () => h('div')
    },
  })

  mount(Host, { props: { resource: { ...resource, ...meta }, table } as any, attrs })

  return api!
}

describe('useResourceListing', () => {
  it('reads the rows out of the payload key the resource named', () => {
    const listing = mountWith({ events: { data: [{ id: 1, title: 'Wedding' }] } })

    expect(listing.records.value.data).toHaveLength(1)
    expect(listing.records.value.data[0].title).toBe('Wedding')
  })

  it('falls back to an empty set when the payload key is absent', () => {
    expect(mountWith().records.value).toEqual({ data: [] })
  })

  it('derives the page title and singular label from the resource', () => {
    const listing = mountWith()

    expect(listing.title.value).toBe('Events')
    expect(listing.singularLabel.value).toBe('Event')
  })


  /** A column marked hiddenByDefault starts off, without touching the query. */
  it('seeds visible columns from the schema', () => {
    const listing = mountWith()

    expect(listing.visibleColumns.value).toContain('title')
    expect(listing.visibleColumns.value).not.toContain('location')
  })

  it('exposes the filter, sort and pagination surface a listing binds to', () => {
    const listing = mountWith()

    for (const key of [
      'form', 'computedParams', 'computedSortColumn', 'computedSortDirection',
      'updateSearch', 'handleSort', 'goToPage', 'updatePerPage', 'setFilter',
    ]) {
      expect(listing, `missing ${key}`).toHaveProperty(key)
    }
  })

  it('starts with no drawer tab pinned, so the first declared one wins', () => {
    expect(mountWith().drawerTab.value).toBeNull()
  })

  /**
   * The server sends the base URL its routes were generated under, prefix
   * included. Building the endpoint from the key through panelUrl() instead
   * sent a `->prefix('/admin')` resource's filters to a page under /panel.
   */
  it('sends filter and page changes to the resource\'s own base URL, not the panel mount', () => {
    setPanelBaseUrl('/panel')
    get.mockClear()

    try {
      mountWith({}, { baseUrl: '/admin/events' }).goToPage(2)
    } finally {
      setPanelBaseUrl('')
    }

    expect(get).toHaveBeenCalledTimes(1)
    expect(get.mock.calls[0][0]).toBe('/admin/events')
    expect(get.mock.calls[0][1]).toMatchObject({ page: { number: 2 } })
  })
})

describe('humanize', () => {
  it.each([
    ['events', 'Events'],
    ['purchase_orders', 'Purchase Orders'],
    ['finished-products', 'Finished Products'],
  ])('%s → %s', (input, expected) => {
    expect(humanize(input)).toBe(expected)
  })
})
