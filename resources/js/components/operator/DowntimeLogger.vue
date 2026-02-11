<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';

const props = defineProps<{
    reasons?: Array<{ id: number; name: string; category?: string; color?: string }>;
}>();

const emit = defineEmits(['log-downtime']);

// Placeholder reasons if none provided
const defaultReasons = [
    { id: 1, name: 'Jam', category: 'stops', color: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' },
    { id: 2, name: 'No Material', category: 'stops', color: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400' },
    { id: 3, name: 'Cleaning', category: 'planned', color: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' },
    { id: 4, name: 'Maintenance', category: 'planned', color: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' },
    { id: 5, name: 'Changeover', category: 'planned', color: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400' },
    { id: 6, name: 'Meeting', category: 'admin', color: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400' },
     { id: 7, name: 'Quality Check', category: 'admin', color: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' },
     { id: 8, name: 'Other', category: 'other', color: 'bg-slate-200 text-slate-800' },
];

const displayReasons = computed(() => props.reasons && props.reasons.length > 0 ? props.reasons : defaultReasons);
</script>

<template>
    <div class="h-full flex flex-col bg-white dark:bg-slate-900 rounded-lg md:rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
        <div class="p-2 md:p-4 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
             <h3 class="font-bold text-sm md:text-lg">Log Downtime Event</h3>
             <Badge variant="outline" class="text-[10px] md:text-xs">Tap reason to log</Badge>
        </div>
        
        <ScrollArea class="flex-1 p-2 md:p-4">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 md:gap-4 pb-2 md:pb-4">
                <button 
                    v-for="reason in displayReasons" 
                    :key="reason.id"
                    @click="emit('log-downtime', reason)"
                    class="aspect-[4/3] rounded-lg md:rounded-xl flex flex-col items-center justify-center p-2 md:p-4 text-center transition-all active:scale-95 border-2 border-transparent focus:outline-none focus:ring-2 focus:ring-slate-400"
                    :class="reason.color || 'bg-slate-100 dark:bg-slate-800'"
                >
                    <span class="font-bold text-sm md:text-lg leading-tight">{{ reason.name }}</span>
                    <span v-if="reason.category" class="text-[10px] md:text-xs opacity-70 mt-0.5 md:mt-1 uppercase tracking-wider">{{ reason.category }}</span>
                </button>
            </div>
        </ScrollArea>
    </div>
</template>
