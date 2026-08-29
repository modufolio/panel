<template>
  <div class="ui-relation-manager">
    <!-- Relation Header -->
    <div class="ui-relation-header bg-white border-b border-gray-200 px-6 py-4">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
          <p v-if="description" class="mt-1 text-sm text-gray-600">{{ description }}</p>
        </div>

        <div class="flex items-center gap-3">
          <!-- Header Actions Slot -->
          <slot name="headerActions" :refresh="refresh" />

          <!-- Create Button -->
          <Action
            v-if="canCreate && !readonly"
            :label="createButtonLabel"
            color="primary"
            size="sm"
            @click="handleCreate"
          >
            <template #icon-before>
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
              </svg>
            </template>
          </Action>

          <!-- Attach Button (for many-to-many) -->
          <Action
            v-if="type === 'many-to-many' && !readonly"
            label="Attach"
            color="gray"
            variant="outlined"
            size="sm"
            @click="showAttachModal = true"
          >
            <template #icon-before>
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
              </svg>
            </template>
          </Action>
        </div>
      </div>
    </div>

    <!-- Relation Table -->
    <div class="ui-relation-table">
      <Table
        :columns="columns"
        :records="relationshipData.records.value"
        :loading="relationshipData.loading.value"
        :searchable="searchable"
        :search="relationshipData.searchTerm.value"
        :sticky-header="stickyHeader"
        :bulk-actions-enabled="bulkActionsEnabled && !readonly"
        @update:search="handleSearch"
        @sort="handleSort"
        :empty-state-title="emptyStateTitle"
        :empty-state-description="emptyStateDescription"
      >
        <!-- Pass through all column slots -->
        <template v-for="(_, slot) in $slots" #[slot]="scope">
          <slot :name="slot" v-bind="scope" />
        </template>

        <!-- Actions Column -->
        <template #actions="{ record }">
          <ActionGroup label="Actions">
            <ActionGroupItem
              v-if="canEdit"
              label="Edit"
              @click="handleEdit(record)"
            >
              <template #default>
                <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                </svg>
              </template>
            </ActionGroupItem>

            <ActionGroupItem
              v-if="type === 'many-to-many' && !readonly"
              label="Detach"
              color="danger"
              @click="handleDetach(record)"
            >
              <template #default>
                <svg class="w-5 h-5 text-danger-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                </svg>
              </template>
            </ActionGroupItem>

            <ActionGroupItem
              v-if="canDelete && type !== 'many-to-many'"
              label="Delete"
              color="danger"
              @click="handleDelete(record)"
            >
              <template #default>
                <svg class="w-5 h-5 text-danger-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                </svg>
              </template>
            </ActionGroupItem>

            <!-- Custom Actions Slot -->
            <slot name="recordActions" :record="record" />
          </ActionGroup>
        </template>

        <!-- Pagination -->
        <template #pagination>
          <TablePagination
            v-if="paginated && relationshipData.totalRecords.value > 0"
            :current-page="relationshipData.page.value"
            :last-page="relationshipData.lastPage.value"
            :total="relationshipData.totalRecords.value"
            :from="((relationshipData.page.value - 1) * perPage) + 1"
            :to="Math.min(relationshipData.page.value * perPage, relationshipData.totalRecords.value)"
            :per-page="perPage"
            @goto="handlePageChange"
          />
        </template>
      </Table>
    </div>

    <!-- Attach Modal (for many-to-many) -->
    <Dialog v-model:is-open="showAttachModal" title="Attach Record">
      <div class="p-6">
        <BelongsToSelect
          v-model="selectedAttachId"
          :label="`Select ${relationship}`"
          :endpoint="endpoint"
          :relationship="relationship"
          searchable
        />

        <div class="mt-6 flex justify-end gap-3">
          <Action
            label="Cancel"
            color="gray"
            variant="outlined"
            @click="showAttachModal = false"
          />
          <Action
            label="Attach"
            color="primary"
            :disabled="!selectedAttachId"
            @click="confirmAttach"
          />
        </div>
      </div>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, type PropType } from 'vue'
