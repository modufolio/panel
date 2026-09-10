<template>
  <Dropdown
    placement="bottom-end"
    class="p-2 text-ink-3 hover:bg-hover focus-visible:bg-hover rounded-lg transition-colors duration-75"
    :aria-label="triggerLabel"
    :title="triggerLabel"
  >
    <icon :name="isDark ? 'moon' : 'sun'" class="w-5 h-5" />

    <template #dropdown>
      <div class="mt-2 py-1 w-48 text-sm bg-surface-raised text-ink rounded-lg shadow-xl ring-1 ring-hairline">
        <button
          v-for="option in options"
          :key="option"
          type="button"
          role="menuitemradio"
          :aria-checked="preference === option"
          :data-theme-option="option"
          :class="[
            'flex items-center w-full gap-3 px-3 py-2 text-left transition-colors duration-75',
            preference === option
              ? 'bg-primary-surface text-primary-on-surface'
              : 'hover:bg-hover focus-visible:bg-hover'
          ]"
          @click="set(option)"
        >
          <icon :name="ICONS[option]" class="w-4 h-4 shrink-0" />
          <span class="flex-1">{{ LABELS[option] }}</span>
          <!-- Only `system` has a resolution to report, and only while it is
               the live preference — the composable exposes what the OS says
               through `theme`, not on its own. -->
          <span v-if="option === 'system' && preference === 'system'" class="text-xs text-ink-3">
            {{ LABELS[theme] }}
          </span>
        </button>
      </div>
    </template>
  </Dropdown>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Icon from '../Core/Icon.vue'
import Dropdown from '../Core/Dropdown.vue'
import { useTheme, type ThemePreference } from '../../Composables/useTheme'

/**
 * The three-state theme control: System, Light, Dark.
 *
 * Exported on its own rather than baked into TopNavigation so a consumer with
 * its own top bar can place it, which is the same reason the layout pieces are
 * separate components.
 */

const LABELS: Record<ThemePreference, string> = {
  system: 'System',
  light: 'Light',
  dark: 'Dark',
}

const ICONS: Record<ThemePreference, string> = {
  system: 'monitor',
  light: 'sun',
  dark: 'moon',
}

const { preference, theme, isDark, options, set } = useTheme()

// The trigger shows what the panel currently *is*, so its label has to name
// the preference as well — a sun icon under a `system` preference is otherwise
// indistinguishable from an explicit `light`.
const triggerLabel = computed(() =>
  preference.value === 'system'
    ? `Theme: System (${LABELS[theme.value].toLowerCase()})`
    : `Theme: ${LABELS[preference.value]}`
)
</script>
