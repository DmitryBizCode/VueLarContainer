import { toast } from 'vue-sonner';

/**
 * Composable for showing toast notifications (success, error, info).
 * Uses vue-sonner under the hood.
 */
export function useToast() {
    return {
        success: (message, description) => {
            toast.success(message, description ? { description } : undefined);
        },
        error: (message, description) => {
            toast.error(message, description ? { description } : undefined);
        },
        info: (message, description) => {
            toast.info(message, description ? { description } : undefined);
        },
        warning: (message, description) => {
            toast.warning(message, description ? { description } : undefined);
        },
        promise: toast.promise,
        dismiss: toast.dismiss,
        raw: toast,
    };
}
