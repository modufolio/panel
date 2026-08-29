<template>
  <dl class="ui-drawer-field-grid grid gap-4" :class="gridClass">
    <div
      v-for="field in resolvedFields"
      :key="field.key"
      :class="field.wide ? spanClass : undefined"
    >
      <dt class="text-sm font-medium text-gray-500">{{ field.label }}</dt>
      <dd class="mt-1 text-sm text-gray-900 whitespace-pre-line">
        <slot :name="`field-${field.key}`" :field="field" :value="field.raw">
          <img
            v-if="field.image"
            :src="field.image"
            :alt="field.label"
            class="w-24 aspect-square rounded-lg object-cover bg-gray-100"
          />
          <template v-else>{{ field.value }}</template>
        </slot>
      </dd>
    </div>
  </dl>
</template>

<script setup lang="ts">
import { computed } from 'vue'

/**
 * The two-column definition grid every drawer's "details" view is built from.
 *
 * Two ways to feed it, deliberately:
 *
 * - `fields` — an explicit list, for a bespoke drawer that chooses its own
 *   labels, order and formatting.
 * - `data` — a raw presented record, for the generated drawer that has no
 *   declaration to read. The derivation rules live here so a generated and a
 *   hand-written drawer cannot drift apart: identity and foreign keys are
 *   dropped (`id`, `*_id`), arrays are dropped (child collections have no
 *   honest rendering in a flat list — they belong in a DrawerRelationList),
 *   blanks render as an em dash, long prose takes the full width rather than
 *   a squeezed column, and a value shaped `{ thumbnail_url, url }` (the
 *   convention presenters already use for a media reference) renders as a
 *   thumbnail instead of being stringified into "[object Object]".
 *
 * Per-field slots (`#field-{key}`) override the rendering of any one value,
 * which is what lets a drawer keep this grid while showing, say, a status
 * pill or a link for one entry.
 */

import type { DrawerField } from './drawerFieldGrid'

const props = withDefaults(defineProps<{
  fields?: DrawerField[]
  data?: Record<string, unknown>
  columns?: number
  /** Keys never shown when deriving from `data`; merged with the built-ins. */
  exclude?: string[]
  /**
   * Which keys to show and in what order, as `{ key: labelOrNull }`. Given,
   * only these are shown — that is what lets one record be split across two
   * grids. Omitted, every eligible key is shown, in the record's own order.
   */
  include?: Record<string, string | null>
  /** Characters after which a derived value claims the full row. */
  wideThreshold?: number
}>(), {
  fields: undefined,
  data: undefined,
  columns: 2,
  exclude: () => [],
  include: undefined,
  wideThreshold: 60,
})

/**
 * Written out rather than interpolated: Tailwind scans source files for
 * literal class names, so `grid-cols-${n}` would never be generated.
 */
const GRID_CLASSES: Record<number, string> = {
  1: 'grid-cols-1',
  2: 'grid-cols-2',
  3: 'grid-cols-3',
  4: 'grid-cols-4',
}

const SPAN_CLASSES: Record<number, string> = {
  1: 'col-span-1',
  2: 'col-span-2',
  3: 'col-span-3',
  4: 'col-span-4',
}

const gridClass = computed(() => GRID_CLASSES[props.columns] ?? GRID_CLASSES[2])
const spanClass = computed(() => SPAN_CLASSES[props.columns] ?? SPAN_CLASSES[2])

/** 'postal_code' → 'Postal Code' */
function humanize(key: string): string {
  return key
    .replace(/[_-]+/g, ' ')
    .replace(/\b\w/g, (character) => character.toUpperCase())
}

/** A plain object shaped like a presenter's media reference, not an array. */
function imageUrl(value: unknown): string | undefined {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) {
    return undefined
  }

  const { thumbnail_url: thumbnailUrl, url } = value as Record<string, unknown>

  return (typeof thumbnailUrl === 'string' && thumbnailUrl)
    || (typeof url === 'string' && url)
    || undefined
}

const resolvedFields = computed<DrawerField[]>(() => {
  if (props.fields !== undefined) {
    return props.fields
  }

  const data = props.data ?? {}

  // A declared list picks the keys and their order; without one, the record's
  // own order stands and only the never-shown keys are dropped. An image
  // reference is a plain object, not an array, so it survives this filter
  // and is picked up by imageUrl() below.
  const entries: Array<[string, unknown]> = props.include
    ? Object.keys(props.include).map((key) => [key, data[key]])
    : Object.entries(data).filter(([key, value]) => (
      key !== 'id'
      && !key.endsWith('_id')
      && !Array.isArray(value)
      && !props.exclude.includes(key)
    ))

  return entries.map(([key, value]) => {
    const image = imageUrl(value)
    const text = value === null || value === undefined || value === ''
      ? '—'
      : String(value)

    return {
      key,
      label: props.include?.[key] ?? humanize(key),
      value: image ? '' : text,
      wide: !image && text.length > props.wideThreshold,
      raw: value,
      image,
    }
  })
})
</script>
