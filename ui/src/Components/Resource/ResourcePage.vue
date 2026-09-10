<template>
  <div>
    <PageHeader :title="title">
      <template #actions>
        <!--
          Which shape of this listing to show. The choice reaches the server as
          `?view=`, because a board is a different query and not a different
          renderer over the table's rows — the client cannot switch to one on
          its own.
        -->
        <ViewSwitcher
          v-if="(resource.views?.length ?? 0) > 1"
          :views="resource.views ?? []"
          :active="resource.view ?? 'table'"
          :aria-label="`${title} view`"
          @select="selectView"
        />
        <Action
          v-if="resource.canCreate"
          :label="`New ${singularLabel}`"
          color="primary"
          icon="plus"
          @click="router.visit(createUrl)"
        />
      </template>
    </PageHeader>

    <!--
      A board reads its columns from the server, which grouped them; the table
      is what every other view falls back to. Only one is ever mounted — the
      server sends the rows for whichever it served.
    -->
    <!--
      A refused move, stated where the drag happened. The board has already
      been re-read by then, so the card is back where the server says it is —
      this says why it went back.
    -->
    <p
      v-if="moveError"
      class="mb-3 rounded-md border border-warning/30 bg-warning-surface px-4 py-3 text-sm text-warning-on-surface"
      role="status"
    >
      {{ moveError }}
    </p>

    <!--
      Numbers about the resource, above whichever shape of it is showing. The
      server computed them; this only lays them out.
    -->
    <MetricRow :metrics="metrics" />

    <BoardView
      v-if="board"
      :view="board.view"
      :columns="board.columns"
      :can-move="resource.canMove === true"
      :can-create="resource.canCreate === true"
      @open="openCard"
      @add="router.visit(createUrl)"
      @move="moveCard"
    />

    <SchemaTable
      v-else
      :schema="table"
      :records="records.data"
      :summaries="records.meta?.summaries ?? {}"
      :can="records.meta?.can ?? {}"
      :why="records.meta?.why ?? {}"
      :search="form.search ?? undefined"
      :sort-column="computedSortColumn ?? undefined"
      :sort-direction="computedSortDirection"
      :query-params="computedParams"
      :visible-columns="visibleColumns"
      :stack="stack"
      :drawer-type="resource.drawerType"
      :filter-values="form"
      :cell-handlers="cellHandlers"
      @update:search="updateSearch"
      @sort="handleSort"
      @update:filter="setFilter"
    >
      <!--
        A `#cell-{key}` slot overrides one generated cell, so an avatar or a
        status pill needs no hand-written page; every other slot is a drawer
        tab body and goes to the frame below.
      -->
      <template v-for="name in cellSlots" :key="name" #[name]="slotProps">
        <slot :name="name" v-bind="slotProps" />
      </template>

      <!-- Which columns to show, and exporting. Exporting goes to the server,
           which re-runs this page's own query: the file is what the filters
           currently match, not the loaded page. -->
      <template #headerActions="{ selectedRecords }">
        <!--
          A filter set someone named. Beside the column toggle because it is
          the same kind of thing — how this viewer wants to look at the list —
          and remembered in the same place.
        -->
        <SavedViews
          :views="savedViews"
          :active="activeView"
          @apply="applyView"
          @save="saveView"
          @delete="deleteView"
        />
        <ColumnToggle v-model="visibleColumns" :columns="table.columns" />
        <ExportButton
          v-if="resource.exportUrl"
          :data="records.data"
          :columns="table.columns"
          :filename="resource.key"
          :title="title"
          :export-url="resource.exportUrl"
          :selected-records="selectedRecords"
        />
      </template>

      <!--
        No #actions or #bulkActions slot: both are declared by the resource
        and gated server-side by ResourceListing against what this viewer may
        actually do. What used to be twenty lines of markup here is now the
        absence of it.
      -->

      <template #pagination>
        <TablePagination
          v-if="records.meta"
          :current-page="records.meta.current_page"
          :last-page="records.meta.last_page"
          :total="records.meta.total"
          :from="records.meta.from"
          :to="records.meta.to"
          :per-page="records.meta.per_page"
          @goto="goToPage"
          @update:per-page="updatePerPage"
        />
      </template>
    </SchemaTable>

    <!--
      The drawer frame is built server-side by ResourceController from the
      resource's drawerType/drawerTitle/presentOne hooks; the slot name is
      dynamic so one page serves every generated resource. The body is a plain
      definition list over whatever presentOne() returned — a resource wanting
      a designed detail view writes its own page instead.
    -->
    <!--
      `add` is handled here for every frame in the stack, not only for this
      resource's own: the list names its endpoint, so extending a stacked
      record of another resource works from the same form.
    -->
    <DrawerStack
      :stack="stack"
      :base-url="resource.baseUrl"
      width="md"
      :overlays="addForm ? 1 : 0"
      @add="(section, item) => openAddForm(item, section)"
      @pick-image="(field, item) => { imagePicker = { field, item } }"
    >
      <!--
        Tabs when the resource declares them (PanelResource::drawerTabs()),
        the plain grid when it does not — a resource with no child collections
        needs nothing more, and that is the shape every generated drawer had
        before tabs existed.
      -->
      <!--
        The resource's own record, rendered from the frame itself:
        DrawerRecordFrame draws whatever tabs, sections, field lists and record
        links the server declared, so this page carries no knowledge of them.
      -->
      <template #[resource.drawerType]="{ item }">
        <DrawerRecordFrame
          :frame="item"
          :active-tab="activeTab(item)"
          :aria-label="`${singularLabel} sections`"
          @update:tab="(key) => (drawerTab = key)"
          @add="(section) => openAddForm(item, section)"
          @pick-image="(field) => { imagePicker = { field, item } }"
        >
          <!--
            Forward this page's own slots, so an application can give a custom
            tab a body (`#tab-files`) without giving up the generated page for
            a hand-written one.
          -->
          <template v-for="name in frameSlots" :key="name" #[name]="slotProps">
            <slot :name="name" v-bind="slotProps" />
          </template>
        </DrawerRecordFrame>
      </template>


      <template v-if="resource.canEdit" #[`footer-${resource.drawerType}`]="{ item }">
        <div v-if="item.can?.edit ?? true" class="flex justify-end gap-3">
          <Action
            :label="`Edit ${singularLabel}`"
            color="primary"
            @click="router.visit(editUrl(item))"
          />
        </div>
      </template>
    </DrawerStack>

    <!--
      Adding one row to a list, over the drawer rather than instead of it:
      reading a record and extending one of its lists is a single task, and
      navigating away to the full form loses the reader's place. The fields
      come from the resource's own form declaration, so this asks exactly what
      editing the same row would.
    -->
    <Teleport v-if="addForm" to="body">
      <!--
        `data-overlay-backdrop` keeps this out of the inert pass the panel's
        own layer applies to everything beside it — a scrim that is inert
        swallows the press it exists to receive, which is why clicking the
        dimmed page did nothing.
      -->
      <div
        class="fixed inset-0 z-[60] bg-overlay"
        data-overlay-backdrop
        data-testid="add-panel-scrim"
        @click="dismissEverything"
      />

      <!--
        As wide as the drawers it stands on (`max-w-xl` is the stack's `md`),
        so the panel that adds to a list reads as the next panel in the stack
        rather than as a narrower thing pasted over it. `:overlays` above
        makes the drawers shift left for it, too.
      -->
      <div
        ref="addPanelRef"
        role="dialog"
        aria-modal="true"
        class="fixed inset-y-0 right-0 z-[61] flex w-full max-w-xl flex-col bg-surface shadow-2xl"
        data-testid="add-panel"
      >
        <!-- Close on the left, as every drawer in the stack has it. -->
        <div class="flex items-center gap-3 border-b border-line px-6 py-4">
          <button
            type="button"
            class="shrink-0 rounded-lg p-1.5 text-ink-3 hover:bg-hover hover:text-ink-2"
            aria-label="Close"
            @click="closeAddForm"
          >
            <Icon name="x" class="h-5 w-5" />
          </button>
          <h2 class="truncate text-lg font-semibold text-ink">{{ addForm.tab.addLabel?.replace(/^\+\s*/, '') || 'Add' }} {{ addForm.tab.label }}</h2>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
          <p v-if="addErrors._" class="mb-4 rounded-md border border-danger/30 bg-danger-surface px-4 py-3 text-sm text-danger-on-surface">
            {{ addErrors._ }}
          </p>

          <BlueprintForm
            v-model="addValues"
            :fields="addForm.fields"
            :errors="addErrors"
            :card="false"
          />
        </div>

        <div class="border-t border-line bg-surface-sunken px-6 py-4">
          <div class="flex justify-end gap-3">
            <Action label="Cancel" color="gray" variant="outlined" @click="closeAddForm" />
            <Action
              :label="addSaving ? 'Saving…' : 'Save'"
              color="primary"
              :disabled="addSaving"
              @click="submitAddForm"
            />
          </div>
        </div>
      </div>
    </Teleport>

    <!--
      Picking an image for a `pickable` drawer field (a cover with no value
      yet) — over the drawer rather than by leaving it for the full edit
      form, the same reasoning as the add-a-row panel above.
    -->
    <MediaPickerDialog
      :is-open="imagePicker !== null"
      @close="imagePicker = null"
      @select="onImageSelected"
    />
  </div>
</template>

<script setup lang="ts">
/**
 * A PanelResource's listing, rendered from the props ResourceListing sends:
 * the table (or board) with its filters, columns and export, the create
 * action, the drawer stack with each record's tabs and lists, and the
 * over-drawer form that adds one row to a list.
 *
 * The rows arrive under the resource's own key, so a host page hands its
 * props straight through — `<ResourcePage v-bind="$attrs" />` — and this
 * reads them from $attrs like `useResourceListing` does. What a host still
 * owns is the page chrome around it: layout, `<Head>`, anything above.
 *
 * Slots: `#cell-{key}` replaces one table cell; any other slot is forwarded
 * to the drawer frame as a tab body (`#tab-files`).
 */
import { computed, ref, useSlots, type PropType } from 'vue'
import { router } from '@inertiajs/vue3'
import { useResourceListing, fillId, type ResourceMeta } from '../../Composables/useResourceListing'
import { useSavedViews } from './useSavedViews'
import { useInlineCellPatch } from './useInlineCellPatch'
import { useBoardMove } from './useBoardMove'
import { useDrawerAddForm } from './useDrawerAddForm'
import { useImagePicker } from './useImagePicker'
import type { TableSchema } from '../Table/tableSchema'
import type { BoardCard, BoardPayload } from '../Board/boardTypes'
import type { StackItem } from '../Drawer/useDrawerStack'
import type { Metric } from '../Metrics/metrics'
import MetricRow from '../Metrics/MetricRow.vue'
import SavedViews from './SavedViews.vue'
import SchemaTable from '../Table/SchemaTable.vue'
import TablePagination from '../Table/TablePagination.vue'
import ColumnToggle from '../Table/ColumnToggle.vue'
import ExportButton from '../Table/ExportButton.vue'
import Action from '../Actions/Action.vue'
import PageHeader from '../Layout/PageHeader.vue'
import DrawerStack from '../Drawer/DrawerStack.vue'
import DrawerRecordFrame from '../Drawer/DrawerRecordFrame.vue'
import BlueprintForm from '../Fields/BlueprintForm.vue'
import Icon from '../Core/Icon.vue'
import BoardView from '../Board/BoardView.vue'
import ViewSwitcher from '../Board/ViewSwitcher.vue'
import MediaPickerDialog from '../Media/MediaPickerDialog.vue'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  filters: Object,
  /** Self-description from ResourceListing — see its `resource` prop. */
  resource: { type: Object as PropType<ResourceMeta>, required: true },
  table: { type: Object as PropType<TableSchema>, required: true },
  stack: { type: Array as PropType<StackItem[]>, default: () => [] },
  /** Present only while a board view is the active one. */
  board: { type: Object as PropType<BoardPayload | undefined>, default: undefined },
  /**
   * Server-computed numbers about the resource, from its `metrics()`. Absent
   * when it declares none, which is every resource until it says otherwise.
   */
  metrics: { type: Array as PropType<Metric[]>, default: () => [] },
})

