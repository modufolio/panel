<template>
  <!-- Switch first, label beside it: the field parts are composed here, since
       FieldPrimitive's frame stacks a label above its control. -->
  <div class="ui-field-toggle" :class="widthClass">
    <div class="flex items-start gap-3">
      <!-- Toggle Switch -->
      <button
        type="button"
        :id="id"
        role="switch"
        :aria-checked="modelValue"
        :aria-describedby="ariaDescribedby"
        :aria-invalid="!!error"
        @click="toggle"
        :disabled="disabled"
        :class="toggleClasses"
        class="ui-field-toggle-switch relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-focus focus:ring-offset-2 focus:ring-offset-surface disabled:cursor-not-allowed disabled:opacity-50"
      >
        <span
          :class="modelValue ? 'translate-x-5' : 'translate-x-0'"
          class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-surface-raised shadow ring-0 transition duration-200 ease-in-out"
        />
      </button>

      <!-- Label and Description -->
      <div class="flex-1">
        <FieldLabel
          v-if="label"
          :for="id"
          spacing="none"
          class="cursor-pointer"
          :class="{ 'mb-1': description }"
          @click="toggle"
        >
          {{ label }}
        </FieldLabel>

        <p v-if="description" :id="`${id}-description`" class="ui-field-description text-sm text-ink-2">
          {{ description }}
        </p>

        <FieldDescription v-if="help" :id="`${id}-help`">{{ help }}</FieldDescription>
        <FieldMessage v-if="error" :id="`${id}-error`">{{ error }}</FieldMessage>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import FieldDescription from './FieldDescription.vue'
import FieldLabel from './FieldLabel.vue'
import FieldMessage from './FieldMessage.vue'
import { computed } from 'vue'
import { useId } from '../../Primitives/useId'
import { useFieldWidth, fieldWidthProp } from './useFieldWidth'

const props = defineProps({
  ...fieldWidthProp,
  modelValue: {
    type: Boolean,
    default: false,
  },
  id: {
    type: String,
    default: () => useId(undefined, 'field'),
  },
  label: {
    type: String,
    default: '',
  },
  description: {
    type: String,
    default: '',
  },
  help: {
    type: String,
    default: '',
  },
  error: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  color: {
    type: String,
    default: 'primary',
    validator: (value: unknown) => ['primary', 'success', 'danger', 'warning', 'info'].includes(value as string),
  },
})

const emit = defineEmits(['update:modelValue'])

const widthClass = useFieldWidth(() => props.width)

const ariaDescribedby = computed(() => {
  const ids = []
  if (props.description) ids.push(`${props.id}-description`)
  if (props.help) ids.push(`${props.id}-help`)
  if (props.error) ids.push(`${props.id}-error`)
  return ids.length > 0 ? ids.join(' ') : undefined
})

const toggleClasses = computed(() => {
  // Spelled out rather than built from `props.color`: Tailwind only emits a
  // class it can see as a literal in the source.
  const colors: Record<string, string> = {
    primary: 'bg-primary-fill',
    success: 'bg-success-fill',
    danger: 'bg-danger-fill',
    warning: 'bg-warning-fill',
    info: 'bg-info-fill',
  }

  if (props.modelValue) {
    return colors[props.color]
  }

  return 'bg-track-off'
})

function toggle() {
  if (!props.disabled) {
    emit('update:modelValue', !props.modelValue)
  }
}
</script>
