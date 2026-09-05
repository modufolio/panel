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
          @click="router.visit(`${resource.baseUrl}/create`)"
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
      class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
      role="status"
    >
      {{ moveError }}
    </p>

    <BoardView
      v-if="board"
      :view="board.view"
      :columns="board.columns"
      :can-move="resource.canMove === true"
      :can-create="resource.canCreate === true"
      @open="openCard"
      @add="router.visit(`${resource.baseUrl}/create`)"
      @move="moveCard"
    />

    <SchemaTable
      v-else
      :schema="table"
      :records="records.data"
      :summaries="records.meta?.summaries ?? {}"
      :search="form.search ?? undefined"
      :sort-column="computedSortColumn ?? undefined"
      :sort-direction="computedSortDirection"
      :query-params="computedParams"
      :visible-columns="visibleColumns"
      :stack="stack"
      :drawer-type="resource.drawerType"
      :filter-values="form"
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
        <ColumnToggle v-model="visibleColumns" :columns="table.columns" />
        <ExportButton
          v-if="resource.exportUrl"
          :data="records.data"
          :columns="table.columns"
          :filename="resource.key"
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
    <DrawerStack :stack="stack" :base-url="resource.baseUrl" width="md">
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
          :can-add="canAdd"
          @update:tab="(key) => (drawerTab = key)"
          @add="(section) => openAddForm(item, section)"
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
        <div class="flex justify-end gap-3">
          <Action
            :label="`Edit ${singularLabel}`"
            color="primary"
            @click="router.visit(`${resource.baseUrl}/${item.data.id}/edit`)"
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
      <div class="fixed inset-0 z-[60] bg-gray-900/25" @click="closeAddForm" />

      <div
        ref="addPanelRef"
        role="dialog"
        aria-modal="true"
        class="fixed inset-y-0 right-0 z-[61] flex w-full max-w-md flex-col bg-white shadow-lg"
      >
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
          <h2 class="text-lg font-semibold text-gray-900">{{ addForm.tab.addLabel?.replace(/^\+\s*/, '') || 'Add' }} {{ addForm.tab.label }}</h2>
          <button type="button" class="text-gray-400 hover:text-gray-600" @click="closeAddForm">
            <Icon name="x" class="h-5 w-5" />
          </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
          <p v-if="addErrors._" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ addErrors._ }}
          </p>

          <BlueprintForm
            v-model="addValues"
            :fields="addForm.fields"
            :errors="addErrors"
            :card="false"
          />
        </div>

        <div class="border-t border-gray-200 bg-gray-50 px-6 py-4">
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
import { getCsrfToken } from '../../Utils/csrf'
import { fieldsFromSpec, initialValues } from '../Fields/fieldsFromSpec'
import { useDismissableLayer } from '../../Primitives/useDismissableLayer'
import { useResourceListing, type ResourceMeta } from '../../Composables/useResourceListing'
import type { TableSchema } from '../Table/tableSchema'
import type { BoardCard, BoardPayload } from '../Board/boardTypes'
import type { StackItem } from '../Drawer/useDrawerStack'
import type { FieldDef } from '../Fields/useBlueprint'
import type { FieldSpec } from '../Fields/fieldsFromSpec'
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

defineOptions({ inheritAttrs: false })

const props = defineProps({
  filters: Object,
  /** Self-description from ResourceListing — see its `resource` prop. */
  resource: { type: Object as PropType<ResourceMeta>, required: true },
  table: { type: Object as PropType<TableSchema>, required: true },
  stack: { type: Array as PropType<StackItem[]>, default: () => [] },
  /** Present only while a board view is the active one. */
  board: { type: Object as PropType<BoardPayload | undefined>, default: undefined },
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
  updateSearch,
  handleSort,
  goToPage,
  updatePerPage,
  setFilter,
} = useResourceListing(props)

/** Which of this page's slots override a table cell, and which dress a drawer tab. */
const slots = useSlots()
const cellSlots = computed(() => Object.keys(slots).filter((name) => name.startsWith('cell-')))
const frameSlots = computed(() => Object.keys(slots).filter((name) => !name.startsWith('cell-')))

/** Why the last drag was put back, if it was. */
const moveError = ref<string | null>(null)

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

  router.visit(`${props.resource.baseUrl}?${new URLSearchParams(params)}`, {
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

/**
 * Persist one drag.
 *
 * The board reports where the card landed — the column, and the cards either
 * side of it — and never a position: two people can drop into the same gap at
 * the same instant, and only the server sees both. A refusal reloads, which
 * puts the card back where the server says it still is.
 */
async function moveCard(payload: {
  card: BoardCard
  column: string
  after: string | null
  before: string | null
}): Promise<void> {
  const url = `${props.resource.baseUrl}/${payload.card.id}/board-move`

  // A previous refusal describes a move that is over; this one starts clean.
  moveError.value = null

  try {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': getCsrfToken() ?? '',
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        view: props.board?.view.key,
        column: payload.column,
        after: payload.after,
        before: payload.before,
      }),
    })

    if (response.ok) {
      return
    }

    const body = await response.json().catch(() => null)
    moveError.value = body?.error ?? 'That move could not be saved.'
  } catch (error) {
    console.error(error)
    moveError.value = 'That move could not be saved.'
  }

  // Re-read rather than undo locally: the server is the only party that knows
  // where the card actually is now, and a hand-rolled undo would disagree with
  // it the moment someone else moved something too.
  router.reload({ only: ['board', 'flash', 'errors'] })
}

