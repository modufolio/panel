<template>
  <div class="ui-field-file" :class="widthClass">
    <label
      v-if="label"
      :for="id"
      class="ui-field-label block text-sm font-medium text-gray-700 mb-1.5"
      :class="{ 'after:content-[\'*\'] after:ml-0.5 after:text-danger-600': required }"
    >
      {{ label }}
    </label>

    <div class="ui-field-wrapper">
      <!-- Drop Zone -->
      <div
        @drop.prevent="handleDrop"
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        :class="dropZoneClasses"
        class="ui-field-dropzone relative border-2 border-dashed rounded-lg p-6 text-center transition-colors"
      >
        <!-- Upload Icon & Text -->
        <div v-if="!preview && !file" class="space-y-2">
          <svg class="mx-auto w-12 h-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
          </svg>
          <div class="text-sm text-gray-600">
            <label :for="id" class="relative cursor-pointer rounded-md font-medium text-primary-600 hover:text-primary-500">
              <span>Upload a file</span>
              <input
                :id="id"
                ref="fileInput"
                type="file"
                :accept="accept"
                :multiple="multiple"
                :disabled="disabled"
                @change="handleFileSelect"
                class="sr-only"
              />
            </label>
            <span class="pl-1">or drag and drop</span>
          </div>
          <p v-if="help" class="text-xs text-gray-500">{{ help }}</p>
        </div>

        <!-- Image Preview -->
        <div v-if="preview" class="relative">
          <img :src="preview" :alt="file?.name" class="mx-auto max-h-48 rounded-lg" />
          <button
            type="button"
            @click="removeFile"
            class="absolute top-2 right-2 p-1 bg-danger-600 text-white rounded-full hover:bg-danger-700 transition-colors"
          >
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
          <p class="mt-2 text-sm text-gray-700 truncate">{{ file?.name }}</p>
          <p class="text-xs text-gray-500">{{ formatFileSize(file?.size) }}</p>
        </div>

        <!-- File Info (non-image) -->
        <div v-else-if="file" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
          <div class="flex items-center gap-3">
            <svg class="w-8 h-8 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
            </svg>
            <div class="text-left">
              <p class="text-sm font-medium text-gray-900 truncate max-w-xs">{{ file.name }}</p>
              <p class="text-xs text-gray-500">{{ formatFileSize(file.size) }}</p>
            </div>
          </div>
          <button
            type="button"
            @click="removeFile"
            class="p-1 text-gray-400 hover:text-danger-600 transition-colors"
          >
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Error Message -->
    <p v-if="error" class="ui-field-error mt-1.5 text-sm text-danger-600">
      {{ error }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useId } from '../../Primitives/useId'
import { useFieldWidth, fieldWidthProp } from './useFieldWidth'

const props = defineProps({
  ...fieldWidthProp,
  modelValue: {
    type: [File, String, null],
    default: null,
  },
  id: {
    type: String,
    default: () => useId(undefined, 'field'),
  },
  label: {
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
  accept: {
    type: String,
    default: '',
  },
  multiple: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  required: {
    type: Boolean,
    default: false,
  },
  maxSize: {
    type: Number,
    default: 10 * 1024 * 1024, // 10MB default
  },
})

const emit = defineEmits(['update:modelValue'])

const widthClass = useFieldWidth(() => props.width)

const fileInput = ref<HTMLInputElement | null>(null)
const file = ref<File | null>(null)
const preview = ref<string | null>(null)
const isDragging = ref(false)

const dropZoneClasses = computed(() => {
  const classes = []

  if (isDragging.value) {
    classes.push('border-primary-500 bg-primary-50')
  } else if (props.error) {
    classes.push('border-danger-300 bg-danger-50')
  } else {
    classes.push('border-gray-300 hover:border-gray-400')
  }

  if (props.disabled) {
    classes.push('opacity-50 cursor-not-allowed')
  } else {
    classes.push('cursor-pointer')
  }

  return classes
})

function handleFileSelect(event: Event): void {
  const target = event.target as HTMLInputElement
  const selectedFile = target.files?.[0]
  if (selectedFile) {
    processFile(selectedFile)
  }
}

function handleDrop(event: DragEvent): void {
  isDragging.value = false

  if (props.disabled) return

  const droppedFile = event.dataTransfer?.files[0]
  if (droppedFile) {
    processFile(droppedFile)
  }
}

function processFile(selectedFile: File) {
  // Check file size
  if (selectedFile.size > props.maxSize) {
    emit('update:modelValue', null)
    return
  }

  file.value = selectedFile
  emit('update:modelValue', selectedFile)

  // Create preview for images
  if (selectedFile.type.startsWith('image/')) {
    const reader = new FileReader()
    reader.onload = (e) => {
      preview.value = (e.target?.result as string) ?? null
    }
    reader.readAsDataURL(selectedFile)
  } else {
    preview.value = null
  }
}

function removeFile() {
  file.value = null
  preview.value = null
  emit('update:modelValue', null)

  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

function formatFileSize(bytes?: number) {
  if (!bytes) return '0 Bytes'

  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))

  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}

// Watch for external changes to modelValue
watch(() => props.modelValue, (newValue) => {
  if (!newValue) {
    removeFile()
  } else if (typeof newValue === 'string') {
    // If it's a URL, show it as preview
    preview.value = newValue
  }
})
</script>
