<script setup lang="ts">
import { onMounted, ref, onUnmounted, computed, watch } from 'vue';
import axios from 'axios';
import { useOeeStore } from '@/stores/oee';
import ThemedOeeGauge from '@/components/charts/ThemedOeeGauge.vue';
import ThemedTrendChart from '@/components/charts/ThemedTrendChart.vue';
import ThemedDowntimeChart from '@/components/charts/ThemedDowntimeChart.vue';
import MaterialLossTrendChart from '@/components/MaterialLossTrendChart.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Pause, Play, RefreshCw, BarChart2, Activity, TrendingDown, Clock, AlertTriangle } from 'lucide-vue-next';
import ThemedShiftComparisonChart from '@/components/charts/ThemedShiftComparisonChart.vue';
import ThemedOeeComponentsChart from '@/components/charts/ThemedOeeComponentsChart.vue';
import ThemedRejectRateChart from '@/components/charts/ThemedRejectRateChart.vue';
import WaterfallChart from '@/components/charts/WaterfallChart.vue';
import ScatterChart from '@/components/charts/ScatterChart.vue';
import ThemedDivisionsComparisonChart from '@/components/charts/ThemedDivisionsComparisonChart.vue';
import { useTerminology } from '@/composables/useTerminology';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AndonBoard from '@/components/AndonBoard.vue';

const { plant, line, machine, plants, lines, machines } = useTerminology();

const oeeStore = useOeeStore();

const getAssetLabel = (type: string) => {
    const t = type.toUpperCase();
    if (t === 'PLANT') return plant.value;
    if (t === 'LINE') return line.value;
    if (t === 'MACHINE') return machine.value;
    return type;
};

// Default Dates
const dateFrom = ref(new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]);
const dateTo = ref(new Date().toISOString().split('T')[0]);

const selectedPlant = ref<number | null>(null);
const selectedLine = ref<number | null>(null);
const selectedMachine = ref<number | null>(null);
const activeTab = ref('overview');

const props = defineProps<{
    initialContext?: {
        plantId: number | null;
        lineId: number | null;
        machineId: number | null;
    },
    // If true, shows simple version (no header title, simplified layout). Default false.
    embedded?: boolean;
    // Optional: Pre-load options to avoid fetching (e.g. from parent prop)
    initialOptions?: any[];
}>();

const emit = defineEmits(['update:context']);

onMounted(async () => {
    // 1. Preload options if provided
    if (props.initialOptions && props.initialOptions.length > 0) {
        oeeStore.options = props.initialOptions;
    }

    // 2. Fetch if still empty
    if(oeeStore.options.length === 0) await oeeStore.fetchOptions();
    
    // Set initial filters from props or defaults
    if (props.initialContext) {
        setContext(props.initialContext);
    } else {
         applyFilters();
    }
});

watch(() => props.initialContext, (newCtx) => {
    if(newCtx) setContext(newCtx);
}, { deep: true });

const setContext = (ctx: any) => {
    // Prevent infinite loops / redundant updates
    if (
        selectedPlant.value === (ctx.plantId ?? null) &&
        selectedLine.value === (ctx.lineId ?? null) &&
        selectedMachine.value === (ctx.machineId ?? null)
    ) {
        return;
    }

    console.log('[OeeDashboard] setContext called with:', ctx);
    selectedPlant.value = ctx.plantId ?? null;
    selectedLine.value = ctx.lineId ?? null;
    selectedMachine.value = ctx.machineId ?? null;
    applyFilters();
}

// Computed available options (Helper for dynamic title / logic)
const drillDownItems = computed(() => {
    if (selectedMachine.value) return [];
    console.log('DrillDownItems:', oeeStore.breakdown);
    return oeeStore.breakdown;
});

// Dynamic Title
const contextTitle = computed(() => {
    if (selectedMachine.value) {
        return `${getMachineName(selectedMachine.value)} ${machine.value} Performance`;
    } else if (selectedLine.value) {
        return `${getLineName(selectedLine.value)} ${line.value} Performance`;
    } else if (selectedPlant.value) {
        return `${getPlantName(selectedPlant.value)} ${plant.value} Performance`;
    }
    return `All ${plants.value} Performance`;
});

// Navigation Helpers
const getPlantName = (id: number | null) => {
    if (!id) return `All ${plants.value}`;
    // If options not loaded yet, return loading state or ID? 
    // Actually, if options empty, we can't do much. 
    if (oeeStore.options.length === 0) return 'Loading...';
    
    const p = oeeStore.options.find((x: any) => x.id === id);
    return p ? p.name : `Unknown ${plant.value}`;
};

