<script setup lang="ts">
import { fieldWidthProp, useFieldWidth } from './useFieldWidth'

/**
 * A break between two runs of fields. Takes the full grid row so what follows
 * starts on a fresh line; `line` draws a rule, `space` draws nothing.
 *
 * Accepts the props every field receives from BlueprintForm and ignores the
 * ones that mean nothing here — there is no value, no label, no error.
 */
const props = defineProps({
  ...fieldWidthProp,
  /** `line` or `space`, from the server's SeparatorType. */
  separator: { type: String, default: 'line' },
  modelValue: { type: null, default: null },
  label: { type: String, default: '' },
  help: { type: String, default: '' },
  id: { type: String, default: '' },
})

const widthClass = useFieldWidth(() => props.width)
</script>

<template>
  <div
    class="ui-field-separator"
    :class="[widthClass, separator === 'line' ? 'border-t border-gray-200 my-1' : 'h-2']"
    role="separator"
    aria-hidden="true"
  />
</template>
