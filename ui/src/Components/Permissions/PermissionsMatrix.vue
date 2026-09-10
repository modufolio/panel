<template>
  <div class="ui-permissions-matrix">
    <p v-if="description" class="max-w-3xl text-sm text-ink-2">{{ description }}</p>

    <div class="mt-6 space-y-8">
      <Section
        v-for="resource in resources"
        :key="resource.key"
        :heading="resource.key"
        :description="`${resource.class} — ${resource.permissions}`"
        card
      >
        <template #headerActions>
          <Tag v-if="resource.overrides.scope" color="warning" dot>rows scoped</Tag>
        </template>

        <div class="overflow-x-auto">
          <Table :columns="matrixColumns(resource)" :records="matrixRows(resource)" :searchable="false" :sticky-header="false">
            <template v-for="column in matrixColumns(resource)" :key="column.key" #[`cell-${column.key}`]="{ value }">
              <span v-if="column.key === 'role'" class="font-mono text-xs">{{ value }}</span>
              <Tag v-else-if="value === 'partial'" color="warning">partial</Tag>
              <span v-else-if="value === '—'" class="text-ink-3">—</span>
              <!-- A refused route is neutral: most roles rightly reach few. A refusing hook is a decision. -->
              <BooleanColumn
                v-else
                :value="value === 'yes' || value === true"
                true-label="yes"
                false-label="no"
                :false-color="column.key.startsWith('can_') ? 'danger' : 'gray'"
                :show-label="false"
              />
            </template>
          </Table>
        </div>

        <div v-if="hasForm(resource)" class="mt-4 overflow-x-auto">
          <Table :columns="fieldColumns" :records="fieldRows(resource)" :searchable="false" :sticky-header="false">
            <template #cell-role="{ value }">
              <span class="font-mono text-xs">{{ value }}</span>
            </template>
            <template v-for="key in ['readable', 'readDenied', 'writeDenied']" :key="key" #[`cell-${key}`]="{ value }">
              <div class="flex flex-wrap gap-1">
                <Badge v-for="field in asList(value)" :key="field" :label="field" :color="badgeColor(key)" size="sm" />
                <span v-if="asList(value).length === 0" class="text-ink-3">—</span>
              </div>
            </template>
          </Table>
        </div>
      </Section>

      <Section heading="Divergences" description="Where one layer says yes and another says no, or a check cannot be answered for the type." card>
        <p v-if="report.notes.length === 0" class="text-sm text-ink-3">None: every layer agrees for every role.</p>
        <ul v-else class="divide-y divide-line">
          <li v-for="(note, index) in report.notes" :key="index" class="flex items-start gap-3 py-2 text-sm">
            <Tag :color="noteColor(note.kind)" class="shrink-0">{{ note.kind.replace(/_/g, ' ') }}</Tag>
            <span class="text-ink-2">{{ note.message }}</span>
          </li>
        </ul>
      </Section>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * The permission inspector, rendered: for every panel resource, what each role
 * may do — which routes admit it, what the resource's own hooks answer, and
 * which form fields it may read and write — plus the places two layers
 * disagree.
 *
 * Pure rendering over `PermissionReport::toArray()`. The report itself is
 * computed server-side by `Inspection\PermissionInspector`, from four things
 * only the application knows (its routes, its resource factory, its roles, and
 * what a user carrying one role looks like) — which is why this component
 * takes the finished report rather than asking for it.
 */
import { computed, type PropType } from 'vue'
import Badge from '../Core/Badge.vue'
import Tag from '../Core/Tag.vue'
import BooleanColumn from '../Columns/BooleanColumn.vue'
import Section from '../Sections/Section.vue'
import Table from '../Table/Table.vue'

interface RoleVerdict {
  routes: Record<string, boolean>
  can: { view: boolean; create: boolean; edit: boolean; delete: boolean }
  fields: { readable: string[]; readDenied: string[]; writeDenied: string[] }
}

/** Which hooks the resource's Permissions class actually overrides. */
type Hook = 'view' | 'create' | 'edit' | 'delete'
type Override = Hook | 'scope' | 'readable' | 'writable' | 'move'

