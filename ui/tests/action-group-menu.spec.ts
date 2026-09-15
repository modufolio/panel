import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { ActionGroup, ActionGroupItem, ActionGroupSeparator, SchemaTable, type TableSchema } from '../src/index'

/**
 * The colour roles live in styles/components.css keyed on `data-color`; what
 * the components guarantee is the hook and the structure around it.
 */
describe('ActionGroupItem', () => {
  it('exposes its role as data-color for the stylesheet', () => {
    const neutral = mount(ActionGroupItem, { props: { label: 'Edit' } })
    const danger = mount(ActionGroupItem, { props: { label: 'Delete', color: 'danger' } })

    expect(neutral.attributes('data-color')).toBe('gray')
    expect(danger.attributes('data-color')).toBe('danger')
    expect(danger.classes()).toContain('ui-action-group-item')
  })
})

describe('ActionGroupSeparator', () => {
  it('is a horizontal separator to assistive tech', () => {
    const wrapper = mount(ActionGroupSeparator)

    expect(wrapper.attributes('role')).toBe('separator')
    expect(wrapper.attributes('aria-orientation')).toBe('horizontal')
    expect(wrapper.classes()).toContain('ui-action-group-separator')
  })

  it('renders between items inside a menu', () => {
    const wrapper = mount(ActionGroup, {
      slots: {
        default: [
          '<button class="ui-action-group-item">Edit</button>',
          '<hr class="ui-action-group-separator" role="separator" />',
          '<button class="ui-action-group-item">Delete</button>',
        ].join(''),
      },
      attachTo: document.body,
    })

    const order = Array.from(document.body.querySelectorAll('[role="menu"] .ui-action-group-item, [role="menu"] .ui-action-group-separator'))
      .map((el) => el.className)

    expect(order).toEqual(['ui-action-group-item', 'ui-action-group-separator', 'ui-action-group-item'])
    wrapper.unmount()
  })
})

describe('SchemaTable generated row menu', () => {
  const schema = (actions: TableSchema['actions']): TableSchema =>
    ({
      columns: [{ key: 'name', label: 'Name', type: 'text' }],
      actions,
    }) as unknown as TableSchema

  const records = [{ id: 1, name: 'Ada' }]

  const mountMenu = (actions: TableSchema['actions']) =>
    mount(SchemaTable, { props: { schema: schema(actions), records, filterValues: {} } as any, attachTo: document.body })

  it('draws a separator before the first destructive action', () => {
    const wrapper = mountMenu([
      { name: 'edit', behaviour: 'visit', label: 'Edit', urlTemplate: '/x/{id}' },
      { name: 'delete', behaviour: 'delete', label: 'Delete', color: 'danger', urlTemplate: '/x/{id}' },
      { name: 'purge', behaviour: 'delete', label: 'Purge', color: 'danger', urlTemplate: '/x/{id}' },
    ] as TableSchema['actions'])

    const order = Array.from(document.body.querySelectorAll('[role="menu"] .ui-action-group-item, [role="menu"] .ui-action-group-separator'))
      .map((el) => (el.classList.contains('ui-action-group-separator') ? '---' : el.textContent?.trim()))

    expect(order).toEqual(['Edit', '---', 'Delete', 'Purge'])
    wrapper.unmount()
  })

  it('draws no separator when the destructive action comes first', () => {
    const wrapper = mountMenu([
      { name: 'delete', behaviour: 'delete', label: 'Delete', color: 'danger', urlTemplate: '/x/{id}' },
      { name: 'edit', behaviour: 'visit', label: 'Edit', urlTemplate: '/x/{id}' },
    ] as TableSchema['actions'])

    expect(document.body.querySelector('[role="menu"] .ui-action-group-separator')).toBeNull()
    wrapper.unmount()
  })
})
