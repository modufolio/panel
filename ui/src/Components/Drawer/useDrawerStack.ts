import { computed, type ComputedRef } from 'vue'
import { visitDrawer, withDrawerParams } from './visitDrawer'

/**
 * A section the server declared for this frame. Absent or empty means the
 * frame renders as one undivided body, which is what every frame did before
 * tabs existed.
 */
export interface StackItemTab {
  key: string
  label: string
  /** 'custom' bodies are rendered by the page, not the shared components. */
  type: 'details' | 'relation' | 'custom'
  /** Details tabs only: the relation lists rendered inline beneath the grid. */
  sections?: StackItemTab[]
  /**
   * Details tabs only: which record keys this grid shows and in what order,
   * as `key => label override`. Absent means every eligible key.
   */
  fields?: Record<string, string | null>
  /** Relation tabs only: whether a row drills into a frame or visits a page. */
  navigation?: 'drawer' | 'visit'
  /** Row list styling: one bordered list, or a card per row. */
  variant?: 'list' | 'cards'
  deletable?: boolean
  /** Row drill-down pattern over `{parent}` and `{id}`. */
  recordUrl?: string | null
  /** Relation tabs only: the key in `data` holding the rows. */
  source?: string
  primary?: string | null
  secondary?: string | null
  /** Short classifying value shown before the primary, in its own column. */
  category?: string | null
  empty?: string
  badge?: number | null
  addable?: boolean
  addLabel?: string
}

/**
 * One frame of the drawer stack, as the server builds it.
 *
 * The single definition: DrawerStack.vue used to declare its own copy, and the
 * two had already drifted (only one knew about record navigation). Slot types
 * come from here now, so a field the server adds is visible to every consumer.
 */
export interface StackItem {
  type: string
  data: Record<string, any>
  title?: string
  description?: string
  width?: string
  key?: string
  href?: string
  nextRecordUrl?: string
  previousRecordUrl?: string
  tabs?: StackItemTab[]
  /**
   * Which frame renders this item: the side panel a record is read in, or a
   * centred dialog for one decision or one short form.
   *
   * Absent means `drawer`, so every frame built before dialogs existed keeps
   * rendering exactly as it did.
   */
  presentation?: 'drawer' | 'dialog'
}

/**
 * Composable for URL-driven hierarchical drawer stack navigation.
 *
 * The stack is driven by the server via Inertia props — no client-side state management needed.
 * The URL represents the full navigation state, so browser back/forward works natively.
 *
 * All visits include the X-Inertia-Drawer-Stack header so the server can detect
 * drawer context for redirect interception (inspired by Tofandel/inertia-vue3-modal).
 *
 * Usage in a page component:
 *
 *   const props = defineProps({ stack: Array })
 *   const { stack, isOpen, push, pop, closeAll } = useDrawerStack(props, '/contacts')
 *
 * The server controller builds the stack array and passes it as an Inertia prop.
 * Each `push()` call navigates to a deeper URL; `pop()` goes back.
 */
export function useDrawerStack(
  props: { stack?: StackItem[] },
  baseUrl: string
): {
  stack: ComputedRef<StackItem[]>
  isOpen: ComputedRef<boolean>
  depth: ComputedRef<number>
  topItem: ComputedRef<StackItem | null>
  push: (href: string) => void
  pushWithParams: (id: number | string, params?: Record<string, string>) => void
  pop: () => void
  closeAll: () => void
  closeFrom: (index: number) => void
} {
  const stack = computed<StackItem[]>(() => props.stack ?? [])

  const isOpen = computed(() => stack.value.length > 0)

  const depth = computed(() => stack.value.length)

  const topItem = computed(() =>
    stack.value.length > 0 ? stack.value[stack.value.length - 1] : null
  )

  /**
   * Push a new entity onto the drawer stack by navigating to its URL.
   * The server will return an updated stack prop with the new item appended.
   */
  function push(href: string): void {
    visitDrawer(href)
  }

  /**
   * Push a record onto the stack, carrying the listing's current filter/sort
   * state so the drawer's keyboard-nav (prev/next) and its own back-navigation
   * stay inside the same filtered set the user was looking at.
   */
  function pushWithParams(id: number | string, params: Record<string, string> = {}): void {
    push(withDrawerParams(`${baseUrl}/${id}`, params))
  }

  /**
   * Pop the top drawer off the stack.
   * Navigates to the previous item's href, or closes the stack entirely.
   */
  function pop() {
    if (stack.value.length <= 1) {
      closeAll()
      return
    }

    const previousItem = stack.value[stack.value.length - 2]
    if (previousItem?.href) {
      visitDrawer(previousItem.href)
    } else {
      window.history.back()
    }
  }

  /**
   * Close the entire drawer stack and return to the base listing URL.
   */
  function closeAll() {
    visitDrawer(baseUrl)
  }

  /**
   * Close from a specific depth upward.
   */
  function closeFrom(index: number) {
    if (index <= 0) {
      closeAll()
      return
    }

    const targetItem = stack.value[index - 1]
    if (targetItem?.href) {
      visitDrawer(targetItem.href)
    } else {
      closeAll()
    }
  }

  return {
    stack,
    isOpen,
    depth,
    topItem,
    push,
    pushWithParams,
    pop,
    closeAll,
    closeFrom,
  }
}
