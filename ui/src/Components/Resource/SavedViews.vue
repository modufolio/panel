<template>
  <div class="relative">
    <button
      ref="triggerRef"
      type="button"
      class="inline-flex items-center gap-2 rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm font-medium text-label shadow-sm transition-colors hover:bg-hover focus:outline-none focus:ring-2 focus:ring-focus focus:ring-offset-0"
      :aria-expanded="isOpen"
      aria-haspopup="true"
      @click="isOpen = !isOpen"
    >
      <Icon name="bookmark" class="h-4 w-4" aria-hidden="true" />
      <!-- The active view's name replaces the label: which list you are
           looking at matters more than what the button opens. -->
      <span>{{ active ?? 'Views' }}</span>
      <Icon name="chevron-down" class="h-4 w-4 text-ink-3" aria-hidden="true" />
    </button>

    <Teleport :to="teleportTarget">
      <Transition
        enter-active-class="transition duration-100 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-75 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-show="isOpen"
          ref="dropdownRef"
          class="z-50 flex w-72 flex-col origin-top-right rounded-lg border border-line bg-surface-raised shadow-lg"
          :style="floatingStyles"
        >
          <div class="flex min-h-0 flex-1 flex-col p-3">
            <h3 class="mb-2 text-sm font-medium text-ink">Saved views</h3>

            <p v-if="views.length === 0" class="px-1 py-2 text-sm text-ink-3">
              No saved views yet. Filter the list, then save it here.
            </p>

            <ul v-else class="min-h-0 flex-1 space-y-1 overflow-y-auto">
              <li v-for="view in views" :key="view.name" class="group flex items-center gap-1">
                <button
                  type="button"
                  class="flex-1 truncate rounded-md px-3 py-2 text-left text-sm transition-colors hover:bg-hover"
                  :class="view.name === active ? 'font-medium text-primary' : 'text-label'"
                  :aria-current="view.name === active ? 'true' : undefined"
                  @click="apply(view)"
                >
                  {{ view.name }}
                </button>
                <button
                  type="button"
                  class="shrink-0 rounded-md p-1.5 text-ink-3 transition-colors hover:bg-hover hover:text-danger focus:outline-none focus:ring-2 focus:ring-focus"
                  :aria-label="`Delete ${view.name}`"
                  @click="emit('delete', view.name)"
                >
                  <Icon name="trash" class="h-4 w-4" aria-hidden="true" />
                </button>
              </li>
            </ul>
          </div>

          <div class="border-t border-line bg-surface-sunken p-3">
            <!--
              Naming the current list is the whole feature, so the input is the
              footer rather than something behind another click. Saving over an
              existing name replaces it — that is how a view is edited.
            -->
            <form class="flex items-center gap-2" @submit.prevent="save">
              <input
                v-model="name"
                type="text"
                :placeholder="active ?? 'Name this view'"
                aria-label="Name this view"
                class="min-w-0 flex-1 rounded-md border border-line-strong bg-surface px-2 py-1.5 text-sm text-ink shadow-sm focus:border-focus focus:outline-none focus:ring-1 focus:ring-focus"
              />
              <button
                type="submit"
                class="shrink-0 rounded-md bg-primary-fill px-3 py-1.5 text-sm font-medium text-primary-on-fill transition-colors hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="name.trim() === ''"
              >
                Save
              </button>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
/**
 * The saved-view picker: apply one, delete one, or name the list as it stands.
 *
 * Deliberately dumb — it holds no storage and reads no filter state. The page
 * owns both, so this component is the same whether views live in the browser
 * or, one day, behind an endpoint.
 */
import { ref, type PropType } from 'vue'
import Icon from '../Core/Icon.vue'
import { useAnchoredPosition } from '../../Primitives/useAnchoredPosition'
import { useDismissableLayer } from '../../Primitives/useDismissableLayer'
import { getTeleportTarget } from '../../Primitives/teleportTarget'
import type { SavedView } from '../../Composables/savedViews'

const props = defineProps({
  views: { type: Array as PropType<SavedView[]>, default: () => [] },
  /** The view the list is currently showing, if it matches one. */
  active: { type: String as PropType<string | null>, default: null },
})

const emit = defineEmits<{
  apply: [view: SavedView]
  save: [name: string]
  delete: [name: string]
}>()

const isOpen = ref(false)
const name = ref('')
const triggerRef = ref<HTMLElement | null>(null)
const dropdownRef = ref<HTMLElement | null>(null)
const teleportTarget = getTeleportTarget()

const { floatingStyles } = useAnchoredPosition(triggerRef, dropdownRef, isOpen, {
  placement: 'bottom-end',
})

useDismissableLayer(isOpen, {
  elements: () => [triggerRef.value, dropdownRef.value],
  onDismiss: () => { isOpen.value = false },
})

function apply(view: SavedView): void {
  isOpen.value = false
  emit('apply', view)
}

function save(): void {
  const label = name.value.trim() || props.active

  if (!label) return

  emit('save', label)
  name.value = ''
  isOpen.value = false
}
</script>