const getLineName = (id: number | null) => {
    if (!id) return '';
    if (oeeStore.options.length === 0) return 'Loading...';

    // Search all plants for this line
    for (const p of oeeStore.options) {
        const l = p.lines?.find((x: any) => x.id === id);
        if (l) return l.name;
    }
    return `Unknown ${line.value}`;
};

const getMachineName = (id: number | null) => {
    if (!id) return '';
    if (oeeStore.options.length === 0) return 'Loading...';

    // Search all plants and lines for this machine
    for (const p of oeeStore.options) {
        if (p.lines) {
            for (const l of p.lines) {
                const m = l.machines?.find((x: any) => x.id === id);
                if (m) return m.name;
            }
        }
    }
    return `Unknown ${machine.value}`;
};

// Handlers
const handleDrillDown = (item: any) => {
    if (item.type === 'LINE') {
        selectedLine.value = item.id;
    } else if (item.type === 'MACHINE') {
        selectedMachine.value = item.id;
    } else if (item.type === 'PLANT') {
        selectedPlant.value = item.id;
    }
    applyFilters();
};

const resetToPlant = () => {
    selectedLine.value = null;
    selectedMachine.value = null;
    applyFilters();
};

const resetToLine = () => {
    selectedMachine.value = null;
    applyFilters();
};


const applyFilters = () => {
    console.log('[OeeDashboard] applyFilters with:', { plantId: selectedPlant.value, lineId: selectedLine.value, machineId: selectedMachine.value });
    oeeStore.setFilters({
        plantId: selectedPlant.value,
        lineId: selectedLine.value,
        machineId: selectedMachine.value,
        dateFrom: dateFrom.value,
        dateTo: dateTo.value
    });
    
    // Notify parent if needed
    emit('update:context', {
        plantId: selectedPlant.value,
        lineId: selectedLine.value,
        machineId: selectedMachine.value
    });

    fetchAdvancedAnalytics();
};

const waterfallData = ref([]);
const scatterData = ref([]);

const fetchAdvancedAnalytics = async () => {
    try {
        const params = {
            machine_id: selectedMachine.value,
            date_from: dateFrom.value,
            date_to: dateTo.value
        };
        // Use axios directly as it's likely available or import it
        const [lossRes, cycleRes] = await Promise.all([
            axios.get('/api/v1/analytics/loss', { params }),
            axios.get('/api/v1/analytics/cycle-time', { params })
        ]);
        waterfallData.value = lossRes.data.waterfall || [];
        scatterData.value = cycleRes.data.points || [];
    } catch (e) {
        console.error('Advanced analytics error', e);
    }
};

// Enhanced Auto-Refresh System
const refreshInterval = ref<number>(parseInt(localStorage.getItem('oee_refresh_interval') || '15000'));
const autoRefreshEnabled = ref<boolean>(localStorage.getItem('oee_auto_refresh') !== 'false');
const lastUpdated = ref<Date | null>(null);
const refreshTimer = ref<number | null>(null);
const timeSinceUpdate = ref<string>('Never');

// Available refresh intervals
const refreshIntervals = [
    { value: 5000, label: '5s' },
    { value: 10000, label: '10s' },
    { value: 15000, label: '15s' },
    { value: 30000, label: '30s' },
    { value: 60000, label: '60s' },
];

// Function to perform refresh
const performRefresh = async () => {
    if (!oeeStore.loading && autoRefreshEnabled.value) {
        await oeeStore.fetchDashboardData();
        lastUpdated.value = new Date();
    }
};

// Start auto-refresh
const startAutoRefresh = () => {
    if (refreshTimer.value) {
        clearInterval(refreshTimer.value);
    }
    
    if (autoRefreshEnabled.value) {
        refreshTimer.value = window.setInterval(performRefresh, refreshInterval.value);
    }
};

// Stop auto-refresh
const stopAutoRefresh = () => {
    if (refreshTimer.value) {
        clearInterval(refreshTimer.value);
        refreshTimer.value = null;
    }
};

// Toggle auto-refresh
const toggleAutoRefresh = () => {
    autoRefreshEnabled.value = !autoRefreshEnabled.value;
    localStorage.setItem('oee_auto_refresh', autoRefreshEnabled.value.toString());
    
    if (autoRefreshEnabled.value) {
        startAutoRefresh();
    } else {
        stopAutoRefresh();
    }
};

// Change refresh interval
const setRefreshInterval = (interval: number) => {
    refreshInterval.value = interval;
    localStorage.setItem('oee_refresh_interval', interval.toString());
    
    // Restart timer with new interval
    if (autoRefreshEnabled.value) {
        startAutoRefresh();
    }
};

