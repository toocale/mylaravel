<script setup lang="ts">
import { useToast } from './use-toast';
import { X, CheckCircle2, AlertCircle } from 'lucide-vue-next';

const { toasts, dismiss } = useToast();
</script>

<template>
    <div class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 max-w-md pointer-events-none">
        <div
            v-for="toast in toasts"
            :key="toast.id"
            :class="[
                'pointer-events-auto rounded-lg shadow-lg p-4 flex items-start gap-3 animate-in slide-in-from-right border backdrop-blur-sm transition-all',
                toast.variant === 'destructive' 
                    ? 'bg-red-50 dark:bg-red-950/90 border-red-200 dark:border-red-800 text-red-900 dark:text-red-100' 
                    : 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800'
            ]"
        >
            <!-- Icon -->
            <div class="shrink-0">
                <CheckCircle2 
                    v-if="toast.variant !== 'destructive'" 
                    class="h-5 w-5 text-green-600" 
                />
                <AlertCircle 
                    v-else 
                    class="h-5 w-5 text-red-600" 
                />
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
                <h3 v-if="toast.title" class="font-semibold text-sm mb-1">
                    {{ toast.title }}
                </h3>
                <p v-if="toast.description" class="text-sm opacity-90">
                    {{ toast.description }}
                </p>
            </div>

            <!-- Close button -->
            <button
                @click="dismiss(toast.id)"
                class="shrink-0 rounded-md p-1 hover:bg-black/5 dark:hover:bg-white/10 transition-colors"
            >
                <X class="h-4 w-4" />
            </button>
        </div>
    </div>
</template>
