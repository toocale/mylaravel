<script setup lang="ts">
import { Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { TrendingUp, TrendingDown, Target } from 'lucide-vue-next';

ChartJS.register(ArcElement, Tooltip, Legend);

const props = defineProps<{
    value: number;
    label: string;
    color?: string;
    size?: 'xs' | 'sm' | 'md' | 'lg';
    target?: number | null;
    showTarget?: boolean;
}>();

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'lg': return 'w-32 h-32 sm:w-40 sm:h-40 md:w-48 md:h-48';
        case 'sm': return 'w-16 h-16 sm:w-20 sm:h-20 md:w-24 md:h-24';
        case 'xs': return 'w-12 h-12 sm:w-14 sm:h-14 md:w-16 md:h-16';
        default: return 'w-24 h-24 sm:w-28 sm:h-28 md:w-32 md:h-32';
    }
});

const textClasses = computed(() => {
     switch (props.size) {
        case 'lg': return 'text-2xl sm:text-3xl md:text-4xl';
        case 'sm': return 'text-base sm:text-lg';
        case 'xs': return 'text-xs sm:text-sm';
        default: return 'text-xl sm:text-2xl';
    }
});

// Determine gauge color based on target achievement
const gaugeColor = computed(() => {
    if (props.color) return props.color;
    if (!props.target) return '#3b82f6'; // Default blue
    
    // Strict Red/Green logic
    if (props.value >= props.target) return '#16a34a'; // Green - met or exceeded
    return '#dc2626'; // Red - below target
});

const isAboveTarget = computed(() => {
    return props.target && props.value >= props.target;
});

const targetDiff = computed(() => {
    if (!props.target) return null;
    return Math.abs(props.value - props.target).toFixed(1);
});

const chartData = computed(() => ({
    labels: ['Score', 'Gap'],
    datasets: [
        {
            backgroundColor: [gaugeColor.value, '#e2e8f0'],
            data: [props.value, 100 - props.value],
            borderWidth: 0,
        }
    ]
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '75%',
    plugins: {
        legend: { display: false },
        tooltip: { enabled: false }
    }
};
</script>

<template>
    <div class="relative flex flex-col items-center justify-center gap-0.5">
        <div class="relative flex items-center justify-center" :class="sizeClasses">
            <div class="absolute inset-0">
                 <Doughnut :data="chartData" :options="chartOptions" />
            </div>
            <div class="text-center">
                 <span class="font-black block text-neutral-800 dark:text-neutral-100" :class="textClasses">{{ value }}%</span>
                 <span class="text-[10px] uppercase tracking-wide font-semibold text-muted-foreground">{{ label }}</span>
            </div>
        </div>
        
        <!-- Target Badge (below gauge) -->
        <div v-if="target && showTarget" class="flex flex-col items-center gap-1.5">
            <div class="flex items-center gap-2">
                <Target class="h-5 w-5" :class="isAboveTarget ? 'text-green-600' : 'text-red-600'" />
                <span class="text-base md:text-lg font-bold" :class="isAboveTarget ? 'text-green-600' : 'text-red-600'">
                    Target: {{ target }}%
                </span>
            </div>
            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg" :class="isAboveTarget ? 'bg-green-100 dark:bg-green-950' : 'bg-red-100 dark:bg-red-950'">
                <component :is="isAboveTarget ? TrendingUp : TrendingDown" class="h-5 w-5" :class="isAboveTarget ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400'" />
                <span class="text-base md:text-lg font-extrabold" :class="isAboveTarget ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400'">
                    {{ isAboveTarget ? '+' : '-' }}{{ targetDiff }}%
                </span>
            </div>
        </div>
    </div>
</template>
