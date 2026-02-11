<script setup lang="ts">
import { Line, Bar } from 'vue-chartjs';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
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
  BarElement,
  Title,
  Tooltip,
  Legend,
  Filler
);

const props = defineProps<{
    data: Array<{ date: string; oee: number }>;
    target?: number | null;
}>();

const { currentTheme, isOcean, isIndustrial, isMinimal } = useTheme();

// Determine which chart type to use based on theme
const chartComponent = computed(() => {
    const config = currentTheme.value.chartConfig;
    
    if (config.trendType === 'bar') {
        return Bar;
    } else {
        return Line; // Both 'area' and 'line' use Line component
    }
});

const chartData = computed(() => {
    const config = currentTheme.value.chartConfig;
    const datasets: any[] = [];

    if (config.trendType === 'area') {
        // Ocean theme: Area chart with gradient fill
        datasets.push({
            label: 'OEE %',
            backgroundColor: createGradient(),
            borderColor: config.colors[0],
            data: props.data.map(d => d.oee),
            tension: 0.4,
            fill: true,
            borderWidth: 3,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: config.colors[0],
        });
    } else if (config.trendType === 'bar') {
        // Industrial theme: Bar chart with gradient
        datasets.push({
            label: 'OEE %',
            backgroundColor: config.colors[0],
            borderColor: config.colors[1],
            data: props.data.map(d => d.oee),
            borderWidth: 2,
            borderRadius: 4,
            hoverBackgroundColor: config.colors[1],
        });
    } else {
        // Minimal theme: Simple line chart
        datasets.push({
            label: 'OEE %',
            backgroundColor: 'transparent',
            borderColor: config.colors[0],
            data: props.data.map(d => d.oee),
            tension: 0.3,
            fill: false,
            borderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 5,
            pointBackgroundColor: config.colors[0],
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
        });
    }

    // Add target line if target is provided
    if (props.target !== null && props.target !== undefined) {
        datasets.push({
            label: 'Target',
            backgroundColor: isIndustrial.value ? config.colors[2] : '#fbbf24',
            borderColor: isIndustrial.value ? config.colors[2] : '#fbbf24',
            data: props.data.map(() => props.target),
            tension: 0,
            fill: false,
            borderWidth: 2,
            borderDash: [5, 5],
            pointRadius: 0,
            pointHoverRadius: 0,
        });
    }

    return {
        labels: props.data.map(d => d.date),
        datasets
    };
});

// Create gradient for area chart (Ocean theme)
function createGradient() {
    if (typeof window === 'undefined') return 'rgba(59, 130, 246, 0.2)';
    
    const config = currentTheme.value.chartConfig;
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    if (!ctx) return 'rgba(59, 130, 246, 0.2)';
    
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    
    // Parse HSL color and create gradient
    if (isOcean.value) {
        gradient.addColorStop(0, 'rgba(33, 150, 243, 0.4)');
        gradient.addColorStop(1, 'rgba(33, 150, 243, 0.0)');
    } else {
        gradient.addColorStop(0, 'rgba(59, 130, 246, 0.2)');
        gradient.addColorStop(1, 'rgba(59, 130, 246, 0.0)');
    }
    
    return gradient;
}

const chartOptions = computed(() => {
    const config = currentTheme.value.chartConfig;
    // Check if dark mode is active
    const isDark = typeof document !== 'undefined' && document.documentElement.classList.contains('dark');
    const textColor = isDark || isIndustrial.value ? 'rgba(255, 255, 255, 0.7)' : 'rgba(0, 0, 0, 0.7)';
    const gridColor = isDark || isIndustrial.value ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
    const legendColor = isDark || isIndustrial.value ? 'rgba(255, 255, 255, 0.9)' : 'rgba(0, 0, 0, 0.9)';
    
    return {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                min: 0,
                max: 100,
                grid: {
                    color: gridColor,
                    drawBorder: false,
                },
                ticks: {
                    color: textColor,
                }
            },
            x: {
                grid: {
                    display: isMinimal.value ? false : true,
                    color: isDark || isIndustrial.value ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)',
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
                    usePointStyle: true,
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
            }
        },
        interaction: {
            intersect: false,
            mode: 'index' as const,
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
        <component :is="chartComponent" :data="chartData" :options="chartOptions" />
    </div>
</template>
