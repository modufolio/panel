import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, config, flushPromises, type VueWrapper } from '@vue/test-utils'
import LayoutField from '../src/Components/LayoutField/LayoutField.vue'
import type { LayoutRow } from '../src/Components/LayoutField/layoutModel'

// The text block mounts a ProseMirror editor and the image block a media
// picker that fetches on open; the field's own behaviour is what is under
// test. Teleport is disabled so the modals render inline.
const stubs = {
  Teleport: true,
  MediaPickerDialog: { template: '<div />', props: ['isOpen'] },
  ProseMirrorBuilderField: { template: '<div class="pm-stub" />', props: ['modelValue'] },
}

vi.mock('../src/Components/Dialogs/confirm', () => ({
  showConfirm: vi.fn(() => Promise.resolve(true)),
}))

const heading = (text: string, id = `h-${text}`) => ({ id, type: 'heading', content: { level: 'h2', text } })

const twoColumns = (): LayoutRow[] => [
  {
    id: 'row-1',
    columns: [
      { id: 'c1', width: '1/2', blocks: [heading('Left')] },
      { id: 'c2', width: '1/2', blocks: [] },
    ],
  },
]

function lastEmitted(wrapper: VueWrapper): LayoutRow[] {
  const events = wrapper.emitted('update:modelValue') ?? []
  return events[events.length - 1]![0] as LayoutRow[]
}

function mountField(props: Record<string, unknown> = {}) {
  return mount(LayoutField, {
    props: { label: 'Layout', ...props },
    global: { stubs },
  })
}

describe('LayoutField', () => {
  beforeEach(() => {
    config.global.stubs = { ...config.global.stubs, ...stubs }
  })
  afterEach(() => {
    config.global.stubs = {}
    vi.clearAllMocks()
  })

  it('renders stored rows as columns of the declared width, with their blocks', () => {
    const wrapper = mountField({ modelValue: twoColumns() })

    const columns = wrapper.findAll('.ui-layout-column')
    expect(columns).toHaveLength(2)
    expect(columns[0]!.attributes('style')).toContain('span 6')
    expect(columns[1]!.attributes('style')).toContain('span 6')

    const input = columns[0]!.find('input[aria-label="Heading text"]')
    expect((input.element as HTMLInputElement).value).toBe('Left')
  })

  it('shows the empty state and opens the layout selector from it', async () => {
    const wrapper = mountField({ modelValue: [] })

    expect(wrapper.text()).toContain('No rows yet')
    expect(wrapper.find('[role="listbox"]').exists()).toBe(false)

    await wrapper.find('button').trigger('click')
    const options = wrapper.findAll('[role="option"]')
    expect(options.length).toBeGreaterThan(1)
  })

  it('adds a row of the chosen preset and emits the value with ids', async () => {
    const wrapper = mountField({ modelValue: [], layouts: ['1/1', '1/3 1/3 1/3'] })

    await wrapper.find('button').trigger('click')
    await wrapper.find('[role="option"][aria-label="1/3 + 1/3 + 1/3"]').trigger('click')

    const rows = lastEmitted(wrapper)
    expect(rows).toHaveLength(1)
    expect(rows[0]!.columns.map((c) => c.width)).toEqual(['1/3', '1/3', '1/3'])
    expect(rows[0]!.id).toMatch(/\S/)
    expect(rows[0]!.columns.every((c) => c.id && c.blocks.length === 0)).toBe(true)
  })

  it('skips the selector when there is only one preset', async () => {
    const wrapper = mountField({ modelValue: [], layouts: ['1/1'] })

    await wrapper.find('button').trigger('click')

    expect(wrapper.find('[role="listbox"]').exists()).toBe(false)
    expect(lastEmitted(wrapper)[0]!.columns.map((c) => c.width)).toEqual(['1/1'])
  })

  it('inserts a block of the chosen type into the chosen column', async () => {
    const wrapper = mountField({ modelValue: twoColumns() })

    // The empty second column invites a click.
    const empty = wrapper.findAll('.ui-layout-column')[1]!
    await empty.find('button').trigger('click')
    await wrapper.find('[role="option"]:not([disabled])').trigger('click')

    const rows = lastEmitted(wrapper)
    expect(rows[0]!.columns[1]!.blocks).toHaveLength(1)
    expect(rows[0]!.columns[1]!.blocks[0]!.type).toBe('heading')
    expect(rows[0]!.columns[0]!.blocks.map((b) => b.id)).toEqual(['h-Left'])
  })

  it('offers only the configured block types', async () => {
    const wrapper = mountField({ modelValue: twoColumns(), blocks: ['quote', 'image'] })

    await wrapper.findAll('.ui-layout-column')[1]!.find('button').trigger('click')

    expect(wrapper.findAll('[role="option"]').map((o) => o.text())).toEqual(['Quote', 'Image'])
  })

  it('writes an edited block back into the value', async () => {
    const wrapper = mountField({ modelValue: twoColumns() })

    const input = wrapper.find('input[aria-label="Heading text"]')
    await input.setValue('Left, edited')

    expect(lastEmitted(wrapper)[0]!.columns[0]!.blocks[0]!.content).toEqual({ level: 'h2', text: 'Left, edited' })
  })

  it('changes a row’s layout without losing its blocks', async () => {
    const wrapper = mountField({ modelValue: twoColumns(), layouts: ['1/1', '1/2 1/2'] })

    await wrapper.find('button[title="Change layout"]').trigger('click')
    const current = wrapper.find('[role="option"][aria-selected="true"]')
    expect(current.attributes('aria-label')).toBe('1/2 + 1/2')

    await wrapper.find('[role="option"][aria-label="1/1"]').trigger('click')

    const rows = lastEmitted(wrapper)
    expect(rows).toHaveLength(1)
    expect(rows[0]!.columns.map((c) => c.width)).toEqual(['1/1'])
    expect(rows[0]!.columns[0]!.blocks.map((b) => b.id)).toEqual(['h-Left'])
  })

  it('removes a block after the confirmation', async () => {
    const wrapper = mountField({ modelValue: twoColumns() })

    await wrapper.find('button[title="Delete block"]').trigger('click')
    await flushPromises()

    expect(lastEmitted(wrapper)[0]!.columns[0]!.blocks).toHaveLength(0)
  })

  it('reorders rows with the move buttons', async () => {
    const wrapper = mountField({
      modelValue: [
        { id: 'a', columns: [{ id: 'a1', width: '1/1', blocks: [] }] },
        { id: 'b', columns: [{ id: 'b1', width: '1/1', blocks: [] }] },
      ],
    })

    await wrapper.findAll('button[title="Move row down"]')[0]!.trigger('click')

    expect(lastEmitted(wrapper).map((r) => r.id)).toEqual(['b', 'a'])
  })

  it('takes a new value from outside but ignores its own echo', async () => {
    const wrapper = mountField({ modelValue: twoColumns() })

    await wrapper.find('input[aria-label="Heading text"]').setValue('Edited')
    const echoed = lastEmitted(wrapper)
    await wrapper.setProps({ modelValue: echoed })
    expect(wrapper.emitted('update:modelValue')).toHaveLength(1)

    await wrapper.setProps({ modelValue: [] })
    expect(wrapper.findAll('.ui-layout-row')).toHaveLength(0)
    expect(wrapper.text()).toContain('No rows yet')
  })
})
