<template>
    <AppShell title="Advanced Analytics">
        <div class="space-y-6">
            <!-- Header / Filters -->
            <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between bg-card p-4 rounded-lg border shadow-sm">
                <div>
                    <h2 class="text-lg font-semibold">Deep Dive Analysis</h2>
                    <p class="text-sm text-muted-foreground">Loss waterfall and cycle time distribution.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                     <Select v-model="filters.machine_id">
                        <SelectTrigger class="w-[180px]">
                            <SelectValue placeholder="Select Machine" />
                        </SelectTrigger>
                        <SelectContent>
                             <SelectItem value="all">All Machines</SelectItem>
                             <SelectItem v-for="m in machines" :key="m.id" :value="String(m.id)">{{ m.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                     
                    <Select v-model="filters.period">
                        <SelectTrigger class="w-[150px]">
                            <SelectValue placeholder="Time Period" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="7">Last 7 Days</SelectItem>
                            <SelectItem value="30">Last 30 Days</SelectItem>
                            <SelectItem value="90">Last Quarter</SelectItem>
                        </SelectContent>
                    </Select>
                     
                    <Button @click="loadData" :disabled="loading">
                        <RefreshCw class="w-4 h-4 mr-2" :class="{ 'animate-spin': loading }" />
                        Analyze
                    </Button>
                </div>
            </div>

            <!-- Charts Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Waterfall Card -->
                <Card class="col-span-1 lg:col-span-2 xl:col-span-1">
                    <CardHeader>
                        <CardTitle>OEE Loss Waterfall</CardTitle>
                        <CardDescription>Where did the time go? Breakdown of losses.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="loading" class="h-[400px] flex items-center justify-center">
                            <Loader2 class="w-8 h-8 animate-spin text-muted-foreground" />
                        </div>
                         <WaterfallChart v-else :data="waterfallData" />
                    </CardContent>
                </Card>

                <!-- Scatter Card -->
                <Card class="col-span-1 lg:col-span-2 xl:col-span-1">
                    <CardHeader>
                        <CardTitle>Cycle Time Analysis</CardTitle>
                        <CardDescription>Production consistency (Actual Rate vs Ideal).</CardDescription>
                    </CardHeader>
                    <CardContent>
                         <div v-if="loading" class="h-[400px] flex items-center justify-center">
                            <Loader2 class="w-8 h-8 animate-spin text-muted-foreground" />
                        </div>
                        <ScatterChart v-else :data="scatterData" />
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppShell>
</template>

<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue';
import AppShell from '@/components/AppShell.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { RefreshCw, Loader2 } from 'lucide-vue-next';
import WaterfallChart from '@/components/charts/WaterfallChart.vue';
import ScatterChart from '@/components/charts/ScatterChart.vue';
import axios from 'axios';

// Props (passed from Inertia controller usually, but here we might fetch machines via API)
const machines = ref([]);

const loading = ref(false);
const filters = reactive({
    machine_id: 'all',
    period: '30'
});

const waterfallData = ref([]);
const scatterData = ref([]);

const fetchMachines = async () => {
    try {
        const res = await axios.get('/api/dashboard/options'); // Re-using existing options endpoint
        // Flatten structure: Plants -> Lines -> Machines
        const flatMachines = [];
        res.data.forEach(plant => {
            plant.lines.forEach(line => {
                line.machines.forEach(machine => {
                    flatMachines.push(machine);
                });
            });
        });
        machines.value = flatMachines;
    } catch (e) {
        console.error('Failed to load machines', e);
    }
};

const loadData = async () => {
    loading.value = true;
    try {
        const params = {
            machine_id: filters.machine_id === 'all' ? null : filters.machine_id,
            days: filters.period,
            date_from: getDateFrom(parseInt(filters.period)),
            date_to: new Date().toISOString().split('T')[0]
        };

        const [lossRes, cycleRes] = await Promise.all([
            axios.get('/api/analytics/loss', { params }),
            axios.get('/api/analytics/cycle-time', { params })
        ]);

        waterfallData.value = lossRes.data.waterfall || [];
        scatterData.value = cycleRes.data.points || [];

    } catch (error) {
        console.error('Analytics load error', error);
    } finally {
        loading.value = false;
    }
};

const getDateFrom = (days) => {
    const d = new Date();
    d.setDate(d.getDate() - days);
    return d.toISOString().split('T')[0];
};

onMounted(() => {
    fetchMachines();
    loadData();
});
</script>
