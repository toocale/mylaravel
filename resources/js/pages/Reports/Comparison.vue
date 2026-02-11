<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { useToast } from '@/components/ui/toast/use-toast';
import { TrendingUp, TrendingDown, Minus, Calendar, BarChart3, Cpu, Clock } from 'lucide-vue-next';
import { useTerminology } from '@/composables/useTerminology';
import axios from 'axios';
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

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
);

interface Plant {
    id: number;
    name: string;
    lines: Array<{
        id: number;
        name: string;
        machines: Array<{
            id: number;
            name: string;
        }>;
    }>;
}

interface Shift {
    id: number;
    name: string;
    type: string;
}

const props = defineProps<{
    plants: Plant[];
    shifts: Shift[];
}>();

const { plant, line, machine, plants, lines, machines } = useTerminology();
const { toast } = useToast();
const activeTab = ref('period');
const comparisonData = ref<any>(null);
const isLoading = ref(false);

// Period Comparison Filters
const periodFilters = useForm({
    period1_from: new Date(Date.now() - 14 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    period1_to: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    period1_label: 'Last Week',
    period2_from: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    period2_to: new Date().toISOString().split('T')[0],
    period2_label: 'This Week',
    machine_id: null as number | null,
});

// Machine Comparison Filters
const machineFilters = useForm({
    machine_ids: [] as number[],
    date_from: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    date_to: new Date().toISOString().split('T')[0],
});

// Shift Comparison Filters
const shiftFilters = useForm({
    shift_ids: [] as number[],
    date_from: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    date_to: new Date().toISOString().split('T')[0],
    machine_id: null as number | null,
});

// Available machines
const allMachines = computed(() => {
    const machines: any[] = [];
    props.plants.forEach(plant => {
        plant.lines.forEach(line => {
            line.machines.forEach(machine => {
                machines.push({
                    ...machine,
                    plant_name: plant.name,
                    line_name: line.name,
                    display: `${plant.name} / ${line.name} / ${machine.name}`
                });
            });
        });
    });
    return machines;
});

// Quick period presets
const setQuickPeriod = (preset: string) => {
    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];
    
    switch (preset) {
        case 'week':
            periodFilters.period2_from = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
            periodFilters.period2_to = todayStr;
            periodFilters.period2_label = 'This Week';
            periodFilters.period1_from = new Date(today.getTime() - 14 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
            periodFilters.period1_to = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
            periodFilters.period1_label = 'Last Week';
            break;
        case 'month':
            const thisMonthStart = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastMonthStart = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0);
            
            periodFilters.period2_from = thisMonthStart.toISOString().split('T')[0];
            periodFilters.period2_to = todayStr;
            periodFilters.period2_label = 'This Month';
            periodFilters.period1_from = lastMonthStart.toISOString().split('T')[0];
            periodFilters.period1_to = lastMonthEnd.toISOString().split('T')[0];
            periodFilters.period1_label = 'Last Month';
            break;
    }
};

// Generate comparisons
const comparePeriods = async () => {
    isLoading.value = true;
    try {
        const response = await axios.post('/admin/reports/comparison/period', periodFilters.data());
        comparisonData.value = response.data;
    } catch (e: any) {
        toast({
            title: 'Comparison Failed',
            description: e.response?.data?.message || 'Failed to generate comparison',
            variant: 'destructive',
        });
    } finally {
        isLoading.value = false;
    }
};

const compareMachines = async () => {
    if (machineFilters.machine_ids.length < 2) {
        toast({
            title: 'Selection Required',
            description: 'Please select at least 2 machines to compare',
            variant: 'destructive',
        });
        return;
    }
    
    isLoading.value = true;
    try {
        const response = await axios.post('/admin/reports/comparison/machines', machineFilters.data());
        comparisonData.value = response.data;
    } catch (e: any) {
        toast({
            title: 'Comparison Failed',
            description: e.response?.data?.message || 'Failed to generate comparison',
            variant: 'destructive',
        });
    } finally {
        isLoading.value = false;
    }
};

const compareShifts = async () => {
    if (shiftFilters.shift_ids.length < 2) {
        toast({
            title: 'Selection Required',
            description: 'Please select at least 2 shifts to compare',
            variant: 'destructive',
        });
        return;
    }
    
    isLoading.value = true;
    try {
        const response = await axios.post('/admin/reports/comparison/shifts', shiftFilters.data());
        comparisonData.value = response.data;
    } catch (e: any) {
        toast({
            title: 'Comparison Failed',
            description: e.response?.data?.message || 'Failed to generate comparison',
            variant: 'destructive',
        });
    } finally {
        isLoading.value = false;
    }
};

