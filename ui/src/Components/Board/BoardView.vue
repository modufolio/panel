<template>
  <div class="ui-board flex-1 overflow-x-auto">
    <div class="flex gap-4 pb-2" style="width: max-content; min-width: 100%;">
      <section
        v-for="column in columns"
        :key="column.value"
        class="flex w-80 shrink-0 flex-col"
        :aria-label="column.label"
      >
        <header class="mb-3 flex items-center gap-2 px-1">
          <span
            v-if="column.color"
            class="h-2.5 w-2.5 rounded-full"
            :style="{ backgroundColor: column.color }"
          />
          <span class="text-sm font-semibold text-gray-700">{{ column.label }}</span>
          <span class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600">
            {{ column.total }}
          </span>
        </header>

        <div class="flex min-h-[200px] flex-1 flex-col gap-2 rounded-xl bg-gray-100/70 p-2">
          <Draggable
            :list="lists[column.value]"
            :group="groupName"
            :disabled="!canMove"
            item-key="id"
            ghost-class="ui-board-ghost"
            class="flex min-h-[60px] flex-col gap-2"
            @change="(event: DragChange) => onChange(column.value, event)"
          >
            <template #item="{ element }">
              <article
                class="group rounded-lg bg-white p-3.5 shadow-sm ring-1 ring-gray-200 transition-shadow hover:ring-primary-400"
                :class="canMove ? 'cursor-grab active:cursor-grabbing' : 'cursor-pointer'"
                @click="$emit('open', element)"
              >
                <slot name="card" :card="element" :column="column">
                  <p class="text-sm font-medium leading-snug text-gray-800">
                    {{ title(element) }}
                  </p>

                  <dl v-if="view.cardFields.length" class="mt-2 flex flex-wrap gap-x-3 gap-y-1">
                    <div
                      v-for="field in view.cardFields"
                      :key="field"
                      class="text-xs text-gray-400"
                    >
                      <dd>{{ display(element[field]) }}</dd>
                    </div>
                  </dl>

                  <!--
                    One button per column this card may move to. The server
                    asked its own rule for each, so an offered button is a move
                    that will be accepted — and dragging is still there for
                    everything else. `.stop` because the card itself opens.
                  -->
                  <div
                    v-if="canMove && targetsFor(column, element).length"
                    class="mt-2.5 flex flex-wrap justify-end gap-1"
                  >
                    <button
                      v-for="target in targetsFor(column, element)"
                      :key="target.value"
                      type="button"
                      class="rounded-md border border-gray-300 px-2 py-0.5 text-xs font-medium text-gray-600 transition-colors hover:border-primary-400 hover:bg-primary-50 hover:text-primary-700"
                      @click.stop="$emit('move', { card: element, column: target.value, after: null, before: null })"
                    >
                      {{ target.label }}
                    </button>
                  </div>
                </slot>
              </article>
            </template>
          </Draggable>

          <!--
            The count is the column's true total; the cards are one page of it.
            Saying so is the difference between "that is everything" and "that
            is what fits" — the same reason the relation picker states its cap.
          -->
          <p
            v-if="column.total > (lists[column.value]?.length ?? 0)"
            class="px-1 py-1 text-xs text-gray-400"
          >
            Showing {{ lists[column.value]?.length ?? 0 }} of {{ column.total }}.
          </p>

          <button
            v-if="canCreate"
            type="button"
            class="rounded-lg px-2 py-1.5 text-left text-sm text-gray-500 transition-colors hover:bg-gray-200/60 hover:text-gray-700"
            @click="$emit('add', column)"
          >
            + Add
          </button>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, watch } from 'vue'
import Draggable from 'vuedraggable'
import type { BoardCard, BoardColumn, BoardMoveTarget, BoardViewSpec } from './boardTypes'

/**
 * A resource's records as cards in columns.
 *
 * The board renders what the server grouped; it derives no structure of its
 * own. In particular it never computes a card's new position — it reports
 * which column the card landed in and which cards it landed between, and the
 * server decides what that works out to. Two people dragging into the same
 * gap at the same moment is precisely the case a client-side position would
 * get wrong, because only the server sees both.
 */

interface DragChange {
  added?: { element: BoardCard; newIndex: number }
  moved?: { element: BoardCard; newIndex: number }
  removed?: { element: BoardCard; oldIndex: number }
}

const props = withDefaults(defineProps<{
  view: BoardViewSpec
  columns: BoardColumn[]
  canMove?: boolean
  canCreate?: boolean
}>(), {
  canMove: true,
  canCreate: false,
})

const emit = defineEmits<{
  open: [card: BoardCard]
  add: [column: BoardColumn]
  /**
   * A card was dropped. `after`/`before` are the ids it landed between, either
   * of which is null at an end of the column.
   */
  move: [payload: { card: BoardCard; column: string; after: string | null; before: string | null }]
}>()

/**
 * Draggable mutates the array it is given, so each column gets its own local
 * copy. Rebuilt whenever the server sends new columns — a reload, a filter, a
 * rejected move being put back.
 */
const lists = reactive<Record<string, BoardCard[]>>({})

watch(
  () => props.columns,
  (columns) => {
    for (const key of Object.keys(lists)) delete lists[key]
    for (const column of columns) lists[column.value] = [...column.cards]
  },
  { immediate: true, deep: true },
)

/**
 * One drag group per board, so cards cannot be dropped onto a different
 * board's columns when two are on one page. Sorting inside a column is offered
 * only when the view declares somewhere to record the result.
 */
const groupName = { name: `board-${props.view.key}`, pull: true, put: true }

function onChange(column: string, event: DragChange): void {
  const change = event.added ?? event.moved

  // `removed` is the other half of a cross-column drag; the receiving column
  // reports it as `added`, and handling both would send the move twice.
  if (!change) return

  if (event.moved && !props.view.sortable) return

  const list = lists[column] ?? []
  const index = change.newIndex

  emit('move', {
    card: change.element,
    column,
    after: idOf(list[index - 1]),
    before: idOf(list[index + 1]),
  })
}

/**
 * The columns this card may move to, as the server computed them.
 *
 * Read from the column the card is currently rendered in rather than from a
 * lookup across the board: a card only ever appears once, and after a drag the
 * server re-sends the whole board, so the entry follows the card.
 */
function targetsFor(column: BoardColumn, card: BoardCard): BoardMoveTarget[] {
  return column.moves?.[String(card.id)] ?? []
}

function idOf(card: BoardCard | undefined): string | null {
  return card === undefined ? null : String(card.id)
}

function title(card: BoardCard): string {
  const key = props.view.cardTitle

  if (key !== null && typeof card[key] === 'string') {
    return card[key] as string
  }

  // No declared title: the first string the card carries reads better than the
  // record's identifier, which is what a raw dump would have shown.
  const first = Object.values(card).find((value) => typeof value === 'string' && value !== '')

  return typeof first === 'string' ? first : String(card.id)
}

function display(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—'

  return typeof value === 'object' ? JSON.stringify(value) : String(value)
}
</script>

<style scoped>
.ui-board-ghost {
  @apply opacity-40 ring-2 ring-dashed ring-primary-400;
}
</style>
