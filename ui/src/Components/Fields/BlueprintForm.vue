<template>
  <FieldsSection :label="label" :help="help" :card="card" :collapsible="collapsible">
    <template v-if="$slots.headerActions" #headerActions>
      <slot name="headerActions" />
    </template>

    <!--
      A type nothing renders is a declaration error. Said here, where the
      form is, with the registration that fixes it — a field that silently
      never appears is the failure this replaces.
    -->
    <pre
      v-if="unknownTypes.length"
      class="ui-field-unknown-types col-span-12 whitespace-pre-wrap rounded-md border border-danger bg-danger-surface p-3 font-mono text-xs text-danger-on-surface"
      role="alert"
    >{{ unknownTypeMessage }}</pre>

    <!--
      Fields above the tabs render first, always. Then the tab bar, then the
      active tab's fields. A fieldset is a boxed run of fields inside either.
    -->
    <template v-for="run in runs(ungroupedFields)" :key="run.key">
      <fieldset
        v-if="run.fieldset"
        class="ui-fieldset col-span-12 rounded-md border border-line p-4"
      >
        <legend class="px-1 text-sm font-medium text-ink">{{ run.fieldset.label }}</legend>
        <p v-if="run.fieldset.help" class="mb-3 -mt-1 text-xs text-ink-3">{{ run.fieldset.help }}</p>
        <FieldGrid>
          <component
            :is="fieldComponent(field.type)"
            v-for="field in run.fields"
            :key="field.key"
            :model-value="(modelValue as Record<string, unknown>)[field.key]"
            v-bind="blueprint.fieldProps(field, shownErrors)"
            @update:model-value="(val: unknown) => onFieldInput(field.key, val)"
          />
        </FieldGrid>
      </fieldset>
      <template v-else>
        <component
          :is="fieldComponent(field.type)"
          v-for="field in run.fields"
          :key="field.key"
          :model-value="(modelValue as Record<string, unknown>)[field.key]"
          v-bind="blueprint.fieldProps(field, shownErrors)"
          @update:model-value="(val: unknown) => onFieldInput(field.key, val)"
        />
      </template>
    </template>

    <template v-if="tabs.length">
      <FormTabs v-model="activeTab" :tabs="tabs" :errors="tabErrors" />
      <div
        v-for="tab in tabs"
        v-show="tab.key === activeTab"
        :key="tab.key"
        class="ui-form-tab-panel col-span-12"
        role="tabpanel"
        :data-tab-panel="tab.key"
      >
        <FieldGrid>
          <template v-for="run in runs(groupedFields[tab.key] ?? [])" :key="run.key">
            <fieldset
              v-if="run.fieldset"
              class="ui-fieldset col-span-12 rounded-md border border-line p-4"
            >
              <legend class="px-1 text-sm font-medium text-ink">{{ run.fieldset.label }}</legend>
              <p v-if="run.fieldset.help" class="mb-3 -mt-1 text-xs text-ink-3">{{ run.fieldset.help }}</p>
              <FieldGrid>
                <component
                  :is="fieldComponent(field.type)"
                  v-for="field in run.fields"
                  :key="field.key"
                  :model-value="(modelValue as Record<string, unknown>)[field.key]"
                  v-bind="blueprint.fieldProps(field, shownErrors)"
                  @update:model-value="(val: unknown) => onFieldInput(field.key, val)"
                />
              </FieldGrid>
            </fieldset>
            <template v-else>
              <component
                :is="fieldComponent(field.type)"
                v-for="field in run.fields"
                :key="field.key"
                :model-value="(modelValue as Record<string, unknown>)[field.key]"
                v-bind="blueprint.fieldProps(field, shownErrors)"
                @update:model-value="(val: unknown) => onFieldInput(field.key, val)"
              />
            </template>
          </template>
        </FieldGrid>
      </div>
    </template>

    <template v-if="$slots.footer" #footer>
      <slot name="footer" />
    </template>
  </FieldsSection>
</template>

<script setup lang="ts">
import { computed, defineAsyncComponent, ref, watch, type Component, type PropType } from 'vue'
import { sentenceLabel } from '../../Utils/labels'
import FieldsSection from '../Sections/FieldsSection.vue'
import FieldGrid from './FieldGrid.vue'
import FormTabs from './FormTabs.vue'
import { useBlueprint, resolveFieldComponent, type FieldDef, type FieldType, type FormLayout } from './useBlueprint'
import { missingFieldTypes, unknownFieldTypeMessage } from './fieldRegistry'

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
  /** The tabs and fieldsets the server declared; a flat form passes none. */
  layout: {
    type: Object as PropType<FormLayout>,
    default: () => ({}),
  },
})

