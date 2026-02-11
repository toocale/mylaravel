<script setup lang="ts">
import { useToast } from '@/composables/useToast';
import { X, CheckCircle, AlertCircle, Info, AlertTriangle } from 'lucide-vue-next';

const { toasts, removeToast } = useToast();
</script>

<template>
    <div class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 w-full max-w-sm pointer-events-none">
        <TransitionGroup 
            name="toast" 
            tag="div" 
            class="flex flex-col gap-2"
        >
            <div 
                v-for="toast in toasts" 
                :key="toast.id"
                class="pointer-events-auto relative flex items-start gap-3 p-4 rounded-xl border shadow-lg backdrop-blur-md transition-all duration-300 overflow-hidden"
                :class="{
                    'bg-white/90 dark:bg-neutral-900/90 border-neutral-200 dark:border-neutral-800 text-neutral-900 dark:text-neutral-100': !toast.type || toast.type === 'info',
                    'bg-green-50/90 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-200': toast.type === 'success',
                    'bg-red-50/90 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-200': toast.type === 'error',
                    'bg-amber-50/90 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200': toast.type === 'warning',
                }"
            >
                <!-- Icon -->
                <div class="shrink-0 mt-0.5">
                    <CheckCircle v-if="toast.type === 'success'" class="w-5 h-5 text-green-600 dark:text-green-400" />
                    <AlertCircle v-else-if="toast.type === 'error'" class="w-5 h-5 text-red-600 dark:text-red-400" />
                    <AlertTriangle v-else-if="toast.type === 'warning'" class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                    <Info v-else class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                </div>

                <!-- Content -->
                <div class="flex-1">
                    <h3 v-if="toast.title" class="font-semibold text-sm leading-none mb-1">{{ toast.title }}</h3>
                    <p class="text-sm opacity-90 leading-snug">{{ toast.message }}</p>
                </div>

                <!-- Close Button -->
                <button 
                    @click="removeToast(toast.id)"
                    class="shrink-0 rounded-md p-1 opacity-50 hover:opacity-100 hover:bg-black/5 dark:hover:bg-white/10 transition-colors"
                >
                    <X class="w-4 h-4" />
                </button>
                
                <!-- Progress Line to auto-dismiss visual (Optional) -->
                <!-- <div class="absolute bottom-0 left-0 h-1 bg-current opacity-20" :style="{ width: '100%', transition: `width ${toast.duration}ms linear` }"></div> -->
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.toast-move,
.toast-enter-active,
.toast-leave-active {
  transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100%) scale(0.9);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(100%) scale(0.9);
}

.toast-leave-active {
  position: absolute; /* Ensures smooth removal of items in the list */
  width: 100%;
}
</style>