/**
 * Every derived value this page needs, and the same one a graduated page gets:
 * a resource overriding indexComponent() calls useResourceListing() too, and
 * writes only the markup that differs. See docs/graduating-a-resource.md.
 */
const {
  records,
  title,
  singularLabel,
  visibleColumns,
  drawerTab,
  drawerStack,
  form,
  computedSortColumn,
  computedSortDirection,
  computedParams,
  reset,
  updateSearch,
  handleSort,
  goToPage,
  updatePerPage,
  setFilter,
} = useResourceListing(props)

const { savedViews, activeView, applyView, saveView, deleteView } = useSavedViews({
  resourceKey: () => props.resource.key,
  form,
  reset,
  computedParams,
  visibleColumns,
})

const { cellHandlers } = useInlineCellPatch({
  table: () => props.table,
  patchTemplate: () => props.resource.urls?.patch,
  computedParams,
  records,
})

/** Which of this page's slots override a table cell, and which dress a drawer tab. */
const slots = useSlots()
const cellSlots = computed(() => Object.keys(slots).filter((name) => name.startsWith('cell-')))
const frameSlots = computed(() => Object.keys(slots).filter((name) => !name.startsWith('cell-')))

/**
 * Where things are, as the server says. `resource.urls` names every generated
 * route; the `baseUrl` arithmetic remains only for a listing rendered by a
 * host that predates it.
 */
