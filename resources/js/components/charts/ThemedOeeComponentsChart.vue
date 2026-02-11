<script setup lang="ts">
import { Line } from 'vue-chartjs';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
} from 'chart.js';
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
);

const props = defineProps<{
    data: Array<{ 
        date: string; 
        oee: number; 
        availability?: number; 
        performance?: number; 
        quality?: number 
    }>;
}>();

const { currentTheme, isIndustrial, isMinimal } = useTheme();

const chartData = computed(() => {
    const config = currentTheme.value.chartConfig;
    
    return {
        labels: props.data.map(d => formatDate(d.date)),
        datasets: [
            {
                label: 'Availability',
                data: props.data.map(d => d.availability || 0),
                borderColor: '#10B981', // Emerald
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.3,
                fill: false,
                borderWidth: 2
            },
            {
                label: 'Performance',
                data: props.data.map(d => d.performance || 0),
                borderColor: '#F59E0B', // Amber
                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                tension: 0.3,
                fill: false,
                borderWidth: 2
            },
            {
                label: 'Quality',
                data: props.data.map(d => d.quality || 0),
                borderColor: '#8B5CF6', // Purple
                backgroundColor: 'rgba(139, 92, 246, 0.1)',
                tension: 0.3,
                fill: false,
                borderWidth: 2
            }
        ]
    };
});

function formatDate(dateStr: string): string {
    const d = new Date(dateStr);
    return `${d.getDate()}/${d.getMonth() + 1}`;
}

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
                grid: {
                    display: !isMinimal.value,
                    color: gridColor,
                },
                ticks: {
                    color: textColor,
                }
            },
            y: {
                min: 0,
                max: 105,
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
                align: 'end' as const,
                labels: {
                    color: legendColor,
                    boxWidth: 10,
                    usePointStyle: true,
                }
            },
            tooltip: {
                mode: 'index' as const,
                intersect: false,
                backgroundColor: isDark || isIndustrial.value ? 'rgba(0, 0, 0, 0.9)' : 'rgba(255, 255, 255, 0.9)',
                titleColor: isDark || isIndustrial.value ? '#fff' : '#000',
                bodyColor: isDark || isIndustrial.value ? '#fff' : '#000',
                borderColor: 'rgba(0,0,0,0.1)',
                borderWidth: 1,
            }
        },
        interaction: {
            mode: 'index' as const,
            intersect: false,
        },
    };
});
</script>

<template>
     <div class="w-full h-48 sm:h-56 md:h-64 lg:h-72">
        <Line :data="chartData" :options="chartOptions" />
    </div>
</template>
