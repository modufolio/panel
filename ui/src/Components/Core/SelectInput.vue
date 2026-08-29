<template>
  <div :class="($attrs as any).class">
    <label v-if="label" :for="id">{{ label }}:</label>
    <select
      :id="id"
      class="form-select"
      :class="{ error: !!error }"
      :value="modelValue"
      :aria-invalid="!!error"
      :aria-describedby="error ? `${id}-error` : undefined"
      v-bind="attrsWithoutClass"
      @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
    >
      <slot />
    </select>
    <div v-if="error" :id="`${id}-error`" class="form-error" role="alert">{{ error }}</div>
  </div>
</template>

<script setup lang="ts">
import { computed, getCurrentInstance, useAttrs } from 'vue'

defineOptions({ inheritAttrs: false })

const rawAttrs = useAttrs()
const attrsWithoutClass = computed(() => {
  const { class: _, ...rest } = rawAttrs as Record<string, unknown>
  return rest
})

withDefaults(defineProps<{
  id?: string
  label?: string
  modelValue?: string | null
  error?: string
}>(), {
  id: () => `select-${getCurrentInstance()?.uid}`,
  modelValue: '',
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()
</script>
