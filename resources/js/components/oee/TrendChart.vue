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
  Legend
} from 'chart.js';
import { computed } from 'vue';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend
);

const props = defineProps<{
    data: Array<{ date: string; oee: number }>;
    target?: number | null;
}>();

const chartData = computed(() => {
    const datasets: any[] = [
        {
            label: 'OEE %',
            backgroundColor: '#0ea5e9', // Sky-500
            borderColor: '#0ea5e9',     // Sky-500
            data: props.data.map(d => d.oee),
            tension: 0.3,
            fill: false,
            borderWidth: 2,
        }
    ];

    // Add target line if target is provided
    if (props.target !== null && props.target !== undefined) {
        datasets.push({
            label: 'Target',
            backgroundColor: '#fbbf24', // Amber-400
            borderColor: '#fbbf24',     // Amber-400
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

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        y: {
            min: 0,
            max: 100,
        }
    },
    plugins: {
        legend: {
            display: true,
            position: 'top' as const,
        }
    }
};
</script>

<template>
    <div class="w-full h-48 sm:h-56 md:h-64 lg:h-72">
        <Line :data="chartData" :options="chartOptions" />
    </div>
</template>
