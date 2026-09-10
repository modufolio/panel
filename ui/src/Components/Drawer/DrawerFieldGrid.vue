<template>
  <dl class="ui-drawer-field-grid grid gap-4" :class="gridClass">
    <div
      v-for="field in resolvedFields"
      :key="field.key"
      :class="[
        field.wide ? spanClass : undefined,
        field.rows ? ROW_SPAN_CLASSES[field.rows] : undefined,
        field.rows ? 'flex flex-col' : undefined,
      ]"
    >
      <!-- A break between runs of fields: nothing to label, nothing to show. -->
      <div
        v-if="field.separator"
        class="ui-drawer-field-separator"
        :class="field.separator === 'line' ? 'border-t border-line' : 'h-2'"
        role="separator"
        aria-hidden="true"
      />
      <template v-else>
      <dt class="text-sm font-medium text-ink-3">{{ field.label }}</dt>
      <dd
        class="mt-1 text-sm text-ink whitespace-pre-line"
        :class="field.rows ? 'relative flex-1 min-h-0' : undefined"
      >
        <slot :name="`field-${field.key}`" :field="field" :value="field.raw">
          <!--
            A spanning image fills the height of the rows it claims and stays
            square, cropped rather than stretched. Positioned absolutely so
            the rows are sized by the fields beside it, not by the image: the
            square follows the neighbours' height instead of pushing them.
            With no cover to show, the square stays as a blank, so the layout
            reads the same for every record.
          -->
          <template v-if="field.rows">
            <button
              v-if="field.image && field.pickUrl"
              type="button"
              class="group absolute inset-y-0 left-0 h-full max-w-full aspect-square overflow-hidden rounded-lg ring-1 ring-line hover:ring-2 hover:ring-primary transition-all"
              :aria-label="`Choose ${field.label}`"
              @click="emit('pick-image', field)"
            >
              <img :src="field.image" :alt="field.label" class="h-full w-full object-cover" />
            </button>
            <img
              v-else-if="field.image"
              :src="field.image"
              :alt="field.label"
              class="absolute inset-y-0 left-0 h-full max-w-full aspect-square rounded-lg object-cover bg-surface-sunken"
            />
            <button
              v-else-if="field.pickUrl"
              type="button"
              class="group absolute inset-y-0 left-0 h-full max-w-full aspect-square flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-line-strong bg-surface-sunken hover:border-primary transition-colors"
              :aria-label="`Choose ${field.label}`"
              @click="emit('pick-image', field)"
            >
              <Icon name="photo" class="h-8 w-8 text-ink-3 group-hover:text-ink-2" />
              <span class="text-sm text-ink-3 group-hover:text-ink-2">{{ field.pickLabel ?? 'Choose image' }}</span>
            </button>
            <div
              v-else
              class="absolute inset-y-0 left-0 h-full max-w-full aspect-square rounded-lg bg-surface-sunken"
              aria-hidden="true"
            />
          </template>
          <button
            v-else-if="field.image && field.pickUrl"
            type="button"
            class="group w-24 aspect-square overflow-hidden rounded-lg ring-1 ring-line hover:ring-2 hover:ring-primary transition-all"
            :aria-label="`Choose ${field.label}`"
            @click="emit('pick-image', field)"
          >
            <img :src="field.image" :alt="field.label" class="h-full w-full object-cover" />
          </button>
          <img
            v-else-if="field.image"
            :src="field.image"
            :alt="field.label"
            class="w-24 aspect-square rounded-lg object-cover bg-surface-sunken"
          />
          <button
            v-else-if="field.pickUrl"
            type="button"
            class="group w-24 aspect-square flex flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-line-strong bg-surface-sunken hover:border-primary transition-colors"
            :aria-label="`Choose ${field.label}`"
            @click="emit('pick-image', field)"
          >
            <Icon name="photo" class="h-6 w-6 text-ink-3 group-hover:text-ink-2" />
            <span class="text-xs text-ink-3 group-hover:text-ink-2">{{ field.pickLabel ?? 'Choose image' }}</span>
          </button>
          <!--
            A reference the presenter gave an `href` — another record worth
            opening. DrawerLink stacks it over this one rather than navigating
            away, so the field reads like the table column that points at the
            same record.
          -->
          <DrawerLink
            v-else-if="field.href"
            :href="field.href"
            :navigation="field.navigation ?? 'drawer'"
          >{{ field.value }}</DrawerLink>

          <!--
            A hex literal is a colour: show the colour, and keep the literal
            beside it for anyone who needs to copy it.
          -->
          <span v-else-if="field.color" class="inline-flex items-center gap-2">
            <span
              class="h-4 w-4 shrink-0 rounded ring-1 ring-inset ring-line"
              :style="{ backgroundColor: field.color }"
              aria-hidden="true"
            />
            <span class="font-mono text-xs uppercase">{{ field.value }}</span>
          </span>

          <template v-else>{{ field.value }}</template>
        </slot>
      </dd>
      </template>
    </div>
  </dl>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { titleLabel } from '../../Utils/labels'