// Manual refresh
const manualRefresh = async () => {
    await oeeStore.fetchDashboardData();
    lastUpdated.value = new Date();
};

// Update time since last update
const updateTimeSinceUpdate = () => {
    if (!lastUpdated.value) {
        timeSinceUpdate.value = 'Never';
        return;
    }
    
    const seconds = Math.floor((Date.now() - lastUpdated.value.getTime()) / 1000);
    
    if (seconds < 60) {
        timeSinceUpdate.value = `${seconds}s ago`;
    } else if (seconds < 3600) {
        const minutes = Math.floor(seconds / 60);
        timeSinceUpdate.value = `${minutes}m ago`;
    } else {
        const hours = Math.floor(seconds / 3600);
        timeSinceUpdate.value = `${hours}h ago`;
    }
};

// Timer for updating "X seconds ago" display
const timeUpdateTimer = ref<number | null>(null);

onMounted(() => {
    // Initial fetch sets the timestamp
    lastUpdated.value = new Date();
    
    // Start auto-refresh
    startAutoRefresh();
    
    // Update time display every second
    timeUpdateTimer.value = window.setInterval(updateTimeSinceUpdate, 1000);
});

onUnmounted(() => {
    stopAutoRefresh();
    if (timeUpdateTimer.value) {
        clearInterval(timeUpdateTimer.value);
    }
});

// Watch for active shift to increase refresh frequency
watch(() => oeeStore.currentShift, (newShift) => {
    // If there's an active shift and refresh interval is > 15s, suggest faster refresh
    if (newShift && newShift.name !== 'Completed' && refreshInterval.value > 15000) {
        // Optionally auto-switch to 10s for active shifts
        // For now, just keep user's preference
    }
});

// Watch date changes to auto-update
watch([dateFrom, dateTo], () => {
    applyFilters();
});

// Quick date range presets
const activeRange = ref<string>('30d');

const setQuickRange = (range: string) => {
    activeRange.value = range;
    
    // Use production days mode - request N most recent days with production data
    let days = 30; // default
    switch (range) {
        case '1d':
            days = 1;
            break;
        case '7d':
            days = 7;
            break;
        case '30d':
            days = 30;
            break;
        case '90d':
            days = 90;
            break;
    }
    
    // Set mode and days, let backend calculate the actual date range
    oeeStore.setFilters({
        ...oeeStore.filters,
        mode: 'production_days',
        days: days,
    });
};
</script>

