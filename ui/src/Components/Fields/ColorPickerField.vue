<template>
  <div class="space-y-1.5" :class="widthClass">
    <label
      v-if="label"
      class="block text-sm font-medium text-gray-700"
      :class="{ 'text-danger-700': error }"
    >
      {{ label }}
      <span v-if="required" class="text-danger-600">*</span>
    </label>

    <div class="flex gap-3">
      <!-- Color Preview Box -->
      <button
        type="button"
        class="group relative h-10 w-10 shrink-0 overflow-hidden rounded-lg border-2 shadow-sm transition-all duration-200"
        :class="[
          error
            ? 'border-danger-600'
            : disabled
            ? 'border-gray-300'
            : 'border-gray-300 hover:border-primary-400 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/20',
        ]"
        :disabled="disabled"
        @click="openColorPicker"
        :style="{ backgroundColor: displayColor }"
      >
        <!-- Checkered pattern for transparency -->
        <div
          v-if="!modelValue || modelValue === 'transparent'"
          class="absolute inset-0"
          style="
            background-image: linear-gradient(45deg, #e5e7eb 25%, transparent 25%),
              linear-gradient(-45deg, #e5e7eb 25%, transparent 25%),
              linear-gradient(45deg, transparent 75%, #e5e7eb 75%),
              linear-gradient(-45deg, transparent 75%, #e5e7eb 75%);
            background-size: 8px 8px;
            background-position: 0 0, 0 4px, 4px -4px, -4px 0px;
          "
        />

        <!-- Hover overlay -->
        <div
          class="absolute inset-0 bg-black opacity-0 transition-opacity duration-200 group-hover:opacity-10"
        />
      </button>

      <div class="flex-1">
        <!-- Color Input -->
        <div class="relative">
          <input
            ref="colorInput"
            v-model="localValue"
            type="color"
            class="sr-only"
            :disabled="disabled"
            @input="handleColorChange"
          />

          <!-- Text Input for Hex Value -->
          <div class="relative">
            <input
              v-model="hexInput"
              type="text"
              :placeholder="placeholder"
              :disabled="disabled"
              class="ui-input block w-full"
              :class="[
                error
                  ? 'border-danger-600 focus:border-danger-600 focus:ring-danger-600/20'
                  : disabled
                  ? 'border-gray-300 bg-gray-50 text-gray-500'
                  : 'border-gray-300 focus:border-primary-600 focus:ring-primary-600/20',
                'pl-3 pr-20 py-2 text-sm',
              ]"
              @input="handleHexInput"
              @blur="validateHexInput"
            />

            <!-- Format Badge -->
            <div
              class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none"
            >
              <span class="text-xs font-medium text-gray-400 uppercase">
                {{ format }}
              </span>
            </div>
          </div>
        </div>

        <!-- Predefined Colors -->
        <div v-if="presets.length > 0" class="mt-2 flex flex-wrap gap-1.5">
          <button
            v-for="preset in presets"
            :key="preset"
            type="button"
            class="h-6 w-6 rounded border-2 transition-all duration-200"
            :class="[
              modelValue === preset
                ? 'border-primary-600 ring-2 ring-primary-600/20'
                : 'border-gray-300 hover:border-gray-400',
              disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
            ]"
            :style="{ backgroundColor: preset }"
            :disabled="disabled"
            @click="selectPreset(preset)"
            :title="preset"
          />
        </div>
      </div>
    </div>

    <!-- Help Text -->
    <p v-if="help && !error" class="text-sm text-gray-500">
      {{ help }}
    </p>

    <!-- Error Message -->
    <p v-if="error" class="text-sm text-danger-600">
      {{ error }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, type PropType } from 'vue'
import { useFieldWidth, fieldWidthProp } from './useFieldWidth'

const props = defineProps({
  ...fieldWidthProp,
  modelValue: {
    type: String,
    default: '#000000',
  },
  label: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: '#000000',
  },
  help: {
    type: String,
    default: '',
  },
  error: {
    type: String,
    default: '',
  },
  required: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  format: {
    type: String,
    default: 'hex',
    validator: (value: unknown) => ['hex', 'rgb', 'hsl'].includes(value as string),
  },
  presets: {
    type: Array as PropType<string[]>,
    default: () => [
      '#000000',
      '#ffffff',
      '#ef4444',
      '#f97316',
      '#f59e0b',
      '#eab308',
      '#84cc16',
      '#22c55e',
      '#10b981',
      '#14b8a6',
      '#06b6d4',
      '#0ea5e9',
      '#3b82f6',
      '#6366f1',
      '#8b5cf6',
      '#a855f7',
      '#d946ef',
      '#ec4899',
    ],
  },
})

const emit = defineEmits(['update:modelValue'])

const widthClass = useFieldWidth(() => props.width)

// State
const colorInput = ref<HTMLInputElement | null>(null)
const localValue = ref(props.modelValue || '#000000')
const hexInput = ref(props.modelValue || '#000000')

// Computed
const displayColor = computed(() => {
  return localValue.value || '#ffffff'
})

// Functions
function openColorPicker() {
  if (!props.disabled && colorInput.value) {
    colorInput.value.click()
  }
}

function handleColorChange(event: Event): void {
  const target = event.target as HTMLInputElement
  const color = target.value
  localValue.value = color
  hexInput.value = color
  emit('update:modelValue', color)
}

function handleHexInput(event: Event): void {
  const target = event.target as HTMLInputElement
  let value = target.value.trim()

  // Auto-add # if not present
  if (value && !value.startsWith('#')) {
    value = '#' + value
    hexInput.value = value
  }

  // If it's a valid hex color, update the color
  if (isValidHex(value)) {
    localValue.value = value
    emit('update:modelValue', value)
  }
}

function validateHexInput() {
  // If invalid, revert to last valid value
  if (!isValidHex(hexInput.value)) {
    hexInput.value = localValue.value
  }
}

function isValidHex(color: string) {
  if (!color) return false
  // Match #RGB, #RRGGBB, #RRGGBBAA
  return /^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/.test(color)
}

function selectPreset(color: string) {
  if (!props.disabled) {
    localValue.value = color
    hexInput.value = color
    emit('update:modelValue', color)
  }
}

// Watch for external changes
watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue !== localValue.value) {
      localValue.value = newValue || '#000000'
      hexInput.value = newValue || '#000000'
    }
  }
)
</script>
