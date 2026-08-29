import { ref, watch, onMounted } from 'vue'
import type { Ref } from 'vue'

interface UseLocalStoragePersistenceOptions<T> {
  key: string
  defaultValue: T
  serialize?: (value: T) => string
  deserialize?: (value: string) => T
  onError?: (error: Error) => void
}

interface UseLocalStoragePersistenceReturn<T> {
  value: Ref<T>
  setValue: (newValue: T) => void
  removeValue: () => void
}

/**
 * Generic composable for persisting reactive values to localStorage with error handling
 */
export function useLocalStoragePersistence<T>({
  key,
  defaultValue,
  serialize = JSON.stringify,
  deserialize = JSON.parse,
  onError,
}: UseLocalStoragePersistenceOptions<T>): UseLocalStoragePersistenceReturn<T> {
  const value = ref(defaultValue) as Ref<T>

  const loadValue = () => {
    try {
      const stored = localStorage.getItem(key)
      if (stored !== null) {
        value.value = deserialize(stored)
      }
    } catch (error) {
      onError?.(error as Error)
      // Keep default value on error
    }
  }

  const saveValue = (newValue: T) => {
    try {
      localStorage.setItem(key, serialize(newValue))
    } catch (error) {
      onError?.(error as Error)
    }
  }

  const removeValue = () => {
    try {
      localStorage.removeItem(key)
    } catch (error) {
      onError?.(error as Error)
    }
  }

  const setValue = (newValue: T) => {
    value.value = newValue
  }

  // Load initial value
  onMounted(loadValue)

  // Watch for changes and persist
  watch(value, (newValue) => saveValue(newValue), { immediate: false })

  return {
    value,
    setValue,
    removeValue,
  }
}