import { computed, type ComputedRef } from 'vue'

export type FieldWidth = '1/4' | '1/3' | '1/2' | '2/3' | '3/4' | 'full'

const widthMap: Record<FieldWidth, string> = {
  '1/4': 'col-span-12 md:col-span-3',
  '1/3': 'col-span-12 md:col-span-4',
  '1/2': 'col-span-12 md:col-span-6',
  '2/3': 'col-span-12 md:col-span-8',
  '3/4': 'col-span-12 md:col-span-9',
  'full': 'col-span-12',
}

export function useFieldWidth(getWidth: () => string): ComputedRef<string> {
  return computed(() => widthMap[getWidth() as FieldWidth] ?? 'col-span-12')
}

export const fieldWidthProp = {
  width: {
    type: String,
    default: 'full',
    validator: (v: string) => ['1/4', '1/3', '1/2', '2/3', '3/4', 'full'].includes(v),
  },
} as const