// Chart data for comparisons
const comparisonChartData = computed(() => {
    if (!comparisonData.value) return null;
    
    if (comparisonData.value.comparison_type === 'period') {
        return {
            labels: ['OEE', 'Availability', 'Performance', 'Quality'],
            datasets: [
                {
                    label: comparisonData.value.period1.label,
                    data: [
                        comparisonData.value.period1.metrics.avg_oee,
                        comparisonData.value.period1.metrics.avg_availability,
                        comparisonData.value.period1.metrics.avg_performance,
                        comparisonData.value.period1.metrics.avg_quality,
                    ],
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                },
                {
                    label: comparisonData.value.period2.label,
                    data: [
                        comparisonData.value.period2.metrics.avg_oee,
                        comparisonData.value.period2.metrics.avg_availability,
                        comparisonData.value.period2.metrics.avg_performance,
                        comparisonData.value.period2.metrics.avg_quality,
                    ],
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                },
            ],
        };
    }
    
    if (comparisonData.value.comparison_type === 'machine') {
        return {
            labels: ['OEE', 'Availability', 'Performance', 'Quality'],
            datasets: comparisonData.value.machines.map((machine: any, index: number) => ({
                label: machine.name,
                data: [
                    machine.metrics.avg_oee,
                    machine.metrics.avg_availability,
                    machine.metrics.avg_performance,
                    machine.metrics.avg_quality,
                ],
                backgroundColor: `hsl(${index * 60}, 70%, 60%)`,
            })),
        };
    }
    
    if (comparisonData.value.comparison_type === 'shift') {
        return {
            labels: ['OEE', 'Availability', 'Performance', 'Quality'],
            datasets: comparisonData.value.shifts.map((shift: any, index: number) => ({
                label: shift.name,
                data: [
                    shift.metrics.avg_oee,
                    shift.metrics.avg_availability,
                    shift.metrics.avg_performance,
                    shift.metrics.avg_quality,
                ],
                backgroundColor: `hsl(${index * 80 + 180}, 70%, 60%)`,
            })),
        };
    }
    
    return null;
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        y: {
            beginAtZero: true,
            max: 100,
        },
    },
};

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/admin/reports' },
    { title: 'Comparison', href: '/admin/reports/comparison' },
];
</script>

