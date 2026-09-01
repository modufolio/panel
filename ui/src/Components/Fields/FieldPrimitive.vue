<script setup lang="ts">
/**
 * The chrome every field shares: the grid width, the label, the help text, the
 * error message, and the ARIA wiring between them.
 *
 * Before this, twenty-two of the twenty-five field components rendered that
 * scaffolding themselves. They did not agree — `mb-1` / `mb-1.5` / `mb-2`
 * under the label, `role="alert"` on some error paragraphs and not others, the
 * required asterisk on some labels only, and `aria-describedby` computed by
 * hand in eight files and simply missing from the rest. None of that is a
 * field's business: a field is a *control*, and this is the frame around it.
 *
 * The control comes in through the default slot and gets what it needs to be
 * accessible as slot props:
 *
 *     <FieldPrimitive v-bind="chromeProps" v-slot="{ describedBy, invalid }">
 *       <input :id="id" :aria-describedby="describedBy" :aria-invalid="invalid" />
 *     </FieldPrimitive>
 *
 * A layout this arrangement cannot express — a checkbox whose label sits beside
 * the control — composes {@link FieldLabel}, {@link FieldDescription} and
 * {@link FieldMessage} directly instead.
 */
import { computed, type PropType } from 'vue'
import FieldDescription from './FieldDescription.vue'
import FieldLabel from './FieldLabel.vue'
import FieldMessage from './FieldMessage.vue'
import { useFieldWidth, fieldWidthProp } from './useFieldWidth'

const props = defineProps({
  ...fieldWidthProp,
  /**
   * The control's id. The help and error ids derive from it, so passing the
   * same id to the control is what connects the three.
   */
  id: { type: String, default: undefined },
  label: { type: String, default: '' },
  help: { type: String, default: '' },
  error: { type: String, default: '' },
  required: { type: Boolean, default: false },
  /**
   * `fieldset` when the slot holds several controls rather than one — the
   * label then renders as a `legend`, because `<label for>` can only point at
   * a single control.
   */
  as: { type: String as PropType<'div' | 'fieldset'>, default: 'div' },
  /** Extra classes for the wrapper, e.g. a field-specific `ui-field-*` hook. */
  wrapperClass: { type: [String, Array, Object], default: '' },
  /** Bottom margin under the label. */
  labelSpacing: { type: String as PropType<'default' | 'none'>, default: 'default' },
})

const widthClass = useFieldWidth(() => props.width)

const descriptionId = computed(() => (props.help && props.id ? `${props.id}-help` : undefined))
const errorId = computed(() => (props.error && props.id ? `${props.id}-error` : undefined))

/**
 * Both ids, in reading order, or undefined when there is nothing to point at —
 * an empty `aria-describedby` is worse than none, since it describes the
 * control as having a description that is not there.
 */
const describedBy = computed(() => {
  const ids = [descriptionId.value, errorId.value].filter(Boolean)

  return ids.length > 0 ? ids.join(' ') : undefined
})

const invalid = computed(() => (props.error ? true : undefined))
</script>

<template>
  <component :is="as" :class="[widthClass, wrapperClass, as === 'fieldset' ? 'min-w-0' : '']">
    <FieldLabel
      v-if="label"
      :for="id"
      :as="as === 'fieldset' ? 'legend' : 'label'"
      :required="required"
      :spacing="labelSpacing"
    >
      {{ label }}
    </FieldLabel>

    <slot
      :described-by="describedBy"
      :description-id="descriptionId"
      :error-id="errorId"
      :invalid="invalid"
    />

    <FieldDescription v-if="help" :id="descriptionId">{{ help }}</FieldDescription>
    <FieldMessage v-if="error" :id="errorId">{{ error }}</FieldMessage>
  </component>
</template>
