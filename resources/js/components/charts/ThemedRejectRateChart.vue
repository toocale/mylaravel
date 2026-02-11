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
        reject_count?: number; 
        total_count?: number;
    }>;
}>();

const { currentTheme, isIndustrial, isMinimal } = useTheme();

const chartData = computed(() => {
    
    // Calculate Reject Rate
    const labels = props.data.map(d => formatDate(d.date));
    const rates = props.data.map(d => {
        const total = d.total_count || 0;
        if (total === 0) return 0;
        return ((d.reject_count || 0) / total) * 100;
    });

    return {
        labels: labels,
        datasets: [
            {
                label: 'Reject Rate (%)',
                data: rates,
                borderColor: '#EF4444', // Red 500
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                tension: 0.3,
                fill: true,
                borderWidth: 2,
                pointBackgroundColor: '#EF4444'
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
                // Do not fix max to 100, reject rate is usually low (0-5%)
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
                backgroundColor: isDark || isIndustrial.value ? 'rgba(0, 0, 0, 0.9)' : 'rgba(255, 255, 255, 0.9)',
                titleColor: isDark || isIndustrial.value ? '#fff' : '#000',
                bodyColor: isDark || isIndustrial.value ? '#fff' : '#000',
                borderColor: 'rgba(0,0,0,0.1)',
                borderWidth: 1,
                callbacks: {
                    label: (context: any) => {
                         return `${context.dataset.label}: ${context.raw.toFixed(2)}%`;
                    }
                }
            }
        }
    };
});
</script>

<template>
     <div class="w-full h-48 sm:h-56 md:h-64 lg:h-72">
        <Line :data="chartData" :options="chartOptions" />
    </div>
</template>