<template>
    <div class="flex flex-1 flex-col gap-3 p-3 sm:p-4 md:gap-6 md:p-6 transition-opacity duration-200" :class="{ 'opacity-60': oeeStore.loading }">
        <!-- Header & Filters -->
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4" v-if="!embedded && activeTab !== 'andon'">
            <div class="w-full md:w-auto">
                <div class="flex items-center gap-3">
                    <h2 class="text-lg sm:text-xl md:text-2xl lg:text-3xl font-bold tracking-tight mb-1">{{ contextTitle }}</h2>
                    <!-- Auto-refresh status badge -->
                    <Badge 
                        :variant="autoRefreshEnabled ? 'default' : 'outline'" 
                        class="h-5 sm:h-6 text-[10px] sm:text-xs font-mono cursor-pointer hover:opacity-80 transition-opacity"
                        @click="toggleAutoRefresh"
                    >
                        <RefreshCw :class="{ 'animate-spin': oeeStore.loading && autoRefreshEnabled }" class="h-3 w-3 mr-1" />
                        {{ autoRefreshEnabled ? 'Live' : 'Paused' }}
                    </Badge>
                </div>
                <p class="text-xs sm:text-sm text-muted-foreground hidden sm:block">
                    Real-time OEE monitoring and analysis. Last updated: {{ timeSinceUpdate }}
                </p>
            </div>
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full md:w-auto">
                 <!-- Auto-refresh controls -->
                 <div class="flex items-center gap-1 bg-muted rounded-lg p-1">
                     <Button 
                         size="sm" 
                         variant="ghost" 
                         class="h-7 w-7 p-0"
                         @click="toggleAutoRefresh"
                         :title="autoRefreshEnabled ? 'Pause auto-refresh' : 'Resume auto-refresh'"
                     >
                         <Pause v-if="autoRefreshEnabled" class="h-4 w-4" />
                         <Play v-else class="h-4 w-4" />
                     </Button>
                     <Button 
                         size="sm" 
                         variant="ghost" 
                         class="h-7 w-7 p-0"
                         @click="manualRefresh"
                         :disabled="oeeStore.loading"
                         title="Refresh now"
                     >
                         <RefreshCw :class="{ 'animate-spin': oeeStore.loading }" class="h-4 w-4" />
                     </Button>

                 </div>
                 
                 <div class="h-4 w-px bg-border hidden md:block mx-1"></div>
            
                 <!-- Filters -->
                 <select v-model="selectedPlant" @change="applyFilters" class="h-9 rounded-md border border-input bg-background text-foreground px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring w-full sm:min-w-[200px] sm:w-auto">
                    <option :value="null">All {{ plants }} Overview</option>
                    <option v-for="plantOption in oeeStore.options" :key="plantOption.id" :value="plantOption.id">{{ plantOption.name }}</option>
                 </select>

                 <div class="h-4 w-px bg-border hidden md:block mx-1"></div>

                 <!-- Quick Range Buttons -->
                 <div class="flex items-center gap-1 bg-muted rounded-lg p-1 w-full sm:w-auto">
                     <button 
                         v-for="range in [{key: '1d', label: '1D'}, {key: '7d', label: '7D'}, {key: '30d', label: '30D'}, {key: '90d', label: '90D'}]" 
                         :key="range.key"
                         @click="setQuickRange(range.key)"
                         class="px-3 py-1 text-xs font-medium rounded-md transition-colors flex-1 sm:flex-initial"
                         :class="activeRange === range.key ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                     >
                         {{ range.label }}
                     </button>
                 </div>

                 <div class="h-4 w-px bg-border hidden md:block mx-1"></div>

                 <div class="flex items-center gap-2 w-full sm:w-auto">
                     <input type="date" v-model="dateFrom" class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring flex-1 sm:flex-initial" />
                     <span class="text-muted-foreground">-</span>
                     <input type="date" v-model="dateTo" class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring flex-1 sm:flex-initial" />
                 </div>

            </div>
        </div>

        <!-- Embedded Header (Minimal) -->
        <div v-else-if="activeTab !== 'andon'" class="flex items-center justify-between pb-2 border-b">
             <div class="flex flex-col">
                <h3 class="text-xl font-bold">{{ contextTitle }}</h3>
                 <span class="text-xs text-muted-foreground" v-if="oeeStore.currentShift">{{ oeeStore.currentShift.name }} &bull; {{ oeeStore.currentShift.date }}</span>
             </div>
             <div class="flex items-center gap-2">
                 <!-- Quick Range Buttons (compact) -->
                 <div class="flex items-center gap-0.5 bg-muted rounded p-0.5">
                     <button 
                         v-for="range in [{key: '1d', label: '1D'}, {key: '7d', label: '7D'}, {key: '30d', label: '30D'}]" 
                         :key="range.key"
                         @click="setQuickRange(range.key)"
                         class="px-2 py-0.5 text-[10px] font-medium rounded transition-colors"
                         :class="activeRange === range.key ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                     >
                         {{ range.label }}
                     </button>
                 </div>
                 <input type="date" v-model="dateFrom" class="h-8 rounded-md border border-input bg-transparent px-2 py-0.5 text-xs shadow-sm" />
                 <span class="text-muted-foreground">-</span>
                 <input type="date" v-model="dateTo" class="h-8 rounded-md border border-input bg-transparent px-2 py-0.5 text-xs shadow-sm" />
                 
            </div>
       </div>

       <!-- Global Loading Bar (Top of website) -->
       <div v-if="oeeStore.loading" class="fixed top-0 left-0 right-0 h-[2px] z-50 bg-gradient-to-r from-blue-200 via-blue-500 to-blue-200 dark:from-blue-900 dark:via-blue-600 dark:to-blue-900 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/40 to-transparent animate-[shimmer_1.5s_infinite]" style="animation: shimmer 1.5s infinite;"></div>
       </div>

        <!-- Dashboard Tabs -->
        <Tabs v-model="activeTab" class="w-full">
            <div class="flex items-center justify-between mb-4">
                 <!-- Breadcrumb (Moved inside for layout) -->
                 <div v-if="selectedLine || selectedMachine" class="flex items-center gap-2 text-sm text-muted-foreground mr-4">
                    <button @click="resetToPlant" class="hover:text-primary hover:underline transition-colors">
                        {{ getPlantName(selectedPlant) }}
                    </button>
                    <span class="text-xs">/</span>
                    <button v-if="selectedLine" @click="resetToLine" :class="{'font-bold text-foreground': !selectedMachine, 'hover:text-primary hover:underline': selectedMachine}">
                        {{ getLineName(selectedLine) }}
                    </button>
                    <span v-if="selectedMachine" class="text-xs">/</span>
                    <span v-if="selectedMachine" class="font-bold text-foreground">
                        {{ getMachineName(selectedMachine) }}
                    </span>
                 </div>
                 <div v-else></div> <!-- Spacer -->

                <TabsList>
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="analytics" class="flex items-center gap-2">
                        <BarChart2 class="h-4 w-4" />
                        Analytics
                    </TabsTrigger>
                    <TabsTrigger value="andon" class="flex items-center gap-2">
                        <Activity class="h-4 w-4" />
                        Andon
                    </TabsTrigger>
                </TabsList>
            </div>

            <TabsContent value="overview" class="space-y-4">


        <!-- KPI Cards -->
        <div class="grid gap-3 sm:gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
            <Card class="relative overflow-hidden border-0 bg-gradient-to-br from-white/80 to-white/40 dark:from-neutral-900/80 dark:to-neutral-900/40 backdrop-blur-xl shadow-xl ring-1 ring-white/20 dark:ring-neutral-700/50 transition-all hover:scale-[1.02] theme-ocean:shadow-blue-500/20 theme-industrial:shadow-orange-500/30 theme-minimal:shadow-sm theme-minimal:border theme-minimal:border-border">
                <div class="absolute inset-0 bg-gradient-to-br from-blue-500/10 to-transparent opacity-50 pointer-events-none"></div>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 p-3 sm:p-2 pb-0 relative z-10">
                <CardTitle class="text-xs sm:text-[10px] md:text-xs font-bold uppercase tracking-wider text-blue-700 dark:text-blue-400">OEE</CardTitle>
                </CardHeader>
                <CardContent class="relative z-10 p-3 sm:p-2 pt-0">
                    <div class="flex justify-center py-1">
                         <ThemedOeeGauge :value="oeeStore.metrics.oee" label="OEE Score" :target="oeeStore.target?.target_oee" :show-target="!!oeeStore.target" size="md" class="sm:hidden" />
                         <ThemedOeeGauge :value="oeeStore.metrics.oee" label="OEE Score" :target="oeeStore.target?.target_oee" :show-target="!!oeeStore.target" size="lg" class="hidden sm:block" />
                    </div>
                </CardContent>
            </Card>

            <Card class="relative overflow-hidden border-0 bg-gradient-to-br from-white/80 to-white/40 dark:from-neutral-900/80 dark:to-neutral-900/40 backdrop-blur-xl shadow-xl ring-1 ring-white/20 dark:ring-neutral-700/50 transition-all hover:scale-[1.02] theme-ocean:shadow-teal-500/20 theme-industrial:shadow-yellow-500/30 theme-minimal:shadow-sm theme-minimal:border theme-minimal:border-border">
                 <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/10 to-transparent opacity-50 pointer-events-none"></div>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 p-3 sm:p-2 pb-0 relative z-10">
                    <CardTitle class="text-xs sm:text-[10px] md:text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Availability</CardTitle>
                </CardHeader>
                <CardContent class="relative z-10 p-3 sm:p-2 pt-0">
                     <div class="flex justify-center py-1">
                          <ThemedOeeGauge :value="oeeStore.metrics.availability" label="Availability" :target="oeeStore.target?.target_availability" :show-target="!!oeeStore.target" size="sm" class="sm:hidden" />
                          <ThemedOeeGauge :value="oeeStore.metrics.availability" label="Availability" :target="oeeStore.target?.target_availability" :show-target="!!oeeStore.target" size="md" class="hidden sm:block" />
                     </div>
                </CardContent>
            </Card>

            <Card class="relative overflow-hidden border-0 bg-gradient-to-br from-white/80 to-white/40 dark:from-neutral-900/80 dark:to-neutral-900/40 backdrop-blur-xl shadow-xl ring-1 ring-white/20 dark:ring-neutral-700/50 transition-all hover:scale-[1.02] theme-ocean:shadow-amber-500/20 theme-industrial:shadow-red-500/30 theme-minimal:shadow-sm theme-minimal:border theme-minimal:border-border">
                <div class="absolute inset-0 bg-gradient-to-br from-amber-500/10 to-transparent opacity-50 pointer-events-none"></div>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 p-3 sm:p-2 pb-0 relative z-10">
                    <CardTitle class="text-xs sm:text-[10px] md:text-xs font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Performance</CardTitle>
                </CardHeader>
                <CardContent class="relative z-10 p-3 sm:p-2 pt-0">
                     <div class="flex justify-center py-1">
                          <ThemedOeeGauge :value="oeeStore.metrics.performance" label="Performance" :target="oeeStore.target?.target_performance" :show-target="!!oeeStore.target" size="sm" class="sm:hidden" />
                          <ThemedOeeGauge :value="oeeStore.metrics.performance" label="Performance" :target="oeeStore.target?.target_performance" :show-target="!!oeeStore.target" size="md" class="hidden sm:block" />
                     </div>
                 </CardContent>
            </Card>

            <Card class="relative overflow-hidden border-0 bg-gradient-to-br from-white/80 to-white/40 dark:from-neutral-900/80 dark:to-neutral-900/40 backdrop-blur-xl shadow-xl ring-1 ring-white/20 dark:ring-neutral-700/50 transition-all hover:scale-[1.02] theme-ocean:shadow-purple-500/20 theme-industrial:shadow-pink-500/30 theme-minimal:shadow-sm theme-minimal:border theme-minimal:border-border">
                 <div class="absolute inset-0 bg-gradient-to-br from-purple-500/10 to-transparent opacity-50 pointer-events-none"></div>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 p-3 sm:p-2 pb-0 relative z-10">
                    <CardTitle class="text-xs sm:text-[10px] md:text-xs font-bold uppercase tracking-wider text-purple-700 dark:text-purple-400">Quality</CardTitle>
                </CardHeader>
                <CardContent class="relative z-10 p-3 sm:p-2 pt-0">
                     <div class="flex justify-center py-1">
                          <ThemedOeeGauge :value="oeeStore.metrics.quality" label="Quality" :target="oeeStore.target?.target_quality" :show-target="!!oeeStore.target" size="sm" class="sm:hidden" />
                          <ThemedOeeGauge :value="oeeStore.metrics.quality" label="Quality" :target="oeeStore.target?.target_quality" :show-target="!!oeeStore.target" size="md" class="hidden sm:block" />
                     </div>
                </CardContent>
            </Card>
        </div>

            <!-- Breakdown Grid: Visible when not at the leaf node (Machine) -->
        <div v-if="drillDownItems.length > 0 && !selectedMachine" class="animate-in fade-in slide-in-from-bottom-2 duration-500">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold tracking-tight">
                    {{ selectedLine ? `${machines} in ` + getLineName(selectedLine) : (selectedPlant ? `${lines} in ` + getPlantName(selectedPlant) : `All ${plants}`) }}
                </h3>
            </div>
            
            <div class="grid gap-3 sm:gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
                <button 
                    v-for="item in drillDownItems" 
                    :key="item.id"
                    @click="handleDrillDown(item)"
                    class="flex flex-col items-start p-4 rounded-xl border bg-card text-card-foreground shadow-sm hover:shadow-md hover:bg-accent/50 transition-all text-left group"
                >
                        <div class="flex items-center justify-between w-full mb-2">
                             <div class="font-semibold text-base sm:text-lg">{{ item.name }}</div>
                             <div class="text-[10px] sm:text-xs font-mono bg-muted px-2 py-0.5 rounded text-muted-foreground uppercase">{{ getAssetLabel(item.type) }}</div>
                        </div>

                        <!-- Mini Stats -->
                        <div class="w-full space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-muted-foreground">OEE</span>
                                <span :class="{'text-green-600': item.oee >= 85, 'text-red-500': item.oee < 85}" class="text-lg font-bold">
                                    {{ item.oee }}%
                                </span>
                            </div>
                            <div class="h-1.5 w-full bg-secondary rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500" 
                                     :class="{'bg-green-500': item.oee >= 85, 'bg-red-500': item.oee < 85}"
                                     :style="{ width: item.oee + '%' }">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-3 gap-1 pt-1 text-center" @click="console.log('Item data:', item)">
                                <div class="flex flex-col">
                                    <span class="text-[10px] text-muted-foreground uppercase">Availability</span>
                                    <span class="text-xs font-semibold">{{ item.availability }}%</span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[10px] text-muted-foreground uppercase">Performance</span>
                                    <span class="text-xs font-semibold">{{ item.performance }}%</span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[10px] text-muted-foreground uppercase">Quality</span>
                                    <span class="text-xs font-semibold">{{ item.quality }}%</span>
                                </div>
                            </div>
                        </div>
                    </button>
                </div>
            </div>

            <!-- OEE Comparison Chart (Shows at All Plants, Plant, or Line level) -->
            <div v-if="!selectedMachine && drillDownItems.length > 0" class="animate-in fade-in slide-in-from-bottom-2 duration-500">
                <Card class="border-0 bg-white/60 dark:bg-neutral-900/60 backdrop-blur-lg shadow-lg ring-1 ring-black/5 dark:ring-white/10">
                    <CardHeader>
                        <CardTitle class="text-sm sm:text-base md:text-lg lg:text-xl font-bold">
                            {{ selectedLine 
                                ? `${machines} OEE Comparison` 
                                : (selectedPlant ? 'Divisions OEE Comparison' : `${plants} OEE Comparison`) 
                            }}
                        </CardTitle>
                        <CardDescription class="text-xs sm:text-sm">
                            {{ selectedLine 
                                ? `Compare OEE performance across ${machines.toLowerCase()} within ${getLineName(selectedLine)}.`
                                : (selectedPlant 
                                    ? `Compare OEE performance across divisions within ${getPlantName(selectedPlant)}.` 
                                    : `Compare OEE performance across all ${plants.toLowerCase()}.`)
                            }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ThemedDivisionsComparisonChart :data="drillDownItems" />
                    </CardContent>
                </Card>
            </div>

            <!-- Global/Plant Charts -->
            <div class="grid gap-3 sm:gap-4 md:gap-6 grid-cols-1 lg:grid-cols-7">
                <Card class="border-0 bg-white/60 dark:bg-neutral-900/60 backdrop-blur-lg shadow-lg ring-1 ring-black/5 dark:ring-white/10 lg:col-span-4">
                    <CardHeader>
                        <CardTitle class="text-sm sm:text-base md:text-lg lg:text-xl font-bold">OEE Trend (30 Days)</CardTitle>
                        <CardDescription class="text-xs sm:text-sm">Daily OEE performance against target.</CardDescription>
                    </CardHeader>
                    <CardContent class="pl-2">
                        <ThemedTrendChart :data="oeeStore.trend" :target="oeeStore.target?.target_oee" />
                    </CardContent>
                </Card>
                
                <Card class="border-0 bg-white/60 dark:bg-neutral-900/60 backdrop-blur-lg shadow-lg ring-1 ring-black/5 dark:ring-white/10 lg:col-span-3">
                    <CardHeader>
                        <CardTitle class="text-sm sm:text-base md:text-lg lg:text-xl font-bold">Top Downtime Reasons</CardTitle>
                         <CardDescription class="text-xs sm:text-sm">Pareto analysis of stoppages.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ThemedDowntimeChart :data="oeeStore.downtime" />
                    </CardContent>
                </Card>
            </div>

            <!-- Material Loss Tracking (Full Width) -->
            <div v-if="oeeStore.materialLoss && oeeStore.materialLoss.total_count > 0" class="animate-in fade-in slide-in-from-bottom-2 duration-500">
                <MaterialLossTrendChart 
                    :summary="oeeStore.materialLoss"
                    :trendData="oeeStore.materialLossTrend"
                    :loading="oeeStore.loading"
                />
            </div>
            </TabsContent>

            <TabsContent value="andon" class="mt-4">
                <AndonBoard :plantId="selectedPlant" />
            </TabsContent>

            <TabsContent value="analytics" class="space-y-4 mt-4">
                
                <!-- Reliability Metrics Row -->
                <div class="grid gap-3 sm:gap-4 grid-cols-2 lg:grid-cols-4" v-if="oeeStore.reliability">
                    <Card class="bg-gradient-to-br from-blue-50 to-white dark:from-blue-900/20 dark:to-background border-blue-100 dark:border-blue-900/50">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs font-medium text-muted-foreground uppercase tracking-wider">MTBF</CardTitle>
                            <Activity class="h-4 w-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ oeeStore.reliability.mtbf }} <span class="text-xs font-normal text-muted-foreground">hrs</span></div>
                            <p class="text-xs text-muted-foreground">Mean Time Between Failures</p>
                        </CardContent>
                    </Card>
                    <Card class="bg-gradient-to-br from-amber-50 to-white dark:from-amber-900/20 dark:to-background border-amber-100 dark:border-amber-900/50">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs font-medium text-muted-foreground uppercase tracking-wider">MTTR</CardTitle>
                            <Clock class="h-4 w-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ oeeStore.reliability.mttr }} <span class="text-xs font-normal text-muted-foreground">min</span></div>
                            <p class="text-xs text-muted-foreground">Mean Time To Repair</p>
                        </CardContent>
                    </Card>
                     <Card class="bg-gradient-to-br from-red-50 to-white dark:from-red-900/20 dark:to-background border-red-100 dark:border-red-900/50">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Failures</CardTitle>
                            <AlertTriangle class="h-4 w-4 text-red-500" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ oeeStore.reliability.failures }}</div>
                            <p class="text-xs text-muted-foreground">Total Breakdown Events</p>
                        </CardContent>
                    </Card>
                     <Card class="bg-gradient-to-br from-green-50 to-white dark:from-green-900/20 dark:to-background border-green-100 dark:border-green-900/50">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Uptime</CardTitle>
                            <RefreshCw class="h-4 w-4 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ oeeStore.reliability.total_uptime_hours }} <span class="text-xs font-normal text-muted-foreground">hrs</span></div>
                            <p class="text-xs text-muted-foreground">Total Operating Time</p>
                        </CardContent>
                    </Card>
                </div>

                 <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Downtime Pareto -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base flex items-center gap-2">
                                <Pause class="h-4 w-4 text-amber-500" />
                                Downtime Pareto
                            </CardTitle>
                            <CardDescription>Top reasons for stoppages by duration</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="oeeStore.downtimeAnalysis && oeeStore.downtimeAnalysis.length > 0">
                                 <ThemedDowntimeChart :data="oeeStore.downtimeAnalysis" />
                            </div>
                            <div v-else class="h-64 flex flex-col items-center justify-center text-muted-foreground">
                                <Pause class="h-12 w-12 mb-4 opacity-20" />
                                <p>No downtime recorded for this period.</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Shift Comparison -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base flex items-center gap-2">
                                <BarChart2 class="h-4 w-4 text-indigo-500" />
                                Shift Comparison
                            </CardTitle>
                            <CardDescription>Total production output by shift</CardDescription>
                        </CardHeader>
                        <CardContent>
                             <div v-if="oeeStore.shiftAnalysis && oeeStore.shiftAnalysis.length > 0">
                                <ThemedShiftComparisonChart :data="oeeStore.shiftAnalysis" />
                             </div>
                              <div v-else class="h-64 flex flex-col items-center justify-center text-muted-foreground">
                                <BarChart2 class="h-12 w-12 mb-4 opacity-20" />
                                <p>No shift data available.</p>
                            </div>
                        </CardContent>
                    </Card>

                     <!-- OEE Components Trend -->
                    <Card>
                         <CardHeader>
                            <CardTitle class="text-base flex items-center gap-2">
                                <Activity class="h-4 w-4 text-emerald-500" />
                                OEE Components Trend
                            </CardTitle>
                            <CardDescription>Availability, Performance, and Quality over time</CardDescription>
                        </CardHeader>
                        <CardContent>
                             <div v-if="oeeStore.trend && oeeStore.trend.length > 0">
                                <ThemedOeeComponentsChart :data="oeeStore.trend" />
                             </div>
                              <div v-else class="h-64 flex flex-col items-center justify-center text-muted-foreground">
                                <Activity class="h-12 w-12 mb-4 opacity-20" />
                                <p>No trend data available.</p>
                            </div>
                        </CardContent>
                    </Card>

                     <!-- Reject Rate Trend -->
                    <Card>
                         <CardHeader>
                            <CardTitle class="text-base flex items-center gap-2">
                                <TrendingDown class="h-4 w-4 text-red-500" />
                                Reject Rate Analysis
                            </CardTitle>
                            <CardDescription>Percentage of rejected units over time</CardDescription>
                        </CardHeader>
                        <CardContent>
                             <div v-if="oeeStore.trend && oeeStore.trend.some(d => (d.total_count || 0) > 0)">
                                <ThemedRejectRateChart :data="oeeStore.trend" />
                             </div>
                              <div v-else class="h-64 flex flex-col items-center justify-center text-muted-foreground">
                                <TrendingDown class="h-12 w-12 mb-4 opacity-20" />
                                <p>No reject data available.</p>
                            </div>
                        </CardContent>
                    </Card>
                 </div>

                 <!-- Advanced Analytics Section -->
                 <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 pt-4">
                    <Card class="col-span-1 lg:col-span-1">
                        <CardHeader>
                            <CardTitle>Loss Waterfall</CardTitle>
                            <CardDescription>Detailed breakdown of time losses.</CardDescription>
                        </CardHeader>
                        <CardContent>
                             <WaterfallChart :data="waterfallData" />
                        </CardContent>
                    </Card>

                    <Card class="col-span-1 lg:col-span-1">
                        <CardHeader>
                            <CardTitle>Cycle Time Scatter</CardTitle>
                            <CardDescription>Cycle time consistency analysis.</CardDescription>
                        </CardHeader>
                        <CardContent>
                             <ScatterChart :data="scatterData" />
                        </CardContent>
                    </Card>
                 </div>
            </TabsContent>
        </Tabs>
    </div>
</template>
