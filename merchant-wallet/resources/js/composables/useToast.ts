import { readonly, ref } from "vue";

export type ToastType = "success" | "error" | "info";

export interface Toast {
    id: number;
    message: string;
    type: ToastType;
}

const toasts = ref<Toast[]>([]);
let nextId = 0;

/**
 * Lightweight app-wide toast notifications. A single <ToastHost> anywhere in
 * the tree renders the queue; any component can push to it via `showToast`.
 */
export function useToast() {
    function showToast(message: string, type: ToastType = "info", duration = 3500) {
        const id = nextId++;
        toasts.value.push({ id, message, type });

        window.setTimeout(() => dismiss(id), duration);
    }

    function dismiss(id: number) {
        toasts.value = toasts.value.filter((toast) => toast.id !== id);
    }

    return {
        toasts: readonly(toasts),
        showToast,
        dismiss,
    };
}
