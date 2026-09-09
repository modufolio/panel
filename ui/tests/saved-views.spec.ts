import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import {
  activeSavedView,
  loadSavedViews,
  removeSavedView,
  saveSavedViews,
  savedViewFilters,
  savedViewMatches,
  upsertSavedView,
  type SavedView,
} from '../src/Composables/savedViews'
import SavedViews from '../src/Components/Resource/SavedViews.vue'

const overdue: SavedView = {
  name: 'Overdue',
  filters: { status: 'open', sort: '-due_date' },
  columns: ['title', 'due_date'],
}

describe('saved views storage', () => {
  beforeEach(() => localStorage.clear())

  it('round-trips through the browser, per resource', () => {
    saveSavedViews('issues', [overdue])

    expect(loadSavedViews('issues')).toEqual([overdue])
    expect(loadSavedViews('movies')).toEqual([])
  })

  it('reads nothing rather than throwing when the stored value is junk', () => {
    localStorage.setItem('panel.views.issues', '{ not json')

    expect(loadSavedViews('issues')).toEqual([])
  })

  it('drops entries that no longer look like a view', () => {
    localStorage.setItem('panel.views.issues', JSON.stringify([overdue, { name: 'Broken' }, 7]))

    expect(loadSavedViews('issues')).toEqual([overdue])
  })

  it('saving over a name replaces that view, which is how one is edited', () => {
    const edited = { ...overdue, filters: { status: 'closed' } }

    expect(upsertSavedView([overdue], edited)).toEqual([edited])
    expect(upsertSavedView([overdue], { name: 'Mine', filters: {} })).toHaveLength(2)
    expect(removeSavedView([overdue], 'Overdue')).toEqual([])
  })

  /** Same reconciliation column preferences do: a dropped filter is forgotten. */
  it('keeps only the filters the resource still declares', () => {
    expect(savedViewFilters(overdue, ['status', 'search', 'sort'])).toEqual({
      status: 'open',
      sort: '-due_date',
    })
    expect(savedViewFilters(overdue, ['search'])).toEqual({})
  })

  it('recognises the view the list is currently showing, whatever the key order', () => {
    expect(savedViewMatches(overdue, { sort: '-due_date', status: 'open' })).toBe(true)
    // Empty values are what an absent filter looks like, so they cannot count.
    expect(savedViewMatches(overdue, { status: 'open', sort: '-due_date', search: '' })).toBe(true)
    expect(savedViewMatches(overdue, { status: 'closed', sort: '-due_date' })).toBe(false)

    expect(activeSavedView([overdue], { status: 'open', sort: '-due_date' })).toBe('Overdue')
    expect(activeSavedView([overdue], {})).toBeNull()
  })
})

describe('the saved-view picker', () => {
  function open(props: Record<string, unknown> = {}) {
    const wrapper = mount(SavedViews, {
      props: { views: [overdue], ...props },
      global: { stubs: { teleport: true } },
    })

    return wrapper.find('button').trigger('click').then(() => wrapper)
  }

  it('names the button after the view being shown, so the list says what it is', async () => {
    const plain = mount(SavedViews, { props: { views: [overdue] }, global: { stubs: { teleport: true } } })
    expect(plain.find('button').text()).toContain('Views')

    const active = mount(SavedViews, {
      props: { views: [overdue], active: 'Overdue' },
      global: { stubs: { teleport: true } },
    })
    expect(active.find('button').text()).toContain('Overdue')
  })

  it('asks the page to apply the view that was picked', async () => {
    const wrapper = await open()

    await wrapper.findAll('button').find((button) => button.text() === 'Overdue')?.trigger('click')

    expect(wrapper.emitted('apply')?.[0]).toEqual([overdue])
  })

  it('saves the list under the typed name, and refuses an empty one', async () => {
    const wrapper = await open()

    await wrapper.find('form').trigger('submit')
    expect(wrapper.emitted('save')).toBeUndefined()

    await wrapper.find('input[type="text"]').setValue('  Unpublished  ')
    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('save')?.[0]).toEqual(['Unpublished'])
  })

  it('offers to delete a view by name', async () => {
    const wrapper = await open()

    await wrapper.find('[aria-label="Delete Overdue"]').trigger('click')

    expect(wrapper.emitted('delete')?.[0]).toEqual(['Overdue'])
  })

  it('says what to do when there is nothing saved yet', async () => {
    const wrapper = await open({ views: [] })

    expect(wrapper.text()).toContain('No saved views yet')
  })
})
