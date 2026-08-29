import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, config, type VueWrapper } from '@vue/test-utils'
import type { EditorView } from 'prosemirror-view'
import ProseMirrorBuilderField from '../src/Components/Fields/ProseMirrorBuilderField.vue'

// MediaPickerDialog fetches on mount; the field's own behaviour is what's under
// test, so stub it. Teleport is disabled so the toolbars render inline.
const stubs = {
  Teleport: true,
  MediaPickerDialog: { template: '<div />', props: ['isOpen'] },
}

const doc = (content: unknown[]) => JSON.stringify({ type: 'doc', content })

const paragraph = (text: string, marks?: unknown[]) => ({
  type: 'paragraph',
  content: [{ type: 'text', text, ...(marks ? { marks } : {}) }],
})

function editorHtml(wrapper: VueWrapper): string {
  return wrapper.find('.pm-editor').element.innerHTML
}

/** The live EditorView, via the component's exposed API rather than internals. */
function editorOf(wrapper: VueWrapper): EditorView | null {
  return (wrapper.vm as unknown as { editor: () => EditorView | null }).editor()
}

describe('ProseMirrorBuilderField', () => {
  beforeEach(() => {
    config.global.stubs = { ...config.global.stubs, ...stubs }
  })
  afterEach(() => {
    config.global.stubs = {}
    vi.restoreAllMocks()
  })

  // ── Mounting ──────────────────────────────────────────────────────────────

  it('mounts an editor for an empty value', () => {
    const wrapper = mount(ProseMirrorBuilderField, { props: { modelValue: '' } })
    expect(wrapper.find('.ProseMirror').exists()).toBe(true)
  })

  it('renders the stored document', () => {
    const wrapper = mount(ProseMirrorBuilderField, {
      props: { modelValue: doc([paragraph('Hello world')]) },
    })
    expect(editorHtml(wrapper)).toContain('Hello world')
  })

  it('shows the label, help and error', () => {
    const wrapper = mount(ProseMirrorBuilderField, {
      props: { modelValue: '', label: 'Body', error: 'Required' },
    })
    expect(wrapper.text()).toContain('Body')
    expect(wrapper.text()).toContain('Required')
  })

  it('offers the block insert toolbar', () => {
    const wrapper = mount(ProseMirrorBuilderField, { props: { modelValue: '' } })
    const labels = wrapper.findAll('button').map((b) => b.text())
    for (const expected of ['Text', 'Heading 2', 'Quote', 'Bullet list', 'Code', 'Image']) {
      expect(labels).toContain(expected)
    }
  })

  // ── The security property, end to end through the component ───────────────

  describe('untrusted content', () => {
    it('renders a script payload as text, not markup', () => {
      const wrapper = mount(ProseMirrorBuilderField, {
        props: { modelValue: doc([paragraph('<script>alert(1)</script>')]) },
      })
      const html = editorHtml(wrapper)
      expect(html).not.toContain('<script')
      expect(html).toContain('&lt;script&gt;')
    })

    it('does not render an anchor for a dangerous href', () => {
      const wrapper = mount(ProseMirrorBuilderField, {
        props: {
          modelValue: doc([
            paragraph('click', [{ type: 'link', attrs: { href: 'javascript:alert(1)' } }]),
          ]),
        },
      })
      const html = editorHtml(wrapper)
      expect(html).not.toContain('javascript:')
      expect(html).not.toContain('<a ')
      expect(html).toContain('click')
    })

    it('renders an ordinary link normally', () => {
      const wrapper = mount(ProseMirrorBuilderField, {
        props: {
          modelValue: doc([
            paragraph('here', [{ type: 'link', attrs: { href: 'https://example.com' } }]),
          ]),
        },
      })
      expect(editorHtml(wrapper)).toContain('href="https://example.com"')
    })

    it('opens rather than crashes on a corrupt document', () => {
      const wrapper = mount(ProseMirrorBuilderField, {
        props: { modelValue: '{"type":"doc","content":[{"type":"evil"}]}' },
      })
      expect(wrapper.find('.ProseMirror').exists()).toBe(true)
    })
  })

  // ── Values the strict reader refuses ──────────────────────────────────────

  describe('unreadable content', () => {
    const UNREADABLE = {
      'legacy block JSON': JSON.stringify({ blocks: [{ type: 'paragraph', data: { text: 'old' } }] }),
      'markdown': '# Heading\n\nSome **bold** text',
      'plain prose': 'Just a sentence.',
      'corrupt document': '{"type":"doc","content":[{"type":"evil"}]}',
    }

    it.each(Object.entries(UNREADABLE))('reports %s and disables editing', (_label, value) => {
      const wrapper = mount(ProseMirrorBuilderField, { props: { modelValue: value } })

      expect(wrapper.text()).toContain('could not be read')
      expect(wrapper.text()).toContain('migrate:content')
      expect(editorOf(wrapper)!.editable).toBe(false)
    })

    it.each(Object.entries(UNREADABLE))('never emits over %s', async (_label, value) => {
      const wrapper = mount(ProseMirrorBuilderField, { props: { modelValue: value } })
      const view = editorOf(wrapper)!

      // Even a transaction dispatched directly must not produce a value: the
      // stored content is not something we can round-trip, so overwriting it
      // with the empty document standing in for it would destroy it.
      view.dispatch(view.state.tr.insertText('typed'))
      await wrapper.vm.$nextTick()

      expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    })

    it('hides the insert toolbar', () => {
      const wrapper = mount(ProseMirrorBuilderField, { props: { modelValue: 'plain prose' } })
      expect(wrapper.findAll('button').map((b) => b.text())).not.toContain('Heading 2')
    })

    it('says nothing for a readable document', () => {
      const wrapper = mount(ProseMirrorBuilderField, {
        props: { modelValue: doc([paragraph('fine')]) },
      })
      expect(wrapper.text()).not.toContain('could not be read')
      expect(editorOf(wrapper)!.editable).toBe(true)
    })
  })

  // ── Value flow ────────────────────────────────────────────────────────────

  describe('value flow', () => {
    it('emits a ProseMirror document, never HTML', async () => {
      const wrapper = mount(ProseMirrorBuilderField, { props: { modelValue: '' } })
      const view = editorOf(wrapper)!

      view.dispatch(view.state.tr.insertText('typed'))
      await wrapper.vm.$nextTick()

      const emitted = wrapper.emitted('update:modelValue')
      expect(emitted).toBeTruthy()

      const value = emitted!.at(-1)![0] as string
      const parsed = JSON.parse(value)
      expect(parsed.type).toBe('doc')
      expect(value).not.toContain('<')
    })

    it('adopts an external change to modelValue', async () => {
      const wrapper = mount(ProseMirrorBuilderField, {
        props: { modelValue: doc([paragraph('first')]) },
      })
      expect(editorHtml(wrapper)).toContain('first')

      await wrapper.setProps({ modelValue: doc([paragraph('second')]) })

      expect(editorHtml(wrapper)).toContain('second')
      expect(editorHtml(wrapper)).not.toContain('first')
    })

    it('does not rebuild the editor when modelValue echoes its own emit', async () => {
      const wrapper = mount(ProseMirrorBuilderField, { props: { modelValue: '' } })
      const before = editorOf(wrapper)!

      before.dispatch(before.state.tr.insertText('typed'))
      await wrapper.vm.$nextTick()

      const echoed = (wrapper.emitted('update:modelValue')!.at(-1)![0]) as string
      await wrapper.setProps({ modelValue: echoed })

      // Same view instance, and the text survived — a rebuild would reset it.
      expect(editorOf(wrapper)).toBe(before)
      expect(editorHtml(wrapper)).toContain('typed')
    })
  })

  it('destroys the editor on unmount', () => {
    const wrapper = mount(ProseMirrorBuilderField, { props: { modelValue: '' } })

    expect(editorOf(wrapper)).not.toBeNull()
    wrapper.unmount()
    expect(editorOf(wrapper)).toBeNull()
  })
})
