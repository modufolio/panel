<template>
  <FieldPrimitive
    v-bind="{ width, label, help, error, required }"
    wrapper-class="ui-field-builder space-y-1.5 border-0 p-0 m-0"
    as="fieldset"
    label-spacing="none"
  >
    <div
      class="ui-input overflow-hidden p-0"
      :class="{ 'border-danger-600': error || readError }"
    >
      <!-- The editor mounts here; ProseMirror owns everything inside. -->
      <div ref="mount" class="pm-editor relative py-3 pl-9 pr-4 text-sm leading-relaxed text-gray-900" />

      <!-- Block insert toolbar -->
      <div v-if="!readError" class="flex flex-wrap items-center gap-1 border-t border-gray-100 bg-gray-50 px-3 py-2">
        <span class="mr-1 text-xs text-gray-400">Add:</span>
        <button
          v-for="option in BLOCK_OPTIONS"
          :key="option.label"
          type="button"
          class="flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs text-gray-600 transition-colors hover:bg-gray-200"
          @click="runOption(option)"
        >
          <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" :d="option.iconPath" />
          </svg>
          {{ option.label }}
        </button>
      </div>
    </div>

    <!-- Selection toolbar -->
    <Teleport to="body">
      <div
        v-if="toolbar.visible"
        class="fixed z-50 flex items-center gap-0.5 rounded-lg bg-gray-900 px-1.5 py-1 shadow-xl"
        :style="toolbar.style"
        @mousedown.prevent
      >
        <template v-if="linkInput.open">
          <input
            ref="linkInputEl"
            v-model="linkInput.value"
            type="url"
            placeholder="https://…"
            class="w-40 border-0 border-b border-white/40 bg-transparent px-1 py-0.5 text-xs text-white outline-none placeholder:text-white/40"
            @keydown.enter.prevent="applyLink"
            @keydown.esc.prevent="cancelLink"
          />
          <button type="button" title="Apply link" class="rounded px-1.5 py-0.5 text-white hover:bg-white/20" @mousedown.prevent="applyLink">✓</button>
          <button type="button" title="Cancel" class="rounded px-1.5 py-0.5 text-white hover:bg-white/20" @mousedown.prevent="cancelLink">✕</button>
          <p v-if="linkInput.error" class="ml-1 text-xs text-red-300">{{ linkInput.error }}</p>
        </template>
        <template v-else>
          <button type="button" title="Bold (⌘B)" class="rounded px-2 py-0.5 text-sm font-bold text-white hover:bg-white/20" :class="{ 'bg-white/20': active.strong }" @mousedown.prevent="run(commands.toggleStrong)">B</button>
          <button type="button" title="Italic (⌘I)" class="rounded px-2 py-0.5 text-sm italic text-white hover:bg-white/20" :class="{ 'bg-white/20': active.em }" @mousedown.prevent="run(commands.toggleEm)">I</button>
          <button type="button" title="Code (⌘E)" class="rounded px-2 py-0.5 font-mono text-xs text-white hover:bg-white/20" :class="{ 'bg-white/20': active.code }" @mousedown.prevent="run(commands.toggleCode)">&lt;&gt;</button>
          <div class="mx-0.5 h-4 w-px bg-white/20" />
          <button type="button" title="Link (⌘K)" class="rounded px-1.5 py-0.5 text-xs text-white hover:bg-white/20" :class="{ 'bg-white/20': active.link }" @mousedown.prevent="openLinkInput">Link</button>
          <button v-if="active.link" type="button" title="Remove link" class="rounded px-1.5 py-0.5 text-xs text-white hover:bg-white/20" @mousedown.prevent="removeLink">Unlink</button>
        </template>
      </div>
    </Teleport>

    <!-- Slash menu -->
    <Teleport to="body">
      <div
        v-if="slash.visible"
        class="fixed z-50 min-w-60 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl"
        :style="slash.style"
        @mousedown.prevent
      >
        <div class="border-b border-gray-100 px-3 pb-1.5 pt-2.5">
          <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Block type</span>
        </div>
        <div class="py-1">
          <button
            v-for="(option, i) in slashOptions"
            :key="option.label"
            type="button"
            class="flex w-full items-center gap-3 px-3 py-2 text-left transition-colors"
            :class="i === slash.focus ? 'bg-primary-50 text-primary-700' : 'text-gray-700 hover:bg-gray-50'"
            @mouseenter="slash.focus = i"
            @mousedown.prevent="chooseSlashOption(option)"
          >
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" :class="i === slash.focus ? 'bg-primary-100' : 'bg-gray-100'">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="option.iconPath" />
              </svg>
            </div>
            <div class="min-w-0">
              <div class="text-sm font-medium">{{ option.label }}</div>
              <div class="text-xs text-gray-400">{{ option.description }}</div>
            </div>
          </button>
          <p v-if="slashOptions.length === 0" class="px-3 py-2 text-sm text-gray-400">No matching block</p>
        </div>
      </div>
    </Teleport>

    <!-- Not a validation message: the document could not be read at all, so
         it stands apart from the frame's `error`. -->
    <FieldMessage v-if="readError">{{ readError }}</FieldMessage>

    <MediaPickerDialog :is-open="picker.open" @close="cancelPick" @select="resolvePick" />
  </FieldPrimitive>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onBeforeUnmount, nextTick, watch, shallowRef } from 'vue'
