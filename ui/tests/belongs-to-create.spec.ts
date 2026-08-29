import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

const apiFetchMock = vi.fn()

vi.mock('../src/Utils/apiFetch', () => ({
  apiFetch: (...args: unknown[]) => apiFetchMock(...args),
  ApiError: class ApiError extends Error {
    body: unknown
    constructor(message: string, _status: number, body: unknown) {
      super(message)
      this.body = body
    }
  },
}))

import BelongsToSelect from '../src/Components/Fields/BelongsToSelect.vue'

/**
 * The picker's "Create …" row: POSTs the typed name to the relation
 * endpoint, folds the answer into the list and selects it. The server is
 * the authority on whether creation is allowed — the component only offers.
 */

function mountCreatable() {
  apiFetchMock.mockReset()
  // The focus-triggered first-page search.
  apiFetchMock.mockResolvedValue({ data: {} })

  return mount(BelongsToSelect, {
    props: {
      modelValue: null,
      label: 'Ingredient',
      searchUrl: '/panel/recipes/relations/recipe_ingredients.ingredient_id',
      valueKey: 'value',
      labelKey: 'label',
      allowCreate: true,
    },
    attachTo: document.body,
  })
}

/** The remote list uses raw fetch for GETs; stub it quiet. */
beforeEach(() => {
  apiFetchMock.mockReset()
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
    ok: true,
    json: () => Promise.resolve({ data: [{ value: 'u-butter', label: 'Butter' }], meta: {} }),
    headers: new Headers({ 'content-type': 'application/json' }),
  }))
})

describe('BelongsToSelect create-from-query', () => {
  it('creates, selects and displays the new record', async () => {
    const wrapper = mountCreatable()
    const input = wrapper.find('input')

    await input.trigger('focus')
    await input.setValue('Cinnamon')
    await new Promise((resolve) => setTimeout(resolve, 250))
    await flushPromises()

    apiFetchMock.mockResolvedValueOnce({ data: { value: 'u-cinnamon', label: 'Cinnamon' } })

    const createRow = wrapper.find('.ui-belongs-to-create')
    expect(createRow.exists()).toBe(true)
    await createRow.trigger('click')
    await flushPromises()

    expect(apiFetchMock).toHaveBeenCalledWith(
      '/panel/recipes/relations/recipe_ingredients.ingredient_id',
      { method: 'POST', body: { label: 'Cinnamon' } },
    )
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['u-cinnamon'])
    expect((input.element as HTMLInputElement).value).toBe('Cinnamon')
  })

  it('does not offer to create a name the list already carries', async () => {
    const wrapper = mountCreatable()
    const input = wrapper.find('input')

    await input.trigger('focus')
    await input.setValue('Butter')
    await new Promise((resolve) => setTimeout(resolve, 250))
    await flushPromises()

    expect(wrapper.find('.ui-belongs-to-create').exists()).toBe(false)
  })

  it('shows the server refusal instead of pretending', async () => {
    const wrapper = mountCreatable()
    const input = wrapper.find('input')

    await input.trigger('focus')
    await input.setValue('Cinnamon')
    await new Promise((resolve) => setTimeout(resolve, 250))
    await flushPromises()

    const { ApiError } = await import('../src/Utils/apiFetch')
    apiFetchMock.mockRejectedValueOnce(
      new (ApiError as any)('Unprocessable', 422, { error: 'A name is required.' }),
    )

    await wrapper.find('.ui-belongs-to-create').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('A name is required.')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('falls back to emitting for consumers without a searchUrl', async () => {
    const wrapper = mount(BelongsToSelect, {
      props: {
        modelValue: null,
        options: [{ value: 'u-butter', label: 'Butter' }],
        valueKey: 'value',
        labelKey: 'label',
        allowCreate: true,
      },
      attachTo: document.body,
    })
    const input = wrapper.find('input')

    await input.trigger('focus')
    await input.setValue('Cinnamon')

    await wrapper.find('.ui-belongs-to-create').trigger('click')

    expect(wrapper.emitted('create')?.at(-1)).toEqual(['Cinnamon'])
    expect(apiFetchMock).not.toHaveBeenCalled()
  })
})
