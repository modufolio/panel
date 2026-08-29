<template>
  <Section :heading="heading" :description="description" :card="card">
    <template v-if="$slots.headerActions" #headerActions>
      <slot name="headerActions" />
    </template>

    <form @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Form Grid -->
      <div class="ui-form-grid grid gap-6" :class="gridClasses">
        <slot />
      </div>

      <!-- Form Actions -->
      <div v-if="$slots.actions || showDefaultActions" class="ui-form-actions flex items-center justify-end gap-3 pt-6">
        <slot name="actions">
          <Action
            v-if="showCancel"
            :label="cancelLabel"
            color="gray"
            variant="outlined"
            @click="handleCancel"
          />
          <Action
            type="submit"
            :label="submitLabel"
            color="primary"
            :disabled="submitting"
          >
            <template v-if="submitting" #icon-before>
              <svg class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </template>
          </Action>
        </slot>
      </div>
    </form>

    <template v-if="$slots.footer" #footer>
      <slot name="footer" />
    </template>
  </Section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Section from './Section.vue'
import Action from '../Actions/Action.vue'

const props = defineProps({
  heading: {
    type: String,
    default: '',
  },
  description: {
    type: String,
    default: '',
  },
  card: {
    type: Boolean,
    default: true,
  },
  columns: {
    type: Number,
    default: 1,
    validator: (value: unknown) => [1, 2, 3, 4].includes(value as number),
  },
  submitting: {
    type: Boolean,
    default: false,
  },
  showDefaultActions: {
    type: Boolean,
    default: true,
  },
  showCancel: {
    type: Boolean,
    default: false,
  },
  submitLabel: {
    type: String,
    default: 'Save',
  },
  cancelLabel: {
    type: String,
    default: 'Cancel',
  },
})

const emit = defineEmits(['submit', 'cancel'])

const gridClasses = computed(() => {
  const cols: Record<number, string> = {
    1: 'grid-cols-1',
    2: 'md:grid-cols-2',
    3: 'md:grid-cols-3',
    4: 'md:grid-cols-4',
  }
  return cols[props.columns]
})

function handleSubmit() {
  emit('submit')
}

function handleCancel() {
  emit('cancel')
}
</script>