import { EditorState, type Command, type Transaction } from 'prosemirror-state'
import { EditorView } from 'prosemirror-view'
import { gapCursor } from 'prosemirror-gapcursor'
import { dropCursor } from 'prosemirror-dropcursor'
import { router } from '@inertiajs/vue3'
import FieldMessage from '../../Components/Fields/FieldMessage.vue'
import FieldPrimitive from '../../Components/Fields/FieldPrimitive.vue'
import { fieldWidthProp } from '../../Components/Fields/useFieldWidth'
import { normalizeUrl } from '../../Utils/url'
import { sanitizeUrl } from '../../Utils/url'
import { panelUrl } from '../../Utils/url'
import MediaPickerDialog from '../Media/MediaPickerDialog.vue'

import { schema } from '../../Builder/schema'
import { parseStoredValue, serializeDoc } from '../../Builder/document'
import {
  commands, builderInputRules, builderKeymap, builderHistory,
  isMarkActive, activeLinkHref,
} from '../../Builder/editing'
import { placeholder, slashMenu, getSlashMenuState, closeSlashMenu } from '../../Builder/plugins'
import { ImageNodeView } from '../../Builder/ImageNodeView'
import { blockDragHandle } from '../../Builder/dragHandle'

type PickedImage = {
  id: string
  url: string
  thumbnail_url: string
  alt_text?: string
  caption?: string
}

