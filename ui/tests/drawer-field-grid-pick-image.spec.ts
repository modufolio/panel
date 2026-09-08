import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import DrawerFieldGrid from '../src/Components/Drawer/DrawerFieldGrid.vue'

/**
 * An empty `pickable` field (a cover with no value yet) becomes a clickable
 * affordance that emits `pick-image`, rather than the plain, inert
 * placeholder every other empty image field renders as.
 */
describe('DrawerFieldGrid image picker', () => {
  it('renders a clickable picker for an empty field with pickUrl, in a spanning slot', () => {
    const wrapper = mount(DrawerFieldGrid, {
      props: {
        data: { cover: null },
        include: { cover: { rows: 3, pickUrl: '/panel/movies/x/relations/cover_media_id', pickTarget: 'cover_media_id' } },
      },
    })

    const button = wrapper.find('button[aria-label="Choose Cover"]')
    expect(button.exists()).toBe(true)

    button.trigger('click')
    expect(wrapper.emitted('pick-image')).toBeTruthy()
    expect(wrapper.emitted('pick-image')?.[0]?.[0]).toMatchObject({
      key: 'cover',
      pickUrl: '/panel/movies/x/relations/cover_media_id',
      pickTarget: 'cover_media_id',
    })
  })

  it('renders a clickable picker for an empty field with pickUrl, in the single-row slot', () => {
    const wrapper = mount(DrawerFieldGrid, {
      props: {
        data: { cover: null },
        include: { cover: { pickUrl: '/panel/movies/x/relations/cover_media_id', pickTarget: 'cover_media_id' } },
      },
    })

    expect(wrapper.find('button[aria-label="Choose Cover"]').exists()).toBe(true)
  })

  it('leaves an empty field with no pickUrl as a plain, non-interactive placeholder', () => {
    const wrapper = mount(DrawerFieldGrid, {
      props: {
        data: { cover: null },
        include: { cover: { rows: 3 } },
      },
    })

    expect(wrapper.find('button').exists()).toBe(false)
  })

  it('uses the resource-declared wording when given, "Choose image" otherwise', () => {
    const withLabel = mount(DrawerFieldGrid, {
      props: {
        data: { cover: null },
        include: { cover: { rows: 3, pickUrl: '/x', pickTarget: 'cover_media_id', pickLabel: 'Upload cover' } },
      },
    })
    expect(withLabel.text()).toContain('Upload cover')
    expect(withLabel.text()).not.toContain('Choose image')

    const withoutLabel = mount(DrawerFieldGrid, {
      props: {
        data: { cover: null },
        include: { cover: { rows: 3, pickUrl: '/x', pickTarget: 'cover_media_id' } },
      },
    })
    expect(withoutLabel.text()).toContain('Choose image')
  })

  it('still shows a plain, inert image when the field is not pickable', () => {
    const wrapper = mount(DrawerFieldGrid, {
      props: {
        data: { cover: { url: '/img/x.jpg' } },
        include: { cover: { rows: 3 } },
      },
    })

    expect(wrapper.find('img').exists()).toBe(true)
    expect(wrapper.find('button').exists()).toBe(false)
  })

  /**
   * An assigned image on a pickable field is still a picker — otherwise
   * there is no way to replace it short of clearing it first.
   */
  it('lets an already-assigned image be clicked to replace it', () => {
    const wrapper = mount(DrawerFieldGrid, {
      props: {
        data: { cover: { url: '/img/x.jpg' } },
        include: { cover: { rows: 3, pickUrl: '/panel/movies/x/relations/cover_media_id', pickTarget: 'cover_media_id' } },
      },
    })

    const button = wrapper.find('button[aria-label="Choose Cover"]')
    expect(button.exists()).toBe(true)
    expect(button.find('img').attributes('src')).toBe('/img/x.jpg')

    button.trigger('click')
    expect(wrapper.emitted('pick-image')?.[0]?.[0]).toMatchObject({ key: 'cover', pickTarget: 'cover_media_id' })
  })
})