const indexUrl = computed(() => props.resource.urls?.index ?? props.resource.baseUrl)
const createUrl = computed(() => props.resource.urls?.create ?? `${props.resource.baseUrl}/create`)

function editUrl(item: StackItem): string {
  return item.urls?.edit
    ?? fillId(props.resource.urls?.edit, item.data.id)
    ?? `${props.resource.baseUrl}/${String(item.data.id)}/edit`
}

/**
 * Switch view. The key travels as `?view=`, keeping the current filters and
 * search — changing how you look at a set should not change which set.
 *
 * A full visit rather than a partial reload: the table and the board are
 * different props entirely, and asking for one while holding the other's is
 * how a board ends up rendered over a table's rows.
 */
function selectView(key: string): void {
  const current = computedParams.value as Record<string, string>

  // The default view is the ABSENCE of the parameter, so a URL shared from the
  // table does not carry a redundant `?view=table`.
  const isDefault = key === (props.resource.views?.[0]?.key ?? 'table')
  const params: Record<string, string> = isDefault ? { ...current } : { ...current, view: key }

  router.visit(`${indexUrl.value}?${new URLSearchParams(params)}`, {
    preserveScroll: true,
  })
}

/** A card opens the same drawer its table row would. */
function openCard(card: BoardCard): void {
  // Through the stack rather than a bare visit, so the card opens the same
  // drawer its table row would — and keeps the listing's filters, which is
  // what makes the drawer's next/previous arrows traverse the same set.
  drawerStack.pushWithParams(String(card.id), computedParams.value as Record<string, string>)
}

