<template>
  <div class="ui-wizard">
    <!-- Wizard Header/Steps -->
    <ol role="list" class="ui-wizard-header border-b border-gray-200 mb-8">
      <div class="flex items-center justify-between">
        <li
          v-for="(step, index) in steps"
          :key="index"
          class="ui-wizard-header-step relative"
          :class="{
            'ui-active': currentStep === index,
            'ui-completed': currentStep > index,
            'flex-1': index < steps.length - 1,
          }"
        >
          <button
            type="button"
            class="ui-wizard-header-step-btn group flex items-center w-full"
            :disabled="!isStepAccessible(index) || step.disabled"
            @click="goToStep(index)"
            :aria-current="currentStep === index ? 'step' : undefined"
          >
            <div class="flex items-center">
              <div
                class="ui-wizard-header-step-icon-ctn flex items-center justify-center w-10 h-10 rounded-full border-2 transition-colors"
                :class="stepIconClasses(index)"
              >
                <!-- Checkmark for completed steps -->
                <svg
                  v-if="currentStep > index"
                  class="w-6 h-6"
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="m4.5 12.75 6 6 9-13.5"
                  />
                </svg>

                <!-- Step number for current/future steps -->
                <span
                  v-else
                  class="ui-wizard-header-step-number text-sm font-semibold"
                >
                  {{ String(index + 1).padStart(2, '0') }}
                </span>
              </div>

              <div class="ui-wizard-header-step-text ml-4 min-w-0 flex flex-col items-start">
                <span
                  class="ui-wizard-header-step-label text-sm font-medium transition-colors"
                  :class="stepLabelClasses(index)"
                >
                  {{ step.label }}
                </span>
                <span
                  v-if="step.description"
                  class="ui-wizard-header-step-description text-xs text-gray-500"
                >
                  {{ step.description }}
                </span>
              </div>
            </div>
          </button>

          <!-- Separator -->
          <svg
            v-if="index < steps.length - 1"
            class="ui-wizard-header-step-separator absolute top-5 -right-5 w-10 h-0.5 text-gray-300"
            viewBox="0 0 40 2"
            fill="none"
            preserveAspectRatio="none"
            aria-hidden="true"
          >
            <path
              d="M0 1h40"
              vector-effect="non-scaling-stroke"
              stroke="currentcolor"
              stroke-linejoin="round"
            />
          </svg>
        </li>
      </div>
    </ol>

    <!-- Wizard Content -->
    <div class="ui-wizard-content">
      <div
        v-for="(_step, index) in steps"
        :key="index"
        v-show="currentStep === index"
      >
        <slot :name="`step-${index}`" :current-step="currentStep" :step-index="index" />
      </div>
    </div>

    <!-- Wizard Footer/Actions -->
    <div class="ui-wizard-footer flex items-center justify-between mt-8 pt-6 border-t border-gray-200">
      <div>
        <!-- Back Button -->
        <Action
          v-if="!isFirstStep"
          label="Back"
          color="gray"
          variant="outlined"
          @click="goToPreviousStep"
        />

        <!-- Cancel Button for first step -->
        <Action
          v-else-if="showCancel"
          :label="cancelLabel"
          color="gray"
          variant="outlined"
          @click="handleCancel"
        />
      </div>

      <div class="flex items-center gap-3">
        <!-- Next Button -->
        <Action
          v-if="!isLastStep"
          label="Next"
          color="primary"
          :disabled="submitting"
          @click="goToNextStep"
        />

        <!-- Submit Button for last step -->
        <Action
          v-else
          :label="submitLabel"
          color="primary"
          :disabled="submitting"
          @click="handleSubmit"
        >
          <template v-if="submitting" #icon-before>
            <svg
              class="animate-spin -ml-1 mr-2 h-4 w-4"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
              ></circle>
              <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
              ></path>
            </svg>
          </template>
        </Action>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, type PropType } from 'vue'
import Action from '../Actions/Action.vue'

interface WizardStep {
  label: string
  description?: string
  disabled?: boolean
}

const props = defineProps({
  steps: {
    type: Array as PropType<WizardStep[]>,
    required: true,
    validator: (steps: unknown) => Array.isArray(steps) && steps.every((step: Partial<WizardStep>) => step.label),
  },
  modelValue: {
    type: Number,
    default: 0,
  },
  skippable: {
    type: Boolean,
    default: false,
  },
  submitting: {
    type: Boolean,
    default: false,
  },
  submitLabel: {
    type: String,
    default: 'Create',
  },
  showCancel: {
    type: Boolean,
    default: true,
  },
  cancelLabel: {
    type: String,
    default: 'Cancel',
  },
})

const emit = defineEmits(['update:modelValue', 'submit', 'cancel', 'step-change'])

const currentStep = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

const isFirstStep = computed(() => currentStep.value === 0)
const isLastStep = computed(() => currentStep.value === props.steps.length - 1)

function isStepAccessible(stepIndex: number) {
  if (props.skippable) {
    return true
  }
  // Can only access current step or previous steps
  return stepIndex <= currentStep.value
}

function goToStep(stepIndex: number) {
  if (!isStepAccessible(stepIndex) || props.steps[stepIndex]?.disabled) {
    return
  }

  const previousStep = currentStep.value
  currentStep.value = stepIndex
  emit('step-change', { from: previousStep, to: stepIndex })
}

function goToNextStep() {
  if (!isLastStep.value) {
    const previousStep = currentStep.value
    currentStep.value = currentStep.value + 1
    emit('step-change', { from: previousStep, to: currentStep.value })
  }
}

function goToPreviousStep() {
  if (!isFirstStep.value) {
    goToStep(currentStep.value - 1)
  }
}

function handleSubmit() {
  emit('submit')
}

function handleCancel() {
  emit('cancel')
}

function stepIconClasses(index: number) {
  if (currentStep.value > index) {
    // Completed step
    return 'border-primary-600 bg-primary-600 text-white'
  } else if (currentStep.value === index) {
    // Active step
    return 'border-primary-600 bg-white text-primary-600'
  } else {
    // Future step
    return 'border-gray-300 bg-white text-gray-500'
  }
}

function stepLabelClasses(index: number) {
  if (currentStep.value >= index) {
    return 'text-gray-900'
  } else {
    return 'text-gray-500'
  }
}
</script>