<template>
    <Head title="Comparison Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6 space-y-6">
            <!-- Header -->
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Comparative Analysis</h2>
                <p class="text-muted-foreground">Compare performance across periods, machines, and shifts</p>
            </div>

            <!-- Comparison Type Tabs -->
            <Tabs v-model="activeTab" class="w-full">
                <TabsList class="grid w-full grid-cols-3 lg:w-[600px]">
                    <TabsTrigger value="period" class="flex items-center gap-2">
                        <Calendar class="h-4 w-4" />
                        Period
                    </TabsTrigger>
                    <TabsTrigger value="machine" class="flex items-center gap-2">
                        <Cpu class="h-4 w-4" />
                        {{ machine }}
                    </TabsTrigger>
                    <TabsTrigger value="shift" class="flex items-center gap-2">
                        <Clock class="h-4 w-4" />
                        Shift
                    </TabsTrigger>
                </TabsList>

                <!-- Period Comparison -->
                <TabsContent value="period" class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Period Comparison</CardTitle>
                            <CardDescription>Compare OEE metrics across two time periods</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- Quick Presets -->
                            <div>
                                <Label class="mb-2 block">Quick Compare</Label>
                                <div class="flex gap-2">
                                    <Button size="sm" variant="outline" @click="setQuickPeriod('week')">This Week vs Last Week</Button>
                                    <Button size="sm" variant="outline" @click="setQuickPeriod('month')">This Month vs Last Month</Button>
                                </div>
                            </div>

                            <!-- Period 1 -->
                            <div class="border rounded-lg p-4 space-y-3">
                                <h4 class="font-semibold">Period 1</h4>
                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <Label>Label</Label>
                                        <Input v-model="periodFilters.period1_label" placeholder="e.g. Last Week" />
                                    </div>
                                    <div>
                                        <Label>From</Label>
                                        <Input type="date" v-model="periodFilters.period1_from" />
                                    </div>
                                    <div>
                                        <Label>To</Label>
                                        <Input type="date" v-model="periodFilters.period1_to" />
                                    </div>
                                </div>
                            </div>

                            <!-- Period 2 -->
                            <div class="border rounded-lg p-4 space-y-3">
                                <h4 class="font-semibold">Period 2</h4>
                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <Label>Label</Label>
                                        <Input v-model="periodFilters.period2_label" placeholder="e.g. This Week" />
                                    </div>
                                    <div>
                                        <Label>From</Label>
                                        <Input type="date" v-model="periodFilters.period2_from" />
                                    </div>
                                    <div>
                                        <Label>To</Label>
                                        <Input type="date" v-model="periodFilters.period2_to" />
                                    </div>
                                </div>
                            </div>

                            <!-- Machine Filter (Optional) -->
                            <div>
                                <Label>Filter by {{ machine }} (Optional)</Label>
                                <select v-model="periodFilters.machine_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                    <option :value="null">All {{ machines }}</option>
                                    <option v-for="machine in allMachines" :key="machine.id" :value="machine.id">{{ machine.display }}</option>
                                </select>
                            </div>

                            <Button @click="comparePeriods" :disabled="isLoading" class="w-full" size="lg">
                                <BarChart3 class="mr-2 h-4 w-4" />
                                {{ isLoading ? 'Comparing...' : 'Compare Periods' }}
                            </Button>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Machine Comparison -->
                <TabsContent value="machine" class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ machine }} Comparison</CardTitle>
                            <CardDescription>Compare performance of multiple {{ machines.toLowerCase() }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <Label>From Date</Label>
                                    <Input type="date" v-model="machineFilters.date_from" />
                                </div>
                                <div>
                                    <Label>To Date</Label>
                                    <Input type="date" v-model="machineFilters.date_to" />
                                </div>
                            </div>

                            <div>
                                <Label class="mb-2 block">Select {{ machines }} (2-10)</Label>
                                <div class="grid grid-cols-2 gap-2 max-h-64 overflow-y-auto border rounded-lg p-3">
                                    <label v-for="machine in allMachines" :key="machine.id" class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" :value="machine.id" v-model="machineFilters.machine_ids" class="rounded" />
                                        {{ machine.display }}
                                    </label>
                                </div>
                                <p class="text-xs text-muted-foreground mt-1">Selected: {{ machineFilters.machine_ids.length }}</p>
                            </div>

                            <Button @click="compareMachines" :disabled="isLoading || machineFilters.machine_ids.length < 2" class="w-full" size="lg">
                                <BarChart3 class="mr-2 h-4 w-4" />
                                {{ isLoading ? 'Comparing...' : 'Compare Machines' }}
                            </Button>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Shift Comparison -->
                <TabsContent value="shift" class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Shift Comparison</CardTitle>
                            <CardDescription>Compare performance across different shifts</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <Label>From Date</Label>
                                    <Input type="date" v-model="shiftFilters.date_from" />
                                </div>
                                <div>
                                    <Label>To Date</Label>
                                    <Input type="date" v-model="shiftFilters.date_to" />
                                </div>
                            </div>

                            <div>
                                <Label>{{ machine }} (Optional)</Label>
                                <select v-model="shiftFilters.machine_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                    <option :value="null">All {{ machines }}</option>
                                    <option v-for="machine in allMachines" :key="machine.id" :value="machine.id">{{ machine.display }}</option>
                                </select>
                            </div>

                            <div>
                                <Label class="mb-2 block">Select Shifts (2+)</Label>
                                <div class="grid grid-cols-2 gap-2 border rounded-lg p-3">
                                    <label v-for="shift in shifts" :key="shift.id" class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" :value="shift.id" v-model="shiftFilters.shift_ids" class="rounded" />
                                        {{ shift.name }} <span class="text-xs text-muted-foreground">({{ shift.type }})</span>
                                    </label>
                                </div>
                                <p class="text-xs text-muted-foreground mt-1">Selected: {{ shiftFilters.shift_ids.length }}</p>
                            </div>

                            <Button @click="compareShifts" :disabled="isLoading || shiftFilters.shift_ids.length < 2" class="w-full" size="lg">
                                <BarChart3 class="mr-2 h-4 w-4" />
                                Compare Shifts
                            </Button>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>

            <!-- Results -->
            <div v-if="comparisonData" class="space-y-6 animate-in fade-in slide-in-from-bottom-4">
                <!-- Comparison Chart -->
                <Card>
                    <CardHeader>
                        <CardTitle>Performance Comparison</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="h-80">
                            <Bar v-if="comparisonChartData" :data="comparisonChartData" :options="chartOptions" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Period Comparison Results -->
                <div v-if="comparisonData.comparison_type === 'period'">
                    <!-- Variance Summary -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <Card v-for="metric in ['avg_oee', 'avg_availability', 'avg_performance', 'avg_quality']" :key="metric">
                            <CardHeader class="pb-3">
                                <CardDescription class="text-xs uppercase">{{ metric.replace('avg_', '') }}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-2">
                                    <div class="flex items-baseline justify-between">
                                        <span class="text-2xl font-bold">{{ comparisonData.period2.metrics[metric] }}%</span>
                                        <Badge :variant="comparisonData.variance[metric].trend === 'up' ? 'default' : 'destructive'" class="text-xs">
                                            <TrendingUp v-if="comparisonData.variance[metric].trend === 'up'" class="h-3 w-3 mr-1" />
                                            <TrendingDown v-else-if="comparisonData.variance[metric].trend === 'down'" class="h-3 w-3 mr-1" />
                                            <Minus v-else class="h-3 w-3 mr-1" />
                                            {{ comparisonData.variance[metric].value > 0 ? '+' : '' }}{{ comparisonData.variance[metric].value }}%
                                        </Badge>
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        vs {{ comparisonData.period1.metrics[metric] }}%
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <!-- Machine Comparison Results -->
                <div v-if="comparisonData.comparison_type === 'machine'">
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ machine }} Rankings</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-muted">
                                        <tr>
                                            <th class="p-3 text-left font-medium">Rank</th>
                                            <th class="p-3 text-left font-medium">{{ machine }}</th>
                                            <th class="p-3 text-center font-medium">OEE</th>
                                            <th class="p-3 text-center font-medium">A</th>
                                            <th class="p-3 text-center font-medium">P</th>
                                            <th class="p-3 text-center font-medium">Q</th>
                                            <th class="p-3 text-center font-medium">OEE Deviation</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <tr v-for="(machine, index) in comparisonData.machines" :key="machine.id" class="hover:bg-muted/50">
                                            <td class="p-3">{{ Number(index) + 1 }}</td>
                                            <td class="p-3 font-medium">{{ machine.name }}</td>
                                            <td class="p-3 text-center">{{ machine.metrics.avg_oee }}%</td>
                                            <td class="p-3 text-center text-xs">{{ machine.metrics.avg_availability }}%</td>
                                            <td class="p-3 text-center text-xs">{{ machine.metrics.avg_performance }}%</td>
                                            <td class="p-3 text-center text-xs">{{ machine.metrics.avg_quality }}%</td>
                                            <td class="p-3 text-center">
                                                <Badge :variant="machine.variance_from_avg.avg_oee.trend === 'up' ? 'default' : 'secondary'" class="text-xs">
                                                    {{ machine.variance_from_avg.avg_oee.value > 0 ? '+' : '' }}{{ machine.variance_from_avg.avg_oee.value }}%
                                                </Badge>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Shift Comparison Results -->
                <div v-if="comparisonData.comparison_type === 'shift'">
                    <Card>
                        <CardHeader>
                            <CardTitle>Shift Performance Comparison</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-muted">
                                        <tr>
                                            <th class="p-3 text-left font-medium">Shift</th>
                                            <th class="p-3 text-center font-medium">Type</th>
                                            <th class="p-3 text-center font-medium">OEE</th>
                                            <th class="p-3 text-center font-medium">A</th>
                                            <th class="p-3 text-center font-medium">P</th>
                                            <th class="p-3 text-center font-medium">Q</th>
                                            <th class="p-3 text-center font-medium">Total Units</th>
                                            <th class="p-3 text-center font-medium">Good Units</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <tr v-for="shift in comparisonData.shifts" :key="shift.id" class="hover:bg-muted/50">
                                            <td class="p-3 font-medium">{{ shift.name }}</td>
                                            <td class="p-3 text-center text-xs">
                                                <Badge variant="outline">{{ shift.type }}</Badge>
                                            </td>
                                            <td class="p-3 text-center">{{ shift.metrics.avg_oee }}%</td>
                                            <td class="p-3 text-center text-xs">{{ shift.metrics.avg_availability }}%</td>
                                            <td class="p-3 text-center text-xs">{{ shift.metrics.avg_performance }}%</td>
                                            <td class="p-3 text-center text-xs">{{ shift.metrics.avg_quality }}%</td>
                                            <td class="p-3 text-center text-xs">{{ shift.metrics.total_units }}</td>
                                            <td class="p-3 text-center text-xs">{{ shift.metrics.good_units }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