import DrawerLink from './DrawerLink.vue'
import Icon from '../Core/Icon.vue'
import { formatDate, hasTimeOfDay, parseTimestamp } from '../../Utils/dates'

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
  include?: Record<string, string | null | { separator: 'line' | 'space' } | { label?: string | null; wide?: boolean; rows?: number; pickUrl?: string | null; pickTarget?: string | null; pickLabel?: string | null }>
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

const emit = defineEmits<{ (e: 'pick-image', field: DrawerField): void }>()

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

/** Rows a field may span; one row is the default and has no class. */
const ROW_SPAN_CLASSES: Record<number, string> = {
  2: 'row-span-2',
  3: 'row-span-3',
  4: 'row-span-4',
}

const gridClass = computed(() => GRID_CLASSES[props.columns] ?? GRID_CLASSES[2])
const spanClass = computed(() => SPAN_CLASSES[props.columns] ?? SPAN_CLASSES[2])

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

/**
 * Where a relation reference points, when the presenter says so.
 *
 * Deliberately `href` and not `url`: imageUrl() above treats an object
 * carrying `url` or `thumbnail_url` as a MEDIA reference and renders an
 * <img>, so a link named `url` would render the organization as a broken
 * image. Two shapes, two keys.
 */
function referenceHref(value: unknown): string | undefined {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) {
    return undefined
  }

  const href = (value as Record<string, unknown>).href

  return typeof href === 'string' && href !== '' ? href : undefined
}

/**
 * The readable label of a relation reference — a presenter emitting the whole
 * related record (`{ id, name, ... }`) rather than a scalar.
 *
 * Without this such a value reached `String(value)` and rendered as
 * `[object Object]`: the grid knew about media references and about scalars,
 * and a relation is neither. Tried in the order a presenter is likely to name
 * the human-facing field.
 */
function referenceLabel(value: unknown): string | undefined {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) {
    return undefined
  }

  const record = value as Record<string, unknown>

  for (const key of ['name', 'title', 'label', 'display_name', 'full_name']) {
    const candidate = record[key]

    if (typeof candidate === 'string' && candidate !== '') {
      return candidate
    }
  }

  // An object with no obvious label reads better as "nothing to show" than as
  // the object's string coercion.
  return undefined
}

/** `#6366f1`, `#63f`, or either with an alpha pair. Nothing else. */
function hexColour(value: unknown): string | undefined {
  return typeof value === 'string' && /^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(value)
    ? value
    : undefined
}

/**
 * A timestamp as a person reads it. The server sends ISO-8601, which is a
 * transport format — `2026-09-08T07:26:29+00:00` in a drawer is a machine
 * talking. The date column formats the same way, from the same helper.
 */
function readableDate(value: unknown): string | undefined {
  if (typeof value !== 'string') {
    return undefined
  }

  const date = parseTimestamp(value)

  return date === null
    ? undefined
    : formatDate(date, hasTimeOfDay(value) ? 'MMM D, YYYY HH:mm' : 'MMM D, YYYY')
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
    const declared = props.include?.[key]

    if (typeof declared === 'object' && declared !== null && 'separator' in declared) {
      return { key, label: '', value: '', wide: true, separator: declared.separator }
    }

    // A label with a width, from a form that laid this field out full-row.
    const declaredLabel = typeof declared === 'object' && declared !== null ? declared.label : declared
    const declaredWide = typeof declared === 'object' && declared !== null ? declared.wide === true : false
    const declaredRows = typeof declared === 'object' && declared !== null && typeof declared.rows === 'number' && declared.rows in ROW_SPAN_CLASSES
      ? declared.rows
      : undefined
    const declaredPickUrl = typeof declared === 'object' && declared !== null ? declared.pickUrl : undefined
    const declaredPickTarget = typeof declared === 'object' && declared !== null ? declared.pickTarget : undefined
    const declaredPickLabel = typeof declared === 'object' && declared !== null ? declared.pickLabel : undefined

    const image = imageUrl(value)
    const reference = image ? undefined : referenceLabel(value)
    const isEmptyObject = !image
      && reference === undefined
      && typeof value === 'object'
      && value !== null
      && !Array.isArray(value)

    const colour = image ? undefined : hexColour(value)

    const text = value === null || value === undefined || value === '' || isEmptyObject
      ? '—'
      : (reference ?? readableDate(value) ?? String(value))

    const href = image ? undefined : referenceHref(value)
    const navigation = href !== undefined
      ? ((value as Record<string, unknown>).navigation as 'drawer' | 'visit' | undefined)
      : undefined

    return {
      key,
      label: (typeof declaredLabel === 'string' ? declaredLabel : undefined) ?? titleLabel(key),
      value: image ? '' : text,
      wide: declaredWide || (!image && !href && text.length > props.wideThreshold),
      rows: declaredRows,
      raw: value,
      image,
      color: colour,
      href,
      navigation,
      pickUrl: declaredPickUrl,
      pickTarget: declaredPickTarget,
      pickLabel: declaredPickLabel,
    }
  })
})
</script>
