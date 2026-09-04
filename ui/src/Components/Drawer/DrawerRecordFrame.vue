<template>
  <DrawerTabs
    v-if="(frame?.tabs ?? []).length > 0"
    :model-value="activeTab"
    :tabs="frame.tabs ?? []"
    :aria-label="ariaLabel"
    @update:model-value="(key: string) => emit('update:tab', key)"
  >
    <template v-for="tab in frame.tabs ?? []" #[tab.key]>
      <!--
        The details tab shows the record and, beneath it, the relation lists it
        names as sections — the common case needs no tab switching.
      -->
      <div v-if="tab.type === 'details'" :key="tab.key" class="space-y-6">
        <!--
          `include` is the tab's declared field list. Without it the grid falls
          back to every key the record carries, minus ids and collections.
        -->
        <DrawerFieldGrid :data="frame?.data ?? {}" :include="tab.fields ?? undefined" />

        <DrawerRelationList
          v-for="section in tab.sections ?? []"
          :key="section.key"
          :heading="section.label"
          :items="rowsFor(section)"
          :empty-text="section.empty ?? undefined"
          :addable="isAddable(section)"
          :add-label="section.addLabel ?? undefined"
          :href="rowHref(section)"
          :navigation="section.navigation ?? 'drawer'"
          bordered
          dense
          @add="emit('add', section)"
        >
          <template #row="{ item: row }">
            <span class="truncate text-sm text-gray-800">{{ primaryOf(row, section) }}</span>
            <span v-if="section.secondary" class="truncate text-xs text-gray-500">
              {{ row[section.secondary] ?? '—' }}
            </span>
          </template>
        </DrawerRelationList>
      </div>

      <!--
        A custom tab has no generic shape — the declaration owns its label and
        count, the body is the page's business (an upload dropzone is not a
        list of rows). The page supplies it through `#tab-{key}`; what follows
        is what a page that supplies nothing gets, so an undeclared body reads
        as "nothing here yet" rather than as a blank panel.
      -->
      <div v-else-if="tab.type === 'custom'" :key="tab.key">
        <slot
          :name="`tab-${tab.key}`"
          :tab="tab"
          :frame="frame"
          :rows="rowsFor(tab)"
        >
          <DrawerRelationList
            :heading="tab.label"
            :items="rowsFor(tab)"
            :empty-text="tab.empty ?? 'Nothing here yet.'"
            :href="rowHref(tab)"
            :navigation="tab.navigation ?? 'drawer'"
          >
            <template #row="{ item: row }">
              <div class="min-w-0">
                <div class="truncate text-sm text-gray-800">{{ primaryOf(row, tab) }}</div>
                <div v-if="tab.secondary" class="truncate text-xs text-gray-500">
                  {{ row[tab.secondary] ?? '—' }}
                </div>
              </div>
            </template>
          </DrawerRelationList>
        </slot>
      </div>

      <DrawerRelationList
        v-else
        :key="tab.key"
        :heading="tab.label"
        :items="rowsFor(tab)"
        :empty-text="tab.empty ?? undefined"
        :addable="isAddable(tab)"
        :add-label="tab.addLabel ?? undefined"
        :href="rowHref(tab)"
        :navigation="tab.navigation ?? 'drawer'"
        @add="emit('add', tab)"
      >
        <template #row="{ item: row }">
          <div class="min-w-0">
            <div class="truncate text-sm text-gray-800">{{ primaryOf(row, tab) }}</div>
            <div v-if="tab.secondary" class="truncate text-xs text-gray-500">
              {{ row[tab.secondary] ?? '—' }}
            </div>
          </div>
        </template>
      </DrawerRelationList>
    </template>
  </DrawerTabs>

  <!-- No tabs declared: the record as one grid, as a frame did before tabs. -->
  <DrawerFieldGrid v-else :data="frame?.data ?? {}" />
</template>

<script setup lang="ts">
import DrawerFieldGrid from './DrawerFieldGrid.vue'
import DrawerRelationList from './DrawerRelationList.vue'
import DrawerTabs from './DrawerTabs.vue'
import type { DrawerTab } from './drawerTabs'
import { resolveRecordUrl } from '../Table/tableSchema'

/**
 * Renders one drawer frame from the frame itself.
 *
 * A frame arrives self-describing: its `data`, and the `tabs` its resource
 * declared, with sections, field lists and record URLs already resolved. That
 * is everything needed to draw it — which is the point of this component.
 *
 * Before it existed, a page rendered frames through a slot named after the
 * frame's type (`#contact`, `#address`). That works while a page knows every
 * type it can show, and the bespoke pages do. It breaks the moment a frame
 * arrives for a resource the page has never heard of: no slot matches, and the
 * frame falls through to a raw dump of every key. A generated resource page
 * knows exactly one type — its own — so it could never stack a foreign record,
 * however well the server built the stack.
 *
 * Rendering from the frame removes that ceiling: a page can show any frame the
 * server pushes, and a nested route is free to stack a record of another type.
 * A page that wants a bespoke body for one type still declares a slot for it;
 * this is what the rest fall back to.
 */

/**
 * A tab as the server sends it: the package's DrawerTab (key, label, badge)
 * plus the relation/section detail DrawerTab::toArray() adds. Extended rather
 * than redeclared, so this cannot drift from what DrawerTabs itself accepts.
 */
interface RelationSection extends DrawerTab {
  type?: string
  // Nullable throughout: DrawerTab::toArray() emits null for anything the
  // resource did not declare, so the shape has to admit it rather than make
  // every caller normalise first.
  source?: string | null
  primary?: string | null
  secondary?: string | null
  empty?: string | null
  addable?: boolean
  addLabel?: string | null
  addForm?: unknown
  fields?: Record<string, string | null> | null
  sections?: RelationSection[]
  recordUrl?: string | null
  navigation?: 'drawer' | 'visit'
}

interface Frame {
  type?: string
  data?: Record<string, unknown>
  tabs?: RelationSection[]
}

const props = withDefaults(defineProps<{
  frame: Frame
  /** The tab to show; the first declared one when unset. */
  activeTab?: string
  ariaLabel?: string
  /**
   * Whether a list may offer its add action. Kept a prop rather than read from
   * the declaration so a page can withhold it — permission is the page's
   * business, not the frame's.
   */
  canAdd?: (section: RelationSection) => boolean
}>(), {
  activeTab: undefined,
  ariaLabel: 'Sections',
  canAdd: undefined,
})

const emit = defineEmits<{
  (e: 'update:tab', key: string): void
  (e: 'add', section: RelationSection): void
}>()

/** Rows come from the key the server named, not from the tab's own key. */
function rowsFor(section: RelationSection): Record<string, unknown>[] {
  const source = section.source ?? section.key
  const rows = (props.frame?.data ?? {})[source]

  return Array.isArray(rows) ? rows as Record<string, unknown>[] : []
}

function primaryOf(row: Record<string, unknown>, section: RelationSection): unknown {
  return row[section.primary ?? 'name'] ?? '—'
}

function isAddable(section: RelationSection): boolean {
  if (!section.addable) return false

  return props.canAdd ? props.canAdd(section) : true
}

/**
 * The per-row href DrawerRelationList wants, from the template the server sent.
 * Shared with the tables' resolver so a drawer row and a table row cannot
 * disagree about a record's URL; null when a token is missing, which leaves the
 * row inert rather than linking somewhere broken.
 */
function rowHref(section: RelationSection): ((row: Record<string, unknown>) => string) | undefined {
  if (!section.recordUrl) return undefined

  return (row: Record<string, unknown>) => resolveRecordUrl(section.recordUrl, row as never) ?? ''
}
</script>
