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
    data: Array<{ 
        id: number;
        name: string; 
        type: string;
        oee: number; 
        availability: number; 
        performance: number; 
        quality: number;
    }>;
}>();

const { currentTheme, isIndustrial, isMinimal } = useTheme();

const chartData = computed(() => {
    // Filter to show PLANT, LINE, or MACHINE type items (whatever is in the breakdown)
    const items = (props.data || []).filter(d => d.type === 'PLANT' || d.type === 'LINE' || d.type === 'MACHINE');
    
    if (items.length === 0) return { labels: [], datasets: [] };
    
    // Colors for OEE values - gradient from red to green based on value
    const getColor = (oee: number) => {
        if (oee >= 85) return '#10B981'; // Green - World class
        if (oee >= 75) return '#22C55E'; // Light green
        if (oee >= 60) return '#EAB308'; // Yellow
        if (oee >= 40) return '#F97316'; // Orange
        return '#EF4444'; // Red
    };
    
    return {
        labels: items.map(d => d.name),
        datasets: [
            {
                label: 'OEE %',
                data: items.map(d => d.oee),
                backgroundColor: items.map(d => getColor(d.oee)),
                borderRadius: isMinimal.value ? 2 : (isIndustrial.value ? 1 : 6),
                barThickness: 'flex' as const,
                maxBarThickness: 60,
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
        // Vertical bar chart (default) - X-axis has names, Y-axis has percentages
        scales: {
            x: {
                grid: {
                    display: false,
                },
                ticks: {
                    color: textColor,
                    font: {
                        weight: 'bold' as const
                    }
                }
            },
            y: {
                min: 0,
                max: 100,
                grid: {
                    display: true,
                    color: gridColor,
                },
                ticks: {
                    color: textColor,
                    callback: function(value: any) {
                        return value + '%';
                    }
                }
            }
        },
        plugins: {
            legend: {
                display: false, // Hide legend since we only have one dataset
            },
            tooltip: {
                backgroundColor: isDark || isIndustrial.value ? 'rgba(0, 0, 0, 0.9)' : 'rgba(255, 255, 255, 0.9)',
                titleColor: isDark || isIndustrial.value ? '#fff' : '#000',
                bodyColor: isDark || isIndustrial.value ? '#fff' : '#000',
                borderColor: 'rgba(0,0,0,0.1)',
                borderWidth: 1,
                callbacks: {
                    label: function(context: any) {
                        const dataIndex = context.dataIndex;
                        const itemsList = (props.data || []).filter(d => d.type === 'PLANT' || d.type === 'LINE' || d.type === 'MACHINE');
                        const item = itemsList[dataIndex];
                        if (!item) return '';
                        return [
                            `OEE: ${item.oee}%`,
                            `Availability: ${item.availability}%`,
                            `Performance: ${item.performance}%`,
                            `Quality: ${item.quality}%`
                        ];
                    }
                }
            }
        }
    };
});

// Check if there are items to display
const hasItems = computed(() => {
    return (props.data || []).some(d => d.type === 'PLANT' || d.type === 'LINE' || d.type === 'MACHINE');
});
</script>

<template>
    <div v-if="hasItems" class="w-full h-48 sm:h-56 md:h-64">
        <Bar :data="chartData" :options="chartOptions" />
    </div>
    <div v-else class="w-full h-48 sm:h-56 md:h-64 flex items-center justify-center text-muted-foreground">
        <p class="text-sm">No data available for comparison.</p>
    </div>
</template>
