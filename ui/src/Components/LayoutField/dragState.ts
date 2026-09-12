import { shallowRef } from 'vue'
import type { BlockAddress } from './layoutModel'

export type DragPayload =
  | { kind: 'block'; field: string; from: BlockAddress }
  | { kind: 'row'; field: string; from: number }

export const dragging = shallowRef<DragPayload | null>(null)

export function startDrag(event: DragEvent, payload: DragPayload): void {
  dragging.value = payload
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', payload.kind)
  }
}

export function endDrag(): void {
  dragging.value = null
}

export function dropHalf(event: DragEvent, element: HTMLElement): 'before' | 'after' {
  const rect = element.getBoundingClientRect()
  return event.clientY < rect.top + rect.height / 2 ? 'before' : 'after'
}
