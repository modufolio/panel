<template>
  <div :class="($attrs as any).class">
    <label v-if="label" :for="id">{{ label }}:</label>
    <input
      ref="inputRef"
      :id="id"
      class="form-input"
      :class="{ error: !!error }"
      :type="type"
      :value="modelValue"
      :aria-invalid="!!error"
      :aria-describedby="error ? `${id}-error` : undefined"
      v-bind="attrsWithoutClass"
      @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
    <div v-if="error" :id="`${id}-error`" class="form-error" role="alert">{{ error }}</div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, getCurrentInstance, useAttrs } from 'vue'

defineOptions({ inheritAttrs: false })

const rawAttrs = useAttrs()
const attrsWithoutClass = computed(() => {
  const { class: _, ...rest } = rawAttrs as Record<string, unknown>
  return rest
})

withDefaults(defineProps<{
  id?: string
  label?: string
  modelValue?: string
  error?: string
  type?: string
}>(), {
  id: () => `input-${getCurrentInstance()?.uid}`,
  type: 'text',
  modelValue: '',
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

const inputRef = ref<HTMLInputElement | null>(null)

function focus() { inputRef.value?.focus() }
function select() { inputRef.value?.select() }
function setSelectionRange(start: number, end: number) { inputRef.value?.setSelectionRange(start, end) }

defineExpose({ focus, select, setSelectionRange })
</script>
