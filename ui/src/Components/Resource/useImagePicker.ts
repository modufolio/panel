import { ref, type Ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { apiFetch } from '../../Utils/apiFetch'
import type { DrawerField } from '../Drawer/drawerFieldGrid'
import type { StackItem } from '../Drawer/useDrawerStack'
import type { MediaItem } from '../Media/MediaPickerDialog.vue'

/** Which field is being set, on which record. */
export interface OpenImagePicker {
  field: DrawerField
  item: StackItem
}

/**
 * Setting one of a record's image fields from the media library, over the
 * drawer rather than by navigating away: picking a cover should not mean
 * leaving the record to reach the full edit form.
 *
 * The server owns the record's shape, so a successful pick re-reads the frame
 * rather than patching a second copy of it here.
 */
export function useImagePicker(): {
  imagePicker: Ref<OpenImagePicker | null>
  onImageSelected: (image: MediaItem) => Promise<void>
} {
  const imagePicker = ref<OpenImagePicker | null>(null)

  async function onImageSelected(image: MediaItem): Promise<void> {
    const open = imagePicker.value
    imagePicker.value = null

    if (open === null || !open.field.pickUrl || !open.field.pickTarget) {
      return
    }

    try {
      await apiFetch(open.field.pickUrl, {
        method: 'POST',
        body: { [open.field.pickTarget]: image.id },
      })

      router.reload()
    } catch (error) {
      console.error(error)
    }
  }

  return { imagePicker, onImageSelected }
}
