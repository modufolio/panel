import { computed } from 'vue'
import type { Ref, ComputedRef } from 'vue'

type PaginationItem =
    | { type: 'page'; key: string; value: number }
    | { type: 'ellipsis'; key: string }

interface UsePaginationOptions {
    pagination: Ref<{ total?: number; totalPages?: number }>
    currentPage: Ref<number>
    onNavigate: (page: number) => void
}

interface UsePaginationReturn {
    paginationItems: ComputedRef<PaginationItem[]>
    goToPage: (page: number) => void
}

export function usePagination(options: UsePaginationOptions): UsePaginationReturn {
    const { pagination, currentPage, onNavigate } = options

    const paginationItems = computed<PaginationItem[]>(() => {
        const total = Number(pagination.value?.totalPages ?? 1)
        const page = Number(currentPage.value ?? 1)

        if (total <= 7) {
            return Array.from({ length: total }, (_, i) => ({
                type: 'page' as const,
                key: `p-${i + 1}`,
                value: i + 1,
            }))
        }

        const pages = new Set([1, total])
        for (let p = page - 2; p <= page + 2; p++) {
            if (p > 1 && p < total) pages.add(p)
        }

        const sorted = [...pages].sort((a, b) => a - b)
        const items: PaginationItem[] = []
        let previous: number | null = null

        for (const p of sorted) {
            if (previous !== null && p - previous > 1) {
                items.push({ type: 'ellipsis', key: `e-${previous}-${p}` })
            }
            items.push({ type: 'page', key: `p-${p}`, value: p })
            previous = p
        }

        return items
    })

    const goToPage = (page: number) => onNavigate(page)

    return { paginationItems, goToPage }
}
