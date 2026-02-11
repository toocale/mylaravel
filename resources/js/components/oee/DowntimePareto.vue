<script setup lang="ts">
import { Bar } from 'vue-chartjs';
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale
} from 'chart.js';
import { computed, ref } from 'vue';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

const props = defineProps<{
    data: Array<{ description: string; total_duration: number }>;
}>();

// Time unit selector
const timeUnit = ref<'seconds' | 'minutes' | 'hours'>('seconds');

// Convert seconds to selected unit
const convertTime = (seconds: number) => {
    switch (timeUnit.value) {
        case 'minutes':
            return seconds / 60;
        case 'hours':
            return seconds / 3600;
        default:
            return seconds;
    }
};

// Get unit label
const unitLabel = computed(() => {
    return timeUnit.value.charAt(0).toUpperCase() + timeUnit.value.slice(1);
});

const chartData = computed(() => ({
    labels: props.data.map(d => d.description),
    datasets: [
        {
            label: `Duration (${unitLabel.value})`,
            backgroundColor: '#6366f1', // Indigo-500
            data: props.data.map(d => convertTime(d.total_duration)),
            borderRadius: 4,
        }
    ]
}));

const chartOptions = computed(() => ({
    indexAxis: 'y' as const, // Horizontal Bar
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (context: any) => {
                    return `${context.parsed.x.toFixed(2)} ${timeUnit.value}`;
                }
            }
        }
    },
    scales: {
        x: {
            ticks: {
                callback: (value: any) => {
                    return value.toFixed(timeUnit.value === 'hours' ? 2 : 0);
                }
            }
        }
    }
}));
</script>

<template>
    <div class="w-full space-y-3">
        <!-- Time Unit Selector -->
        <div class="flex justify-end gap-1">
            <button
                v-for="unit in ['seconds', 'minutes', 'hours']"
                :key="unit"
                @click="timeUnit = unit as any"
                :class="[
                    'px-3 py-1 text-xs font-medium rounded-md transition-colors',
                    timeUnit === unit
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-muted text-muted-foreground hover:bg-muted/80'
                ]"
            >
                {{ unit }}
            </button>
        </div>
        
        <!-- Chart -->
        <div class="w-full h-48 sm:h-56 md:h-64">
            <Bar :data="chartData" :options="chartOptions" />
        </div>
    </div>
</template>
