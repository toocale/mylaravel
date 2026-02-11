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
    data: Array<{ description: string; total_duration: number; count?: number }>;
}>();

const { currentTheme, isOcean, isIndustrial, isMinimal } = useTheme();

const chartData = computed(() => {
    const config = currentTheme.value.chartConfig;
    
    // Sort by duration descending
    const sortedData = [...props.data].sort((a, b) => b.total_duration - a.total_duration).slice(0, 10);
    
    const isHorizontal = config.downtimeType === 'horizontal' || config.downtimeType === 'simple';
    
    return {
        labels: sortedData.map(d => d.description),
        datasets: [
            {
                label: 'Downtime (mins)',
                data: sortedData.map(d => d.total_duration),
                backgroundColor: createBackgroundColors(sortedData.length),
                borderColor: config.colors[0],
                borderWidth: isIndustrial.value ? 2 : 0,
                borderRadius: isMinimal.value ? 4 : (isIndustrial.value ? 2 : 6),
                barThickness: isMinimal.value ? 20 : (isIndustrial.value ? 30 : 25),
            }
        ]
    };
});

function createBackgroundColors(count: number): string[] {
    const config = currentTheme.value.chartConfig;
    const colors: string[] = [];
    
    for (let i = 0; i < count; i++) {
        const colorIndex = i % config.colors.length;
        colors.push(config.colors[colorIndex]);
    }
    
    return colors;
}

const chartOptions = computed(() => {
    const config = currentTheme.value.chartConfig;
    const isHorizontal = config.downtimeType === 'horizontal' || config.downtimeType === 'simple';
    // Check if dark mode is active
    const isDark = typeof document !== 'undefined' && document.documentElement.classList.contains('dark');
    const textColor = isDark || isIndustrial.value ? 'rgba(255, 255, 255, 0.7)' : 'rgba(0, 0, 0, 0.7)';
    const gridColor = isDark || isIndustrial.value ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
    const legendColor = isDark || isIndustrial.value ? 'rgba(255, 255, 255, 0.9)' : 'rgba(0, 0, 0, 0.9)';
    
    return {
        indexAxis: isHorizontal ? ('y' as const) : ('x' as const),
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: {
                display: !isHorizontal || !isMinimal.value,
                grid: {
                    display: isMinimal.value ? false : true,
                    color: gridColor,
                },
                ticks: {
                    color: textColor,
                }
            },
            y: {
                display: true,
                grid: {
                    display: isMinimal.value ? false : true,
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
                position: 'top' as const,
                labels: {
                    color: legendColor,
                    usePointStyle: false,
                    padding: 15,
                }
            },
            tooltip: {
                backgroundColor: isDark || isIndustrial.value ? 'rgba(0, 0, 0, 0.9)' : 'rgba(255, 255, 255, 0.9)',
                titleColor: isDark || isIndustrial.value ? '#fff' : '#000',
                bodyColor: isDark || isIndustrial.value ? '#fff' : '#000',
                borderColor: config.colors[0],
                borderWidth: 2,
                padding: 12,
                cornerRadius: isIndustrial.value ? 2 : 8,
                callbacks: {
                    afterBody: (context: any) => {
                        const index = context[0].dataIndex;
                        const item = props.data[index];
                        if (item) {
                            return `Occurrences: ${item.count}`;
                        }
                        return '';
                    }
                }
            }
        },
        animation: {
            duration: 800,
            easing: 'easeInOutQuart' as const,
        }
    };
});
</script>

<template>
    <div class="w-full h-48 sm:h-56 md:h-64 lg:h-72">
        <Bar :data="chartData" :options="chartOptions" />
    </div>
</template>