const props = defineProps({
  ...fieldWidthProp,
  modelValue: { type: String, default: '' },
  label:      { type: String, default: '' },
  help:       { type: String, default: '' },
  error:      { type: String, default: '' },
  required:   { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])


// ── Block catalogue ──────────────────────────────────────────────────────────

interface BlockOption {
  label: string
  description: string
  iconPath: string
  keywords: string[]
  command: Command
}

const BLOCK_OPTIONS: BlockOption[] = [
  { label: 'Text', description: 'Plain paragraph', keywords: ['text', 'paragraph', 'p'],
    iconPath: 'M4 6h16M4 10h16M4 14h16M4 18h7', command: commands.setParagraph },
  { label: 'Heading 2', description: 'Section title', keywords: ['heading', 'h2', 'title'],
    iconPath: 'M4 6h16M4 12h7', command: commands.setHeading(2) },
  { label: 'Heading 3', description: 'Subsection title', keywords: ['heading', 'h3', 'subtitle'],
    iconPath: 'M4 6h12M4 12h6', command: commands.setHeading(3) },
  { label: 'Quote', description: 'Block quotation', keywords: ['quote', 'blockquote', 'cite'],
    iconPath: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
    command: commands.wrapBlockquote },
  { label: 'Bullet list', description: 'Unordered list', keywords: ['list', 'bullet', 'ul'],
    iconPath: 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01', command: commands.toggleBulletList },
  { label: 'Numbered list', description: 'Ordered list', keywords: ['list', 'number', 'ol', 'ordered'],
    iconPath: 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01', command: commands.toggleOrderedList },
  { label: 'Code', description: 'Code snippet', keywords: ['code', 'pre', 'snippet'],
    iconPath: 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4', command: commands.setCodeBlock },
  { label: 'Image', description: 'Photo from media library', keywords: ['image', 'photo', 'picture', 'img'],
    iconPath: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
    command: insertImage },
]

// ── State ────────────────────────────────────────────────────────────────────

const mount = ref<HTMLElement | null>(null)
const linkInputEl = ref<HTMLInputElement | null>(null)
const view = shallowRef<EditorView | null>(null)

// Built during setup so it renders immediately; buildState() refreshes it when
// the value is replaced from outside.
const initialState = shallowRef<EditorState | null>(null)

// Set when the stored value is not a document we can read. The editor goes
// read-only and stops emitting, so an unreadable value is never overwritten by
// the empty document standing in for it.
const readError = ref('')

const active = reactive({ strong: false, em: false, code: false, link: false })
const toolbar = reactive({ visible: false, style: {} as Record<string, string> })
const slash = reactive({ visible: false, focus: 0, style: {} as Record<string, string>, query: '' })
const linkInput = reactive({ open: false, value: '', error: '' })

const picker = reactive({ open: false })
let pickerResolve: ((image: PickedImage | null) => void) | null = null

const slashOptions = computed(() => {
  const query = slash.query.toLowerCase()
  if (query === '') return BLOCK_OPTIONS
  return BLOCK_OPTIONS.filter((o) => o.keywords.some((k) => k.startsWith(query)))
})

// Serialized form of what the editor currently holds. Used to tell an echo of
// our own emit apart from a genuine external change.
let lastEmitted = ''

// ── Media picker, promise-shaped for the NodeView ────────────────────────────

function pickImage(): Promise<PickedImage | null> {
  picker.open = true
  return new Promise((resolve) => {
    pickerResolve = resolve
  })
}

function resolvePick(image: PickedImage) {
  picker.open = false
  pickerResolve?.(image)
  pickerResolve = null
}

function cancelPick() {
  picker.open = false
  pickerResolve?.(null)
  pickerResolve = null
}

function insertImage(_state: EditorState, dispatch?: (tr: Transaction) => void): boolean {
  if (!dispatch) return true

  void pickImage().then((chosen) => {
    const current = view.value
    if (!current) return

    const node = schema.nodes.image.create(chosen
      ? {
          id: chosen.id,
          url: chosen.url,
          thumbnail_url: chosen.thumbnail_url,
          alt: chosen.alt_text ?? '',
          caption: chosen.caption ?? '',
          width: 'default',
        }
      : undefined)

    current.dispatch(current.state.tr.replaceSelectionWith(node).scrollIntoView())
    current.focus()
  })

  return true
}

// ── Editor lifecycle ─────────────────────────────────────────────────────────

// Parsed during setup, not in onMounted: whether the value is readable is
// knowable before the editor exists, and should be on screen in the first
// render rather than a tick later.
function buildState(value: string): EditorState {
  const { doc, status, reason } = parseStoredValue(value)

  readError.value = status === 'unreadable'
    ? `This field could not be read (${reason}). Editing is disabled so the stored ` +
      'value is not overwritten. Run `npm run migrate:content` if it predates the builder.'
    : ''

  return EditorState.create({
    doc,
    plugins: [
      builderInputRules(),
      ...builderKeymap(openLinkInput),
      builderHistory(),
      dropCursor({ width: 2, class: 'pm-dropcursor' }),
      gapCursor(),
      placeholder('Type something, or press / to insert a block…'),
      slashMenu(),
      blockDragHandle(),
    ],
  })
}

initialState.value = buildState(props.modelValue)

onMounted(() => {
  if (!mount.value) return

  view.value = new EditorView(mount.value, {
    state: initialState.value ?? buildState(props.modelValue),
    nodeViews: {
      image: (node, editorView, getPos) =>
        new ImageNodeView(node, editorView, getPos, {
          pickImage,
          openMedia: (id) => router.visit(panelUrl(`/library/media/${id}`)),
        }),
    },
    editable: () => readError.value === '',
    dispatchTransaction(transaction) {
      const current = view.value
      if (!current) return

      const next = current.state.apply(transaction)
      current.updateState(next)

      // Never write back over a value we could not read in the first place.
      if (transaction.docChanged && readError.value === '') {
        lastEmitted = serializeDoc(next.doc)
        emit('update:modelValue', lastEmitted)
      }

      syncUi()
    },
  })

  syncUi()
})

onBeforeUnmount(() => {
  view.value?.destroy()
  view.value = null
})

// External changes (a reset, a different page) rebuild the state. Our own emits
// are filtered out so the caret is not thrown to the start on every keystroke.
watch(
  () => props.modelValue,
  (value) => {
    if (!view.value || value === lastEmitted) return
    view.value.updateState(buildState(value))
    syncUi()
  },
)

// ── UI sync ──────────────────────────────────────────────────────────────────

function syncUi() {
  const current = view.value
  if (!current) return

  const { state } = current

  active.strong = isMarkActive(state, schema.marks.strong)
  active.em = isMarkActive(state, schema.marks.em)
  active.code = isMarkActive(state, schema.marks.code)
  active.link = isMarkActive(state, schema.marks.link)

  const slashState = getSlashMenuState(state)

  if (slashState.from !== null) {
    slash.visible = true
    slash.query = slashState.query
    slash.focus = Math.min(slash.focus, Math.max(0, slashOptions.value.length - 1))
    slash.style = positionAt(slashState.from, 'below')
  } else {
    slash.visible = false
    slash.query = ''
    slash.focus = 0
  }

  // The selection toolbar and the slash menu should never overlap.
  toolbar.visible = !state.selection.empty && current.hasFocus() && !slash.visible

  if (toolbar.visible) {
    toolbar.style = positionAt(state.selection.from, 'above')
  } else if (!linkInput.open) {
    linkInput.open = false
  }
}

function positionAt(pos: number, placement: 'above' | 'below'): Record<string, string> {
  const current = view.value
  if (!current) return {}

  const coords = current.coordsAtPos(pos)

  return placement === 'above'
    ? {
        left: `${coords.left}px`,
        top: `${coords.top}px`,
        transform: 'translateX(-50%) translateY(calc(-100% - 8px))',
      }
    : { left: `${coords.left}px`, top: `${coords.bottom + 6}px` }
}

// ── Command plumbing ─────────────────────────────────────────────────────────

function run(command: Command) {
  const current = view.value
  if (!current) return

  command(current.state, current.dispatch.bind(current), current)
  current.focus()
}

function runOption(option: BlockOption) {
  run(option.command)
}

function chooseSlashOption(option: BlockOption) {
  const current = view.value
  if (!current) return

  // Remove the "/query" the user typed, then apply the block command to the
  // now-empty paragraph.
  current.dispatch(closeSlashMenu(current.state, true))
  run(option.command)
}

// ── Link input ───────────────────────────────────────────────────────────────

function openLinkInput() {
  const current = view.value
  if (!current || current.state.selection.empty) return

  linkInput.value = activeLinkHref(current.state)
  linkInput.error = ''
  linkInput.open = true
  toolbar.visible = true
  toolbar.style = positionAt(current.state.selection.from, 'above')

  nextTick(() => {
    linkInputEl.value?.focus()
    linkInputEl.value?.select()
  })
}

function applyLink() {
  const raw = linkInput.value.trim()

  if (raw === '') {
    removeLink()
    return
  }

  const href = normalizeUrl(raw)

  // normalizeUrl returns '' for a scheme we will not render. Say so rather than
  // silently dropping the link — a rejected paste should be visible.
  if (href === '' || sanitizeUrl(href) === '') {
    linkInput.error = 'Unsupported link'
    return
  }

  run(commands.setLink(href))
  closeLinkInput()
}

function removeLink() {
  run(commands.setLink(''))
  closeLinkInput()
}

function cancelLink() {
  closeLinkInput()
  view.value?.focus()
}

function closeLinkInput() {
  linkInput.open = false
  linkInput.value = ''
  linkInput.error = ''
}

// Arrow/Enter/Escape for the slash menu. Bound at the document level because
// ProseMirror owns keydown inside the editor and the menu is teleported out.
function onKeydown(event: KeyboardEvent) {
  if (!slash.visible) return

  const options = slashOptions.value

  if (event.key === 'ArrowDown') {
    event.preventDefault()
    slash.focus = options.length === 0 ? 0 : (slash.focus + 1) % options.length
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    slash.focus = options.length === 0 ? 0 : (slash.focus - 1 + options.length) % options.length
  } else if (event.key === 'Enter' && options[slash.focus]) {
    event.preventDefault()
    chooseSlashOption(options[slash.focus])
  } else if (event.key === 'Escape') {
    event.preventDefault()
    const current = view.value
    if (current) current.dispatch(closeSlashMenu(current.state, false))
  }
}

onMounted(() => document.addEventListener('keydown', onKeydown, { capture: true }))
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown, { capture: true }))

defineExpose({
  focus: () => view.value?.focus(),
  /** The live EditorView, for parents that need to drive the editor directly. */
  editor: () => view.value,
})
</script>

<style>
/* ProseMirror owns the DOM inside .pm-editor, so its content is styled here
   rather than with scoped classes on elements we do not render ourselves. */
.pm-editor .ProseMirror { outline: none; min-height: 8rem; }
.pm-editor .ProseMirror > * + * { margin-top: 0.75rem; }
.pm-editor .ProseMirror h2 { font-size: 1.25rem; font-weight: 600; }
.pm-editor .ProseMirror h3 { font-size: 1.125rem; font-weight: 600; }
.pm-editor .ProseMirror h4,
.pm-editor .ProseMirror h5,
.pm-editor .ProseMirror h6 { font-weight: 600; }
.pm-editor .ProseMirror strong { font-weight: 600; }
.pm-editor .ProseMirror em { font-style: italic; }
.pm-editor .ProseMirror a { text-decoration: underline; color: inherit; }
.pm-editor .ProseMirror code {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.875em;
  background: rgb(243 244 246);
  padding: 0.1em 0.3em;
  border-radius: 0.25rem;
}
.pm-editor .ProseMirror pre {
  background: rgb(243 244 246);
  padding: 0.75rem;
  border-radius: 0.375rem;
  overflow-x: auto;
}
.pm-editor .ProseMirror pre code { background: none; padding: 0; }
.pm-editor .ProseMirror blockquote {
  border-left: 3px solid rgb(209 213 219);
  padding-left: 0.75rem;
  color: rgb(75 85 99);
}
.pm-editor .ProseMirror ul { list-style: disc; padding-left: 1.25rem; }
.pm-editor .ProseMirror ol { list-style: decimal; padding-left: 1.25rem; }
.pm-editor .ProseMirror li > * + * { margin-top: 0.25rem; }

.pm-editor .pm-placeholder::before {
  content: attr(data-placeholder);
  color: rgb(209 213 219);
  float: left;
  height: 0;
  pointer-events: none;
}

.pm-editor .ProseMirror-selectednode {
  outline: 2px solid rgb(59 130 246);
  border-radius: 0.375rem;
}
.pm-editor .pm-dropcursor { background: rgb(59 130 246); }

/* Drag handle. Sits in the gutter the editor's `pl-9` reserves, and only
   appears while a block is hovered — the plugin toggles `display`. */
.pm-drag-handle {
  position: absolute;
  left: 0.5rem;
  /* The plugin sets display explicitly in both directions; this is only the
     value before it has run. */
  display: none;
  align-items: center;
  justify-content: center;
  width: 1rem;
  padding: 0.125rem 0.125rem;
  border-radius: 0.25rem;
  color: rgb(209 213 219);
  cursor: grab;
  user-select: none;
}
.pm-drag-handle:hover { color: rgb(107 114 128); background: rgb(243 244 246); }
.pm-drag-handle:active { cursor: grabbing; }
.pm-drag-handle svg {
  width: 0.75rem;
  height: 1.25rem;
  /* The icon must not be the drag source. Chromium resolves a drag against the
     element under the cursor, and an inner <svg> does not hand the gesture up
     to its draggable parent — mousedown fires, dragstart never does. */
  pointer-events: none;
}
</style>
