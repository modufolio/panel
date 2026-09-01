<script setup lang="ts">
/**
 * A field's label, as an element of its own.
 *
 * Most fields get this through FieldPrimitive and never name it. It is exported
 * for the layouts the primitive's stacked arrangement cannot express — a
 * checkbox whose label sits *beside* the control — so those keep the same
 * typography, the same required marker and the same class hook instead of
 * reproducing them by hand, which is how `mb-1`, `mb-1.5` and `mb-2` all came
 * to mean "the space under a label".
 */
import { computed, type PropType } from 'vue'

const props = defineProps({
  /** The control this labels. Ignored when rendering as a `legend`. */
  for: { type: String, default: undefined },
  /**
   * `legend` for a group of controls (a date+time pair, a set of sub-fields):
   * `<label for>` may only point at one form control, so a fieldset's caption
   * has to be a legend or it labels nothing.
   */
  as: { type: String as PropType<'label' | 'legend'>, default: 'label' },
  required: { type: Boolean, default: false },
  /** Bottom margin, for the rare control that hugs its label. */
  spacing: { type: String as PropType<'default' | 'none'>, default: 'default' },
})

const classes = computed(() => [
  'ui-field-label block text-sm font-medium text-gray-700 dark:text-gray-300',
  props.spacing === 'default' ? 'mb-1.5' : '',
  // Marked in CSS rather than in the text so the asterisk is never read out as
  // part of the label; `aria-required` on the control is what announces it.
  props.required ? "after:content-['*'] after:ml-0.5 after:text-danger-600" : '',
])
</script>

<template>
  <component :is="as" :for="as === 'label' ? props.for : undefined" :class="classes">
    <slot />
  </component>
</template>
