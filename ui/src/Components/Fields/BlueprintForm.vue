<template>
  <FieldsSection :label="label" :help="help" :card="card" :collapsible="collapsible">
    <template v-if="$slots.headerActions" #headerActions>
      <slot name="headerActions" />
    </template>

    <template v-for="field in visibleFields" :key="field.key">
      <component
        :is="fieldComponent(field.type)"
        :model-value="(modelValue as Record<string, unknown>)[field.key]"
        v-bind="blueprint.fieldProps(field, shownErrors)"
        @update:model-value="(val: unknown) => onFieldInput(field.key, val)"
      />
    </template>

    <template v-if="$slots.footer" #footer>
      <slot name="footer" />
    </template>
  </FieldsSection>
</template>

<script setup lang="ts">
import { computed, defineAsyncComponent, ref, watch, type Component } from 'vue'
import FieldsSection from '../Sections/FieldsSection.vue'
import { useBlueprint, resolveFieldComponent, type FieldDef, type FieldType } from './useBlueprint'

const props = defineProps({
  modelValue: {
    type: Object as () => Record<string, unknown>,
    required: true,
  },
  fields: {
    type: Array as () => FieldDef[],
    required: true,
  },
  errors: {
    type: Object as () => Record<string, string>,
    default: () => ({}),
  },
  label: {
    type: String,
    default: '',
  },
  help: {
    type: String,
    default: '',
  },
  card: {
    type: Boolean,
    default: true,
  },
  collapsible: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits<{
  'update:modelValue': [value: Record<string, unknown>]
}>()

// Getters, not the unwrapped props: this component emits a *new* model object
// on every change, so a captured snapshot would freeze conditional visibility
// at whatever the values were when the form first rendered.
const blueprint = useBlueprint(() => props.fields, () => props.modelValue)
const { visibleFields, visibleData, clientErrors, isValid } = blueprint

// A field that has been edited, or the whole form once a submit was attempted.
// Rules are evaluated from the start, but showing "required" on a field nobody
// has touched yet is nagging rather than helping.
const touched = ref(new Set<string>())
const submitted = ref(false)

// A fresh server verdict resets the bookkeeping: the edits `touched` was
// tracking are exactly what got submitted, so hiding the server's answer to
// them because they were "already edited" silences every error about a field
// the user actually filled in. From here, editing again hides the message —
// that edit is a *response* to it.
watch(() => props.errors, (errors) => {
  if (Object.keys(errors).length > 0) {
    touched.value = new Set()
  }
})

function onFieldInput(key: string, value: unknown) {
  touched.value = new Set(touched.value).add(key)
  emit('update:modelValue', { ...(props.modelValue as Record<string, unknown>), [key]: value })
}

/**
 * Client rules first, then whatever the server said.
 *
 * Concatenating rather than merging means neither source has to know about the
 * other: a client message is reactive and disappears as soon as the value is
 * fixed, while a server message persists until that field is edited again.
 */
const shownErrors = computed(() => {
  const shown: Record<string, string> = {}

  for (const field of visibleFields.value) {
    const key = field.key
    const client = clientErrors.value[key]

    if (client !== undefined && (submitted.value || touched.value.has(key))) {
      shown[key] = client
      continue
    }

    // A server error stands until the user edits that field.
    if (props.errors[key] !== undefined && !touched.value.has(key)) {
      shown[key] = props.errors[key]
    }

    // Row-addressed server errors (`cast.2.actor_id`) travel too — fieldProps
    // fans them out to the container's rows, but only if they survive this
    // filter. Same lifetime as a flat error: editing the container clears
    // them, since any edit may have been the fix and a stale row message
    // pinned to a reordered list would point at the wrong line.
    if (!touched.value.has(key)) {
      const prefix = `${key}.`

      for (const [errorKey, message] of Object.entries(props.errors)) {
        if (errorKey.startsWith(prefix)) {
          shown[errorKey] = message
        }
      }
    }
  }

  return shown
})

defineExpose({
  /** The model minus any currently hidden field — what a caller should submit. */
  visibleData,
  /** True when no visible field currently breaks a rule. */
  isValid,
  /** Every client-side failure, whether or not it is currently shown. */
  clientErrors,
  /**
   * Reveal all outstanding messages — call before submitting so untouched
   * invalid fields stop hiding.
   */
  validate: (): boolean => {
    submitted.value = true
    return isValid.value
  },
})

// Async wrapper per field type, backed by the shared field registry in
// useBlueprint (single source of truth, including app-registered types).
const componentCache: Partial<Record<FieldType, Component>> = {}

function fieldComponent(type: FieldType): Component {
  if (!componentCache[type]) {
    componentCache[type] = defineAsyncComponent(() => resolveFieldComponent(type))
  }
  return componentCache[type] as Component
}
</script>
