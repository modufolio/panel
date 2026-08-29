import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { ExportButton } from '../src/index'

const columns = [{ key: 'name', label: 'Name' }]
const rows = [
  { id: 1, name: 'Ada' },
  { id: 2, name: 'Grace' },
  { id: 3, name: 'Katherine' },
]

const mountButton = (props: Record<string, unknown> = {}) =>
  mount(ExportButton, {
    props: {
      data: rows,
      columns,
      filename: 'people',
      availableFormats: ['csv', 'json'],
      selectedRecords: [],
      ...props,
    },
  })

describe('ExportButton', () => {
  it('is enabled when there is data but nothing selected', () => {
    // Regression: the button was disabled whenever the selection was empty,
    // so the whole table could never be exported.
    const button = mountButton().find('button')

    expect(button.attributes('disabled')).toBeUndefined()
  })

  it('is disabled only when there is nothing at all to export', () => {
    const button = mountButton({ data: [], selectedRecords: [] }).find('button')

    expect(button.attributes('disabled')).toBeDefined()
  })

  it('exports every loaded row when the selection is empty', async () => {
    const wrapper = mountButton()

    await wrapper.find('button').trigger('click')
    await wrapper.findAll('[role="menuitem"], button').find(b => /csv/i.test(b.text()))?.trigger('click')

    const events = wrapper.emitted('export') as Array<[{ recordCount: number }]> | undefined
    expect(events?.[0]?.[0].recordCount).toBe(rows.length)
  })

  it('exports only the selection when one is present', async () => {
    const wrapper = mountButton({ selectedRecords: [{ id: 2, name: 'Grace' }] })

    await wrapper.find('button').trigger('click')
    await wrapper.findAll('[role="menuitem"], button').find(b => /csv/i.test(b.text()))?.trigger('click')

    const events = wrapper.emitted('export') as Array<[{ recordCount: number }]> | undefined
    expect(events?.[0]?.[0].recordCount).toBe(1)
  })
})
