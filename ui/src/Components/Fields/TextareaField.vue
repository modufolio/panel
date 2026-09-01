<template>
  <FieldPrimitive
    v-bind="{ width, id, label, help, error, required }"
    wrapper-class="ui-field-textarea"
    v-slot="{ describedBy, invalid }"
  >
    <div class="ui-field-wrapper">
      <textarea
        :id="id"
        :value="modelValue"
        @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
        :placeholder="placeholder"
        :disabled="disabled"
        :readonly="readonly"
        :required="required"
        :rows="rows"
        :aria-describedby="describedBy"
        :aria-invalid="invalid"
        :aria-required="required"
        :class="textareaClasses"
        class="ui-input ui-field-textarea block w-full resize-y"
      />
    </div>
  </FieldPrimitive>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useId } from '../../Primitives/useId'
import FieldPrimitive from './FieldPrimitive.vue'
import { fieldWidthProp } from './useFieldWidth'

const props = defineProps({
  ...fieldWidthProp,
  modelValue: {
    type: [String, Number],
    default: '',
  },
  id: {
    type: String,
    default: () => useId(undefined, 'field'),
  },
  label: {
    type: String,
    default: '',
  },
  placeholder: {
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
  rows: {
    type: Number,
    default: 4,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  readonly: {
    type: Boolean,
    default: false,
  },
  required: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['update:modelValue'])



const textareaClasses = computed(() => {
  const classes = ['px-3 py-2']

  if (props.error) {
    classes.push('border-danger-600 focus:border-danger-600 focus:ring-danger-600/20')
  }

  return classes
})
</script>