const emit = defineEmits<{
  'update:modelValue': [value: Record<string, unknown>]
}>()

// Getters, not the unwrapped props: this component emits a *new* model object
// on every change, so a captured snapshot would freeze conditional visibility
// at whatever the values were when the form first rendered.
const blueprint = useBlueprint(() => props.fields, () => props.modelValue)
const { visibleFields: renderableFields, visibleData, clientErrors, isValid } = blueprint

// Checked against the whole declaration, hidden fields included: a `when`
// that is false today does not make the type it hides any more renderable.
const unknownTypes = computed(() => missingFieldTypes(props.fields))
const unknownTypeMessage = computed(() => unknownFieldTypeMessage(unknownTypes.value))
const visibleFields = computed(() => renderableFields.value.filter((field) => !unknownTypes.value.includes(field.type)))

watch(unknownTypes, (types) => {
  if (types.length) console.error(unknownFieldTypeMessage(types))
}, { immediate: true })

// ── Tabs and fieldsets ───────────────────────────────────────────────────────
//
// The server flattened its containers into `group` and `fieldset` on each
// field and sent the containers as `layout`. Rebuilding the structure here,
// from the *visible* fields, is what lets a tab disappear when every field
// in it is hidden by a condition — a bar with an empty tab is a bug the user
// sees before the developer does.

const groupedFields = computed<Record<string, FieldDef[]>>(() => {
  const groups: Record<string, FieldDef[]> = {}
  for (const field of visibleFields.value) {
    if (field.group) (groups[field.group] ??= []).push(field)
  }
  return groups
})

const ungroupedFields = computed(() => visibleFields.value.filter((field) => !field.group))

/** Declared tabs first, in their order; a group no layout named still gets a tab. Empty tabs are dropped. */
const tabs = computed(() => {
  const declared = props.layout.tabs ?? []
  const keys = [...declared.map((tab) => tab.key), ...Object.keys(groupedFields.value)]
  const seen = new Set<string>()
  const result: Array<{ key: string; label: string; icon?: string | null }> = []

  for (const key of keys) {
    if (seen.has(key) || !(groupedFields.value[key]?.length)) continue
    seen.add(key)
    result.push(declared.find((tab) => tab.key === key) ?? { key, label: sentenceLabel(key) })
  }

  return result
})

const chosenTab = ref<string | null>(null)

const activeTab = computed<string>({
  get: () => {
    const wanted = chosenTab.value
    return wanted !== null && tabs.value.some((tab) => tab.key === wanted) ? wanted : (tabs.value[0]?.key ?? '')
  },
  set: (key) => { chosenTab.value = key },
})

interface Run {
  key: string
  fieldset: { key: string; label: string; help?: string | null } | null
  fields: FieldDef[]
}

/** Contiguous fields with the same fieldset form one boxed run; the rest render bare. */
function runs(fields: FieldDef[]): Run[] {
  const result: Run[] = []

  for (const field of fields) {
    const last = result[result.length - 1]
    const key = field.fieldset ?? null

    if (last && (last.fieldset?.key ?? null) === key) {
      last.fields.push(field)
      continue
    }

    const declared = key === null ? null : (props.layout.fieldsets ?? []).find((set) => set.key === key) ?? { key, label: sentenceLabel(key) }
    result.push({ key: `${key ?? 'bare'}:${field.key}`, fieldset: declared, fields: [field] })
  }

  return result
}

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

/** Fields with a shown error, counted per tab — the badge on the bar. */
const tabErrors = computed<Record<string, number>>(() => {
  const counts: Record<string, number> = {}
  for (const field of visibleFields.value) {
    if (field.group && shownErrors.value[field.key] !== undefined) {
      counts[field.group] = (counts[field.group] ?? 0) + 1
    }
  }
  return counts
})

// An error on a tab that is not showing is an error nobody can see. When the
// active tab is clean and another is not, switch — after a submit or a server
// verdict, which is when errors appear all at once.
watch(tabErrors, (counts) => {
  if (!counts[activeTab.value]) {
    const first = tabs.value.find((tab) => counts[tab.key])
    if (first) chosenTab.value = first.key
  }
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