/** The open tab, falling back to the first the resource declares. */
function activeTab(item: StackItem): string {
  const tabs = item.tabs ?? []

  if (drawerTab.value && tabs.some((tab) => tab.key === drawerTab.value)) {
    return drawerTab.value
  }

  return tabs[0]?.key ?? 'details'
}

/**
 * Whether to offer the list's add action. The declaration asks for it; the
 * viewer's ability to edit the record decides — offering an action the server
 * would turn away is a broken promise, not a shortcut.
 */
function canAdd(tab: { addable?: boolean }): boolean {
  return tab.addable === true && props.resource.canEdit === true
}

/**
 * What the add form needs of the list it adds to. Structural on purpose: the
 * frame emits its own section type, the stack declares another, and both
 * carry these four.
 */
interface AddableTab {
  label: string
  addLabel?: string | null
  addFields?: FieldSpec[]
  addTarget?: string | null
}

/**
 * The open add-form, if any: which list is being added to, on which record.
 *
 * Adding happens in a drawer over the drawer rather than by navigating to the
 * full form — reading a record and extending one of its lists is one task, and
 * leaving the record to do it loses the place.
 */
const addForm = ref<{ tab: AddableTab; fields: FieldDef[]; recordId: string } | null>(null)
const addValues = ref<Record<string, unknown>>({})
const addErrors = ref<Record<string, string>>({})
const addSaving = ref(false)

function openAddForm(item: StackItem, tab: AddableTab): void {
  // Nothing to render a form from — fall back to the place that can edit it.
  if (!tab.addFields?.length) {
    router.visit(`${props.resource.baseUrl}/${String(item.data.id)}/edit`)
    return
  }

  // The declaration arrives with `rules` as the server's map; the blueprint
  // layer needs rule *functions*, and handing the raw map through throws the
  // moment a field validates. One conversion, shared with ResourceForm.
  const fields = fieldsFromSpec(tab.addFields)

  addValues.value = initialValues(fields)
  addErrors.value = {}
  addForm.value = { tab, fields, recordId: String(item.data.id) }
}

/**
 * Register the panel in the overlay layer stack. Without it the drawer beneath
 * stays the topmost layer, so Escape closed the drawer out from under an
 * open form — and the drawer's focus trap kept reaching into this panel.
 */
const addPanelRef = ref<HTMLElement | null>(null)

useDismissableLayer(() => addForm.value !== null, {
  elements: () => [addPanelRef.value],
  onDismiss: (reason) => { if (reason === 'escape') closeAddForm() },
  // The panel's own scrim already handles a press outside.
  dismissOnOutsidePointer: false,
  modalElement: () => addPanelRef.value,
})

function closeAddForm(): void {
  addForm.value = null
  addErrors.value = {}
  addSaving.value = false
}

async function submitAddForm(): Promise<void> {
  const open = addForm.value
  if (open === null || addSaving.value) {
    return
  }

  addSaving.value = true
  addErrors.value = {}

  // The server names the field to write to: a tab may *read* from a
  // display copy (`tag_list`) while the field that edits the relation is the
  // form's own (`tags`), and posting to the display key is a 404.
  const url = `${props.resource.baseUrl}/${open.recordId}/relations/${open.tab.addTarget}`

  try {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': getCsrfToken() ?? '',
      },
      credentials: 'same-origin',
      body: JSON.stringify(addValues.value),
    })

    if (response.status === 422) {
      const body = await response.json()
      addErrors.value = body?.errors ?? {}
      return
    }

    if (!response.ok) {
      addErrors.value = { _: `Could not save (status ${response.status}).` }
      return
    }

    closeAddForm()
    // The server owns the record's shape, so re-read the frame rather than
    // patching a second copy of it here.
    drawerStack.pushWithParams(open.recordId, computedParams.value as Record<string, string>)
  } catch (error) {
    console.error(error)
    addErrors.value = { _: 'Could not save.' }
  } finally {
    addSaving.value = false
  }
}
</script>
