<template>
  <button
    type="button"
    role="menuitem"
    :disabled="disabled"
    :title="title || undefined"
    :aria-disabled="disabled || undefined"
    @click="handleClick"
    class="ui-action-group-item w-full flex items-center gap-3 px-4 py-2 text-sm text-left transition-colors"
    :class="disabled && 'cursor-not-allowed opacity-50'"
    :data-color="resolvedColor"
  >
    <!-- Icon (string name or component) -->
    <template v-if="icon">
      <Icon v-if="typeof icon === 'string'" :name="icon" class="ui-action-group-item-icon w-5 h-5 shrink-0" />
      <component v-else :is="icon" class="ui-action-group-item-icon w-5 h-5 shrink-0" />
    </template>
    <!-- Slot icon (legacy / custom) -->
    <slot v-else-if="$slots.default" />

    <!-- Label -->
    <span class="flex-1">{{ label }}</span>
  </button>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Icon from '../Core/Icon.vue'
import { isSemanticColorInput, semanticColor } from '../../Utils/colors'

const props = defineProps({
  label: {
    type: String,
    required: true,
  },
  icon: {
    type: [Object, String],
    default: null,
  },
  color: {
    type: String,
    default: 'gray',
    validator: isSemanticColorInput,
  },
  /** Offered but refused: shown, not clickable, with `title` saying why. */
  disabled: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['click'])

/** Label colour, icon colour and the hover tint are all `data-color` in
 *  styles/components.css — danger is the only role that tints the whole row. */
const resolvedColor = computed(() => semanticColor(props.color))

function handleClick() {
  if (props.disabled) return
  emit('click')
}
</script>
