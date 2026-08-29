import { onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'

/**
 * Warn before a navigation would discard unsaved edits.
 *
 * Hooks Inertia's `before` event, so it covers every ordinary visit —
 * including Logout, which is a POST visit like any other — plus
 * `beforeunload` for tab closes and hard refreshes.
 *
 * Takes Inertia's form object, or a getter for anything else that knows
 * whether it is dirty (the nested drawer form's computed, say). Read at
 * event time either way, never captured.
 */
export function useUnsavedChangesWarning(form: { isDirty: boolean } | (() => boolean)) {
    const isDirty = (): boolean => (typeof form === 'function' ? form() : form.isDirty)
    let unsubscribe: (() => void) | null = null
    let ignoreNextNavigation = false

    function handleBeforeUnload(e: BeforeUnloadEvent) {
        if (isDirty()) {
            e.preventDefault()
            e.returnValue = ''
        }
    }

    function onBefore(event: { detail?: { visit?: { prefetch?: boolean; deferredProps?: boolean } }; preventDefault?: () => void }) {
        // Skip the prompt for prefetch and deferred-props requests —
        // these are background fetches, not real navigations away from the page.
        const visit = event?.detail?.visit
        if (visit?.prefetch || visit?.deferredProps) {
            return true
        }

        // if we explicitly allowed the next navigation, skip prompt
        if (ignoreNextNavigation) {
            ignoreNextNavigation = false
            return true
        }

        if (isDirty()) {
            const leave = confirm('You have unsaved changes. Leave anyway?')
            if (!leave && event && typeof event.preventDefault === 'function') {
                event.preventDefault()
            }
            return leave
        }

        return true
    }

    onMounted(() => {
        window.addEventListener('beforeunload', handleBeforeUnload)
        if (router && typeof router.on === 'function') {
            unsubscribe = router.on('before', onBefore)
        }
    })

    onBeforeUnmount(() => {
        window.removeEventListener('beforeunload', handleBeforeUnload)
        if (unsubscribe) unsubscribe()
    })

    // call this right before you initiate a save/visit
    function allowNextNavigation() {
        ignoreNextNavigation = true
    }

    return { allowNextNavigation }
}
