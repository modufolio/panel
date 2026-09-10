<template>
  <div class="ui-boolean-column inline-flex items-center gap-2">
    <span :class="iconClasses" class="inline-flex items-center justify-center w-5 h-5 rounded-full">
      <component :is="icon" class="w-3.5 h-3.5" />
    </span>
    <span v-if="showLabel" :class="labelClasses">{{ label }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { pathIcon } from '../../Utils/pathIcon'

const CheckIcon = pathIcon('M4.5 12.75l6 6 9-13.5', { strokeWidth: 2.5 })
const CrossIcon = pathIcon('M6 18L18 6M6 6l12 12', { strokeWidth: 2.5 })

const props = defineProps({
  value: {
    type: [Boolean, Number, String],
    default: false,
  },
  trueLabel: {
    type: String,
    default: 'Yes',
  },
  falseLabel: {
    type: String,
    default: 'No',
  },
  trueColor: {
    type: String,
    default: 'success',
  },
  falseColor: {
    type: String,
    default: 'gray',
  },
  trueIcon: {
    type: Object,
    default: null,
  },
  falseIcon: {
    type: Object,
    default: null,
  },
  showLabel: {
    type: Boolean,
    default: false,
  },
})

const boolValue = computed(() => {
  if (typeof props.value === 'boolean') return props.value
  if (typeof props.value === 'number') return props.value !== 0
  if (typeof props.value === 'string') {
    const lower = props.value.toLowerCase()
    return lower === 'true' || lower === '1' || lower === 'yes'
  }
  return false
})

const label = computed(() => {
  return boolValue.value ? props.trueLabel : props.falseLabel
})

const iconClasses = computed(() => {
  const colorClasses: Record<string, string> = {
    success: 'bg-success-surface text-success-on-surface',
    danger: 'bg-danger-surface text-danger-on-surface',
    warning: 'bg-warning-surface text-warning-on-surface',
    info: 'bg-info-surface text-info-on-surface',
    primary: 'bg-primary-surface text-primary-on-surface',
    gray: 'bg-gray-surface text-gray-on-surface',
  }

  const color = boolValue.value ? props.trueColor : props.falseColor
  return colorClasses[color] || colorClasses.gray
})

const labelClasses = computed(() => {
  const colorClasses: Record<string, string> = {
    success: 'text-success',
    danger: 'text-danger',
    warning: 'text-warning',
    info: 'text-info',
    primary: 'text-primary',
    gray: 'text-gray',
  }

  const color = boolValue.value ? props.trueColor : props.falseColor
  return ['text-sm font-medium', colorClasses[color] || colorClasses.gray]
})

const icon = computed(() => {
  if (boolValue.value && props.trueIcon) return props.trueIcon
  if (!boolValue.value && props.falseIcon) return props.falseIcon

  return boolValue.value ? CheckIcon : CrossIcon
})
</script>
