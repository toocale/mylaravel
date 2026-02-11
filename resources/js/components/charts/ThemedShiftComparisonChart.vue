<script setup lang="ts">
import { Bar } from 'vue-chartjs';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
} from 'chart.js';
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
);

const props = defineProps<{
    data: Array<{ name: string; production: number; good: number; reject: number }>;
}>();

const { currentTheme, isIndustrial, isMinimal } = useTheme();

const chartData = computed(() => {
    const config = currentTheme.value.chartConfig;
    // We want stacks: Good (Green/Primary) and Reject (Red/Warning)
    
    // Determine colors based on theme if possible, or hardcode semantic colors
    const goodColor = isIndustrial.value ? '#10B981' : '#3B82F6'; // Emerald or Blue
    const rejectColor = '#EF4444'; // Red

    return {
        labels: props.data.map(d => d.name),
        datasets: [
            {
                label: 'Good Units',
                data: props.data.map(d => d.good),
                backgroundColor: goodColor,
                stack: 'Stack 0',
                 borderRadius: isMinimal.value ? 2 : (isIndustrial.value ? 1 : 4),
            },
            {
                label: 'Rejects',
                data: props.data.map(d => d.reject),
                backgroundColor: rejectColor,
                stack: 'Stack 0',
                 borderRadius: isMinimal.value ? 2 : (isIndustrial.value ? 1 : 4),
            }
        ]
    };
});

const chartOptions = computed(() => {
    const isDark = typeof document !== 'undefined' && document.documentElement.classList.contains('dark');
    const textColor = isDark || isIndustrial.value ? 'rgba(255, 255, 255, 0.7)' : 'rgba(0, 0, 0, 0.7)';
    const gridColor = isDark || isIndustrial.value ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
    const legendColor = isDark || isIndustrial.value ? 'rgba(255, 255, 255, 0.9)' : 'rgba(0, 0, 0, 0.9)';
    
    return {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: {
                stacked: true,
                grid: {
                    display: !isMinimal.value,
                    color: gridColor,
                },
                ticks: {
                    color: textColor,
                }
            },
            y: {
                stacked: true,
                grid: {
                     display: !isMinimal.value,
                    color: gridColor,
                },
                ticks: {
                    color: textColor,
                }
            }
        },
        plugins: {
            legend: {
                display: true,
                labels: {
                    color: legendColor
                }
            },
            tooltip: {
                 backgroundColor: isDark || isIndustrial.value ? 'rgba(0, 0, 0, 0.9)' : 'rgba(255, 255, 255, 0.9)',
                titleColor: isDark || isIndustrial.value ? '#fff' : '#000',
                bodyColor: isDark || isIndustrial.value ? '#fff' : '#000',
                borderColor: 'rgba(0,0,0,0.1)',
                borderWidth: 1,
            }
        }
    };
});
</script>

<template>
     <div class="w-full h-48 sm:h-56 md:h-64 lg:h-72">
        <Bar :data="chartData" :options="chartOptions" />
    </div>
</template>
