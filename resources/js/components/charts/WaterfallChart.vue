<template>
    <div class="w-full h-[400px]">
        <v-chart class="chart" :option="chartOption" autoresize />
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { BarChart } from 'echarts/charts';
import {
    TitleComponent,
    TooltipComponent,
    GridComponent,
    LegendComponent
} from 'echarts/components';
import VChart from 'vue-echarts';

use([
    CanvasRenderer,
    BarChart,
    TitleComponent,
    TooltipComponent,
    GridComponent,
    LegendComponent
]);

const props = defineProps<{
    data: Array<{
        name: string;
        value: number;
        type: string;
        category?: string;
    }>;
}>();

const chartOption = computed(() => {
    if (!props.data || props.data.length === 0) return {};

    const categories = props.data.map(item => item.name);
    
    // Waterfall Logic: Calculate 'placeholder' (invisible bar) for floating effect
    const values = props.data.map(item => item.value);
    const types = props.data.map(item => item.type);
    
    const placeholders = [];
    const barValues = [];
    
    // We need to calculate the "step down"
    // Ideally, the backend gives us the sequence:
    // 1. Total (100) -> Start at 0, Height 100
    // 2. Loss A (10) -> Start at 90, Height 10
    // 3. Loss B (20) -> Start at 70, Height 20
    // ...
    // But standard waterfall is usually additive or subtractive.
    
    // Let's assume the data order is:
    // Total -> Planned Loss -> Unplanned Loss -> Net Time -> Perf Loss -> Quality Loss -> Fully Productive
    
    // We iterate to build the visual steps
    // Total = First Bar (Base 0)
    // Loss = Subtractive from previous Top
    
    let currentHeight = values[0]; // Start with Total
    
    // Map data to ECharts waterfall format
    // Series 1: Transparent Placeholder (base)
    // Series 2: Actual Value (bar)
    
    for (let i = 0; i < props.data.length; i++) {
        const item = props.data[i];
        
        if (i === 0) {
            // First item is typically the Total
            placeholders.push(0);
            barValues.push(item.value);
        } else if (item.type === 'total' || item.type === 'final') {
            // Totals or subtotals start at 0
            placeholders.push(0);
            barValues.push(item.value);
            currentHeight = item.value; // Reset current height tracker to this total
        } else {
            // Loss item (subtractive)
            currentHeight -= item.value;
            placeholders.push(currentHeight);
            barValues.push(item.value);
        }
    }

    return {
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params: any) => {
                const tar = params[1]; // The actual bar, not placeholder
                return `${tar.name}<br/>${tar.seriesName} : ${tar.value} Hours`;
            }
        },
        grid: {
            left: '3%',
            right: '4%',
            bottom: '3%',
            containLabel: true
        },
        xAxis: {
            type: 'category',
            splitLine: { show: false },
            data: categories
        },
        yAxis: {
            type: 'value',
            name: 'Hours'
        },
        series: [
            {
                name: 'Placeholder',
                type: 'bar',
                stack: 'Total',
                itemStyle: {
                    borderColor: 'transparent',
                    color: 'transparent'
                },
                emphasis: {
                    itemStyle: {
                        borderColor: 'transparent',
                        color: 'transparent'
                    }
                },
                data: placeholders
            },
            {
                name: 'Duration',
                type: 'bar',
                stack: 'Total',
                label: {
                    show: true,
                    position: 'inside'
                },
                itemStyle: {
                     color: (params: any) => {
                         const type = props.data[params.dataIndex].type;
                         const category = props.data[params.dataIndex].category;
                         
                         if (type === 'total') return '#3b82f6'; // Blue
                         if (type === 'final') return '#22c55e'; // Green
                         if (type === 'subtotal') return '#64748b'; // Gray
                         
                         // Losses
                         if (category === 'availability') return '#ef4444'; // Red
                         if (category === 'performance') return '#f97316'; // Orange
                         if (category === 'quality') return '#eab308'; // Yellow
                         
                         return '#ef4444';
                     }
                },
                data: barValues
            }
        ]
    };
});
</script>

<style scoped>
.chart {
    height: 100%;
    width: 100%;
}
</style>
