import { ref } from 'vue';

export interface Toast {
    id: string;
    title?: string;
    message: string;
    type?: 'success' | 'error' | 'info' | 'warning';
    duration?: number;
}

const toasts = ref<Toast[]>([]);

export const useToast = () => {
    const addToast = (toast: Omit<Toast, 'id'>) => {
        const id = Math.random().toString(36).substring(2, 9);
        const newToast = {
            id,
            duration: 3000, // Default 3 seconds
            type: 'info',
            ...toast,
        } as Toast;

        toasts.value.push(newToast);

        if (newToast.duration !== 0) {
            setTimeout(() => {
                removeToast(id);
            }, newToast.duration);
        }
    };

    const removeToast = (id: string) => {
        toasts.value = toasts.value.filter((t) => t.id !== id);
    };

    return {
        toasts,
        addToast,
        removeToast,
    };
};
