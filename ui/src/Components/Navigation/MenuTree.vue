<template>
  <div class="space-y-1">
    <template v-for="(node, index) in tree" :key="node.id">

      <!-- Insertion line above -->
      <div
        v-show="dropState.targetIndex === index && dropState.targetParent === null"
        class="h-0.5 rounded-full bg-primary pointer-events-none mx-1"
      />

      <div
        @dragover.prevent.stop="onDragOver(index, null, $event)"
        @dragleave.stop="onDragLeave"
        @drop.prevent.stop="onDrop"
      >
        <MenuItemRow
          :item="node"
          :can-move-up="index > 0"
          :can-move-down="index < tree.length - 1"
          @dragstart="onDragStart(node, $event)"
          @dragend="onDragEnd"
          @toggle-visibility="$emit('toggle-visibility', $event)"
          @remove="$emit('remove', $event)"
          @update-label="(id, label) => $emit('update-label', id, label)"
          @move-up="moveWithinSiblings(tree, index, -1)"
          @move-down="moveWithinSiblings(tree, index, 1)"
        />
      </div>

      <!-- Children (max depth 1) -->
      <div v-if="node.children?.length" class="ml-6 space-y-1">
        <template v-for="(child, ci) in node.children" :key="child.id">

          <div
            v-show="dropState.targetIndex === ci && dropState.targetParent === node.id"
            class="h-0.5 rounded-full bg-primary pointer-events-none mx-1"
          />

          <div
            @dragover.prevent.stop="onDragOver(ci, node.id, $event)"
            @dragleave.stop="onDragLeave"
            @drop.prevent.stop="onDrop"
          >
            <MenuItemRow
              :item="child"
              :can-move-up="ci > 0"
              :can-move-down="ci < node.children.length - 1"
              @dragstart="onDragStart(child, $event)"
              @dragend="onDragEnd"
              @toggle-visibility="$emit('toggle-visibility', $event)"
              @remove="$emit('remove', $event)"
              @update-label="(id, label) => $emit('update-label', id, label)"
              @move-up="moveWithinSiblings(node.children, ci, -1)"
              @move-down="moveWithinSiblings(node.children, ci, 1)"
            />
          </div>

        </template>

        <!-- Insertion after last child -->
        <div
          v-show="dropState.targetIndex === node.children.length && dropState.targetParent === node.id"
          class="h-0.5 rounded-full bg-primary pointer-events-none mx-1"
        />

        <!-- Drop zone for after last child -->
        <div
          class="h-2"
          @dragover.prevent.stop="onDragOver(node.children.length, node.id, $event)"
          @dragleave.stop="onDragLeave"
          @drop.prevent.stop="onDrop"
        />
      </div>

      <!-- Drop zone to nest into this root node -->
      <div
        v-if="!node.children?.length || node.children.length === 0"
        class="ml-6 h-4 flex items-center border border-dashed rounded transition-colors"
        :class="dropState.targetParent === node.id
          ? 'border-primary bg-primary-surface'
          : 'border-line text-transparent'"
        @dragover.prevent.stop="onDragOver(0, node.id, $event)"
        @dragleave.stop="onDragLeave"
        @drop.prevent.stop="onDrop"
      >
        <span v-if="dropState.targetParent === node.id" class="w-full text-center text-[10px] font-medium text-primary">
          Nest under "{{ node.label }}"
        </span>
      </div>

    </template>

    <!-- Insertion after last root -->
    <div
      v-show="dropState.targetIndex === tree.length && dropState.targetParent === null"
      class="h-0.5 rounded-full bg-primary pointer-events-none mx-1"
    />

    <div
      class="h-4"
      @dragover.prevent.stop="onDragOver(tree.length, null, $event)"
      @dragleave.stop="onDragLeave"
      @drop.prevent.stop="onDrop"
    />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import MenuItemRow from './MenuItemRow.vue'
import type { NavigationTreeNode } from '../../Composables/useNavigation'

defineProps<{
  tree: NavigationTreeNode[]
}>()

const emit = defineEmits<{
  move: [payload: { id: number, newParentId: number | null, newPosition: number }]
  'toggle-visibility': [id: number]
  remove: [id: number]
  'update-label': [id: number, label: string]
}>()

const dragging = ref<NavigationTreeNode | null>(null)
const dropState = ref<{ targetIndex: number | null, targetParent: number | null }>({
  targetIndex: null,
  targetParent: null,
})

const onDragStart = (node: NavigationTreeNode, event: DragEvent) => {
  dragging.value = node
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
}

const onDragEnd = () => {
  dragging.value = null
  dropState.value = { targetIndex: null, targetParent: null }
}

const onDragOver = (index: number, parentId: number | null, event: DragEvent) => {
  if (!dragging.value) return
  // Prevent nesting children into other children (max depth = 1)
  if (parentId !== null && dragging.value.children?.length > 0) return
  dropState.value = { targetIndex: index, targetParent: parentId }
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'
}

const onDragLeave = () => {
  // Small delay to avoid flickering when moving between zones
}

const onDrop = () => {
  if (!dragging.value) return
  const { targetIndex, targetParent } = dropState.value
  if (targetIndex === null) return

  emit('move', {
    id: dragging.value.id,
    newParentId: targetParent,
    newPosition: targetIndex,
  })

  onDragEnd()
}

// Keyboard-operable reorder: swap with the sibling at index ± 1, staying
// within the same parent. `siblings` is either the root list or one node's
// `children` — both are ordered the way the tree renders them, so the
// sibling at `index + direction` is exactly what "up"/"down" means on screen.
const moveWithinSiblings = (siblings: NavigationTreeNode[], index: number, direction: -1 | 1) => {
  const target = index + direction
  if (target < 0 || target >= siblings.length) return

  const node = siblings[index]
  const parentId = node.parent_id ?? null

  emit('move', { id: node.id, newParentId: parentId, newPosition: target })
}
</script>
