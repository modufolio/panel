<template>
  <!-- A real form, so Enter in any input submits — the footer button is not
       the only path to saving. -->
  <form class="space-y-4" @submit.prevent="emit('submit')">
    <!-- Server-side error banner -->
    <div v-if="serverError" class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
      {{ serverError }}
    </div>

    <div class="grid grid-cols-2 gap-4">
    <div
      v-for="field in fields.filter(f => f.type !== 'hidden')"
      :key="field.name"
      :class="{
        'col-span-2': field.grid === 'full' || !field.grid,
      }"
      class="space-y-2"
    >
      <label
        v-if="field.type !== 'checkbox'"
        :for="field.name"
        class="block text-sm font-medium text-gray-700"
      >
        {{ field.label }}
        <span v-if="field.required" class="text-red-500">*</span>
      </label>

      <!-- Text inputs -->
      <input
        v-if="field.type === 'text' || field.type === 'email' || field.type === 'number'"
        :id="field.name"
        :value="String(state[field.name] ?? '')"
        :type="field.type"
        :placeholder="field.placeholder"
        :maxlength="field.maxlength"
        class="ui-input w-full"
        :class="{ 'border-red-500 bg-red-50': errors[field.name] }"
        @input="(e) => setFieldValue(field.name, (e.target as HTMLInputElement).value)"
      />

      <!--
        Date and time. Native controls rather than the panel's own pickers:
        this form is the lightweight one, rendering plain inputs from a flat
        config, and pulling the blueprint field components in here would drag
        their whole registry along with them.
      -->
      <input
        v-else-if="field.type === 'date' || field.type === 'time' || field.type === 'datetime'"
        :id="field.name"
        :value="String(state[field.name] ?? '')"
        :type="field.type === 'datetime' ? 'datetime-local' : field.type"
        :min="field.min"
        :max="field.max"
        class="ui-input w-full"
        :class="{ 'border-red-500 bg-red-50': errors[field.name] }"
        @input="(e) => setFieldValue(field.name, (e.target as HTMLInputElement).value)"
      />

      <!-- Checkbox: its own label sits beside the box, not above it -->
      <label v-else-if="field.type === 'checkbox'" :for="field.name" class="flex items-center gap-2">
        <input
          :id="field.name"
          type="checkbox"
          :checked="state[field.name] === true || state[field.name] === 1 || state[field.name] === '1'"
          class="rounded border-gray-300 text-primary-600 focus:ring-primary-600"
          @change="(e) => setFieldValue(field.name, (e.target as HTMLInputElement).checked)"
        />
        <span class="text-sm font-medium text-gray-700">
          {{ field.label }}
          <span v-if="field.required" class="text-red-500">*</span>
        </span>
      </label>

      <!-- Select inputs -->
      <select
        v-else-if="field.type === 'select'"
        :id="field.name"
        :value="String(state[field.name] ?? '')"
        class="ui-input w-full"
        :class="{ 'border-red-500 bg-red-50': errors[field.name] }"
        @change="(e) => setFieldValue(field.name, (e.target as HTMLSelectElement).value)"
      >
        <option value="">Select {{ field.label }}</option>
        <option v-for="opt in field.options" :key="opt.value" :value="String(opt.value)">
          {{ opt.label }}
        </option>
      </select>

      <!-- Textarea inputs -->
      <textarea
        v-else-if="field.type === 'textarea'"
        :id="field.name"
        :value="String(state[field.name] ?? '')"
        :placeholder="field.placeholder"
        :maxlength="field.maxlength"
        rows="3"
        class="ui-input w-full"
        :class="{ 'border-red-500 bg-red-50': errors[field.name] }"
        @input="(e) => setFieldValue(field.name, (e.target as HTMLTextAreaElement).value)"
      />

      <!-- Error message -->
      <p v-if="errors[field.name]" class="text-xs text-red-600 mt-1">
        {{ errors[field.name] }}
      </p>
    </div>
    </div>

    <!-- Enter-to-submit needs a submit control inside the form; the visible
         footer buttons live outside it in the drawer chrome. -->
    <button type="submit" class="hidden" aria-hidden="true" tabindex="-1" />
  </form>
</template>

<script setup lang="ts">
import type { FieldConfig, FormState } from '../../Composables/useNestedDrawerForm'

defineProps({
  fields: {
    type: Array as () => FieldConfig[],
    required: true,
  },
  state: {
    type: Object as () => FormState,
    required: true,
  },
  errors: {
    type: Object as () => Record<string, string>,
    required: true,
  },
  serverError: {
    type: String as () => string | null,
    default: null,
  },
  saving: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits<{
  'update:field': [field: string, value: unknown]
  submit: []
}>()

function setFieldValue(field: string, value: unknown) {
  emit('update:field', field, value)
}
</script>