interface ResourceEntry {
  key: string
  class: string
  /** The Permissions class answering for this resource. */
  permissions: string
  prefix: string | null
  routes: string[]
  overrides: Record<Override, boolean>
  roles: Record<string, RoleVerdict>
}

interface Note {
  kind: string
  resource: string
  role: string | null
  message: string
}

export interface PermissionReport {
  roles: string[]
  resources: Record<string, ResourceEntry>
  notes: Note[]
}

const props = defineProps({
  report: { type: Object as PropType<PermissionReport>, required: true },
  /**
   * The lead paragraph. Defaulted rather than left to each host, because what
   * the matrix means is not obvious from the grid alone; pass null to render
   * the sections only.
   */
  description: {
    type: String as PropType<string | null>,
    default: 'What each role may do on every panel resource: which routes admit it, '
      + "what the resource's own hooks answer, and which form fields it may read and write. "
      + 'Verdicts are for the resource type; a hook marked * is overridden and may answer '
      + 'differently per record.',
  },
})

const resources = computed(() => Object.values(props.report.resources))

/** Route-name suffixes per operation, mirroring PermissionInspector::OPERATIONS. */
const operations: Record<string, string[]> = {
  index: ['', '_export'],
  create: ['_create', '_store'],
  edit: ['_edit', '_update', '_relation_options', '_relation_create', '_relation_store'],
  delete: ['_delete_preview', '_bulk_destroy', '_destroy'],
  show: ['_show'],
}

const hooks: Hook[] = ['view', 'create', 'edit', 'delete']

function matrixColumns(resource: ResourceEntry) {
  return [
    { key: 'role', label: 'Role' },
    ...Object.keys(operations).map((operation) => ({ key: `op_${operation}`, label: `${operation} routes` })),
    // The star marks a hook the resource overrides — the report keys those by
    // the method's own name, so `view`, not `canView`.
    ...hooks.map((hook) => ({
      key: `can_${hook}`,
      label: `${hook}()${resource.overrides[hook] ? ' *' : ''}`,
    })),
  ]
}

function operationCell(resource: ResourceEntry, verdict: RoleVerdict, suffixes: string[]): string {
  const names = suffixes.map((suffix) => resource.key + suffix).filter((name) => resource.routes.includes(name))

  if (names.length === 0) return '—'

  const granted = names.filter((name) => verdict.routes[name]).length

  return granted === 0 ? 'no' : granted === names.length ? 'yes' : 'partial'
}

function matrixRows(resource: ResourceEntry): Record<string, unknown>[] {
  return props.report.roles.map((role) => {
    const verdict = resource.roles[role]
    const row: Record<string, unknown> = { id: role, role }

    for (const [operation, suffixes] of Object.entries(operations)) {
      row[`op_${operation}`] = verdict ? operationCell(resource, verdict, suffixes) : '—'
    }

    for (const hook of hooks) {
      row[`can_${hook}`] = verdict ? verdict.can[hook] : false
    }

    return row
  })
}

const fieldColumns = [
  { key: 'role', label: 'Role' },
  { key: 'readable', label: 'Readable' },
  { key: 'readDenied', label: 'Read denied' },
  { key: 'writeDenied', label: 'Write denied' },
]

function fieldRows(resource: ResourceEntry): Record<string, unknown>[] {
  return props.report.roles.map((role) => ({ id: role, role, ...(resource.roles[role]?.fields ?? {}) }))
}

function hasForm(resource: ResourceEntry): boolean {
  return Object.values(resource.roles).some(
    (verdict) => verdict.fields.readable.length > 0 || verdict.fields.readDenied.length > 0,
  )
}

function asList(value: unknown): string[] {
  return Array.isArray(value) ? value.map(String) : []
}

function badgeColor(kind: string): 'gray' | 'danger' | 'warning' {
  switch (kind) {
    case 'readDenied':
      return 'danger'
    case 'writeDenied':
      return 'warning'
    default:
      return 'gray'
  }
}

function noteColor(kind: string): 'gray' | 'danger' | 'warning' | 'info' {
  switch (kind) {
    case 'route_admits_hook_denies':
    case 'hierarchy_divergence':
      return 'danger'
    case 'unguarded':
    case 'access_threw':
      return 'warning'
    default:
      return 'info'
  }
}
</script>
