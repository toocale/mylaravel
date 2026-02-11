import { ref } from 'vue';

export interface Toast {
    id: string;
    title?: string;
    description?: string;
    variant?: 'default' | 'destructive';
    duration?: number;
}

const toasts = ref<Toast[]>([]);

export function useToast() {
    const toast = ({
        title,
        description,
        variant = 'default',
        duration = 5000,
    }: Omit<Toast, 'id'>) => {
        const id = Math.random().toString(36).substring(7);

        toasts.value.push({
            id,
            title,
            description,
            variant,
            duration,
        });

        if (duration > 0) {
            setTimeout(() => {
                dismiss(id);
            }, duration);
        }
    };

    const dismiss = (id: string) => {
        toasts.value = toasts.value.filter((t) => t.id !== id);
    };

    return {
        toast,
        toasts,
        dismiss,
    };
}