import { useRelationship } from '../Composables/useRelationship'
import Table from '../Table/Table.vue'
import TablePagination from '../Table/TablePagination.vue'
import Action from '../Actions/Action.vue'
import ActionGroup from '../Actions/ActionGroup.vue'
import ActionGroupItem from '../Actions/ActionGroupItem.vue'
import Dialog from '../Dialogs/Dialog.vue'
import BelongsToSelect from '../Fields/BelongsToSelect.vue'

const props = defineProps({
  /**
   * Relationship configuration
   */
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    default: '',
  },
  relationship: {
    type: String,
    required: true,
  },
  type: {
    type: String,
    required: true,
    validator: (value: unknown) => ['has-many', 'many-to-many', 'has-one'].includes(value as string),
  },
  resourceId: {
    type: [String, Number],
    required: true,
  },
  endpoint: {
    type: String,
    required: true,
  },

  /**
   * Table configuration
   */
  columns: {
    type: Array as PropType<any[]>,
    required: true,
  },
  searchable: {
    type: Boolean,
    default: true,
  },
  stickyHeader: {
    type: Boolean,
    default: false,
  },
  bulkActionsEnabled: {
    type: Boolean,
    default: false,
  },
  paginated: {
    type: Boolean,
    default: true,
  },
  perPage: {
    type: Number,
    default: 10,
  },

  /**
   * Action configuration
   */
  canCreate: {
    type: Boolean,
    default: true,
  },
  canEdit: {
    type: Boolean,
    default: true,
  },
  canDelete: {
    type: Boolean,
    default: true,
  },
  readonly: {
    type: Boolean,
    default: false,
  },
  createButtonLabel: {
    type: String,
    default: 'Create',
  },

  /**
   * Empty state
   */
  emptyStateTitle: {
    type: String,
    default: 'No records found',
  },
  emptyStateDescription: {
    type: String,
    default: 'Get started by creating a new record.',
  },
})

const emit = defineEmits(['create', 'edit', 'delete', 'attach', 'detach', 'refresh'])

const showAttachModal = ref(false)
const selectedAttachId = ref(null)

// Initialize relationship composable
const relationshipData = useRelationship({
  endpoint: props.endpoint,
  relationship: props.relationship,
  searchable: props.searchable,
  perPage: props.perPage,
})

// Fetch initial data
onMounted(async () => {
  await relationshipData.fetchRecords({
    filters: {
      [props.relationship + '_id']: props.resourceId,
    },
  })
})

// Event handlers
const handleCreate = () => {
  emit('create')
}

const handleEdit = (record: any) => {
  emit('edit', record)
}

const handleDelete = async (record: any) => {
  if (confirm('Are you sure you want to delete this record?')) {
    try {
      await relationshipData.deleteRecord(record.id)
      emit('delete', record)
      emit('refresh')
    } catch (error) {
      console.error('Error deleting record:', error)
    }
  }
}

const handleDetach = async (record: any) => {
  if (confirm('Are you sure you want to detach this record?')) {
    try {
      await relationshipData.detach(props.resourceId, record.id)
      emit('detach', record)
      emit('refresh')
    } catch (error) {
      console.error('Error detaching record:', error)
    }
  }
}

const confirmAttach = async () => {
  if (!selectedAttachId.value) return

  try {
    await relationshipData.attach(props.resourceId, selectedAttachId.value)
    showAttachModal.value = false
    selectedAttachId.value = null
    emit('attach', selectedAttachId.value)
    emit('refresh')
  } catch (error) {
    console.error('Error attaching record:', error)
  }
}

const handleSearch = (searchTerm: string) => {
  relationshipData.search(searchTerm)
}

const handleSort = ({ column, direction }: { column: string; direction: string }) => {
  relationshipData.fetchRecords({
    sort: direction === 'desc' ? `-${column}` : column,
  })
}

const handlePageChange = (page: number) => {
  relationshipData.goToPage(page)
}

const refresh = async () => {
  await relationshipData.fetchRecords()
  emit('refresh')
}

// Expose refresh method for parent components
defineExpose({
  refresh,
})
</script>