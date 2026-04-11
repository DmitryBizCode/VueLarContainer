import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from '@/composables/useToast';

/**
 * Watches Inertia flash props and shows toasts for status (success) and error messages.
 * Call once in the root layout (e.g. AuthenticatedLayout).
 */
export function useFlashToToast() {
    const page = usePage();
    const { success, error } = useToast();

    watch(
        () => ({
            status: page.props.flash?.status,
            error: page.props.flash?.error,
            errors: page.props.flash?.errors,
        }),
        (flash) => {
            if (flash.status) {
                success(flash.status);
            }
            if (flash.error) {
                error(flash.error);
            }
            if (flash.errors && typeof flash.errors === 'object') {
                const messages = Object.values(flash.errors)
                    .flat()
                    .map((msg) => (Array.isArray(msg) ? msg[0] : msg))
                    .filter(Boolean);
                if (messages.length > 0) {
                    if (messages.length === 1) {
                        error(messages[0]);
                    } else {
                        error('Validation failed', '• ' + messages.join('\n• '));
                    }
                }
            }
        },
        { immediate: true }
    );
}
