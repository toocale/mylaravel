<template>
    <div class="w-full h-[400px]">
        <v-chart class="chart" :option="chartOption" autoresize />
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { ScatterChart } from 'echarts/charts';
import {
    TitleComponent,
    TooltipComponent,
    GridComponent,
    LegendComponent,
    MarkLineComponent
} from 'echarts/components';
import VChart from 'vue-echarts';

use([
    CanvasRenderer,
    ScatterChart,
    TitleComponent,
    TooltipComponent,
    GridComponent,
    LegendComponent,
    MarkLineComponent
]);

const props = defineProps<{
    data: Array<{
        date: string;
        actual_rate: number;
        ideal_rate: number;
        shift_name: string;
        product: string;
        run_time_mins: number;
        total_units: number;
    }>;
}>();

const chartOption = computed(() => {
    if (!props.data || props.data.length === 0) return {};

    // Transform data for ECharts [x, y] format
    // X-Axis: Date (Time)
    // Y-Axis: Actual Rate (Units/Hour)
    
    // Series 1: The Points
    const seriesData = props.data.map(item => {
        return {
            value: [
                item.date,
                item.actual_rate,
                item.product,      // Extra data for tooltip
                item.shift_name,   // Extra data for tooltip
                item.ideal_rate,   // For comparison
                item.total_units
            ],
            itemStyle: {
                // Color based on performance?
                color: item.actual_rate >= item.ideal_rate ? '#22c55e' : (item.actual_rate >= item.ideal_rate * 0.9 ? '#eab308' : '#ef4444') 
            }
        };
    });

    return {
        tooltip: {
            trigger: 'item',
            formatter: (params: any) => {
                const v = params.value;
                return `
                    <div class="font-bold">${v[0]}</div>
                    <div class="text-sm">
                        Product: <b>${v[2]}</b><br/>
                        Shift: ${v[3]}<br/>
                        Output: ${v[5]} units<br/>
                        <hr class="my-1"/>
                        Actual Rate: <b>${v[1]}</b> u/hr<br/>
                        Ideal Rate: ${v[4]} u/hr<br/>
                        Performance: ${Math.round((v[1]/v[4])*100)}%
                    </div>
                `;
            }
        },
        grid: {
            left: '3%',
            right: '4%',
            bottom: '3%',
            containLabel: true
        },
        xAxis: {
            type: 'time',
            name: 'Date',
            splitLine: { show: false }
        },
        yAxis: {
            type: 'value',
            name: 'Run Rate (Units/Hour)',
            splitLine: { show: true, lineStyle: { type: 'dashed' } }
        },
        series: [
            {
                name: 'Production Cycles',
                type: 'scatter',
                symbolSize: (data: any) => {
                    // Size bubble by volume (clamped)
                    const units = data[5]; 
                    if (units < 100) return 10;
                    if (units > 5000) return 30;
                    return 10 + (units / 5000) * 20; 
                },
                data: seriesData,
                markLine: {
                    symbol: 'none',
                    label: { formatter: 'Avg Rate' },
                    data: [
                        { type: 'average', name: 'Average' }
                    ]
                }
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
