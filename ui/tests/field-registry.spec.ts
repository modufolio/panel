import { describe, it, expect } from 'vitest'
import manifest from '../src/Components/Fields/fieldTypes.json'
import {
  builtInFieldTypes,
  registeredFieldTypes,
  missingFieldTypes,
  unknownFieldTypeMessage,
  registerFieldType,
  resolveFieldComponent,
} from '../src/Components/Fields/fieldRegistry'
import { mountBlueprintForm } from './support/mountBlueprintForm'

/**
 * The field-component contract across the boundary. `fieldTypes.json` is the
 * one list both registries answer to: the PHP side pins FieldComponents::BUILT_IN
 * to it, and this pins the Vue registry — so a type shipped on one side and not
 * the other fails here, not on a page.
 */
describe('field registry contract', () => {
  it('ships exactly the types the manifest lists', () => {
    expect([...registeredFieldTypes()].sort()).toEqual([...(manifest as string[])].sort())
    expect(builtInFieldTypes()).toEqual(manifest)
  })

  it('every manifest type resolves to a component', async () => {
    for (const type of manifest as string[]) {
      await expect(resolveFieldComponent(type), type).resolves.toBeTruthy()
    }
  })

  it('finds the unregistered types a declaration needs, sub-fields included, once each', () => {
    const fields = [
      { type: 'text', key: 'title' },
      { type: 'markdown', key: 'body' },
      { type: 'set', key: 'venue', fields: [{ type: 'text', key: 'name' }, { type: 'geo-point', key: 'map' }] },
      { type: 'markdown', key: 'notes' },
    ]

    expect(missingFieldTypes(fields)).toEqual(['markdown', 'geo-point'])

    registerFieldType('geo-point', () => import('../src/Components/Fields/TextField.vue'))
    expect(missingFieldTypes(fields)).toEqual(['markdown'])
  })

  it('tells the reader how to register what is missing', () => {
    const message = unknownFieldTypeMessage(['markdown'])

    expect(message).toContain('Unknown field type "markdown"')
    expect(message).toContain("'markdown': () => import('./Fields/MarkdownField.vue')")
    expect(message).toContain('createPanel({')
  })
})

describe('BlueprintForm with an unregistered type', () => {
  it('says so where the form is, and still renders the fields it can', async () => {
    const wrapper = await mountBlueprintForm({
      fields: [
        { type: 'text', key: 'title', label: 'Title' },
        { type: 'markdown', key: 'body', label: 'Body' },
      ],
      modelValue: {},
    }, 2)

    const alert = wrapper.find('[role="alert"].ui-field-unknown-types')
    expect(alert.exists()).toBe(true)
    expect(alert.text()).toContain('Unknown field type "markdown"')
    expect(alert.text()).toContain("'markdown': () => import('./Fields/MarkdownField.vue')")
    expect(wrapper.findAll('label')).toHaveLength(1)
  })
})