const { moveError, moveCard } = useBoardMove({
  baseUrl: () => props.resource.baseUrl,
  viewKey: () => props.board?.view.key,
})

/** The open tab, falling back to the first the resource declares. */
function activeTab(item: StackItem): string {
  const tabs = item.tabs ?? []

  if (drawerTab.value && tabs.some((tab) => tab.key === drawerTab.value)) {
    return drawerTab.value
  }

  return tabs[0]?.key ?? 'details'
}

/**
 * The add panel's element, registered by the composable as the topmost overlay
 * layer while the form is open. Without it the drawer beneath stays topmost,
 * so Escape closed the drawer out from under an open form.
 */
const addPanelRef = ref<HTMLElement | null>(null)

const {
  addForm,
  addValues,
  addErrors,
  addSaving,
  openAddForm,
  closeAddForm,
  submitAddForm,
} = useDrawerAddForm({ editUrl, panel: addPanelRef })

const { imagePicker, onImageSelected } = useImagePicker()

/**
 * A press on the dimmed page puts the whole stack away, form included — the
 * dimmed page is what is left of the listing, and pressing it means "back to
 * that". Closing only the form left the user pressing the same grey twice to
 * get out of three panels.
 */
function dismissEverything(): void {
  closeAddForm()
  drawerStack.closeAll()
}

</script>
