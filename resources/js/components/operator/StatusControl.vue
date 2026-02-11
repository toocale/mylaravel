<script setup lang="ts">
import { Play, Square, Pause, AlertTriangle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { computed } from 'vue';

const props = defineProps<{
    status: 'running' | 'idle' | 'stopped' | 'error';
    machineId: number;
    isLoading?: boolean;
}>();

const emit = defineEmits(['change-status']);

const isRunning = computed(() => props.status === 'running');
</script>

<template>
    <div class="flex flex-col gap-4 w-full h-full">
        <!-- Main Control Button -->
        <button 
            @click="emit('change-status', isRunning ? 'stopped' : 'running')"
            :disabled="isLoading"
            class="flex-1 rounded-2xl flex flex-col items-center justify-center gap-4 transition-all active:scale-[0.98] shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
            :class="isRunning 
                ? 'bg-red-500 hover:bg-red-600 text-white shadow-red-500/30' 
                : 'bg-green-600 hover:bg-green-700 text-white shadow-green-600/30 animate-pulse-slow'"
        >
            <component :is="isRunning ? Square : Play" class="h-16 w-16" :class="{ 'ml-2': !isRunning }" />
            <span class="text-2xl font-black uppercase tracking-widest">
                {{ isRunning ? 'Stop Production' : 'Start Production' }}
            </span>
        </button>

        <!-- Secondary Controls row -->
        <div class="grid grid-cols-2 gap-4 h-32">
            <button 
                class="rounded-xl flex flex-col items-center justify-center bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-500 border-2 border-amber-200 dark:border-amber-800 hover:bg-amber-200 dark:hover:bg-amber-900/50 transition-colors"
                @click="emit('change-status', 'idle')"
            >
                <Pause class="h-8 w-8 mb-2" />
                <span class="font-bold">Pause / Idle</span>
            </button>

             <button 
                class="rounded-xl flex flex-col items-center justify-center bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-400 border-2 border-slate-200 dark:border-slate-700 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
                @click="emit('change-status', 'break')"
            >
                <span class="text-2xl mb-1">☕</span>
                <span class="font-bold">Break</span>
            </button>
        </div>
    </div>
</template>

<style scoped>
.animate-pulse-slow {
    animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: .9; }
}
</style>
