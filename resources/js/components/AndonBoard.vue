<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import axios from 'axios';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    RefreshCw, Maximize2, Minimize2, CheckCircle, AlertTriangle, XCircle, Info,
    Radio, Pause, Activity, Clock, Factory, ChevronDown, ChevronUp, Bell
} from 'lucide-vue-next';

const props = defineProps<{
    plantId?: number | null;
}>();

// ============ STATE ============
const machines = ref<any[]>([]);
const grouped = ref<Record<string, any[]>>({});
const summary = ref({ total: 0, running: 0, stopped: 0, idle: 0 });
const alerts = ref<any[]>([]);
const loading = ref(false);
const isFullscreen = ref(false);
const showAlerts = ref(false);
const selectedPlant = ref<number | null>(props.plantId || null);
const plants = ref<any[]>([]); // Kept for reference, though dropdown is hidden if prop is used
const error = ref<string | null>(null);

// Auto-refresh
const refreshInterval = ref(10000);
let refreshTimer: ReturnType<typeof setInterval> | null = null;
const lastUpdated = ref<Date | null>(null);
const timeSinceUpdate = ref('Never');

// Watch prop for external filter changes
watch(() => props.plantId, (newVal) => {
    selectedPlant.value = newVal || null;
    fetchStatus();
});

// ============ DATA FETCHING ============
const fetchStatus = async () => {
    loading.value = true;
    try {
        const params: any = {};
        if (selectedPlant.value) params.plant_id = selectedPlant.value;

        const [statusRes, alertsRes] = await Promise.all([
            axios.get('/api/v1/andon/status', { params }),
            axios.get('/api/v1/andon/alerts', { params: { active_only: true } }),
        ]);

        machines.value = statusRes.data.machines || [];
        grouped.value = statusRes.data.grouped || {};
        summary.value = statusRes.data.summary || { total: 0, running: 0, stopped: 0, idle: 0 };
        alerts.value = alertsRes.data.alerts || [];

        // Extract unique plants (optional if not using dropdown)
        const plantMap = new Map();
        machines.value.forEach((m: any) => {
            if (m.plant_id && m.plant_name) {
                plantMap.set(m.plant_id, m.plant_name);
            }
        });
        plants.value = Array.from(plantMap, ([id, name]) => ({ id, name }));

        lastUpdated.value = new Date();
    } catch (e: any) {
        console.error('Andon fetch error:', e);
        error.value = e.response?.data?.message || e.message || 'Unknown error occurred';
    } finally {
        loading.value = false;
    }
};

const acknowledgeAlert = async (alertId: number) => {
    try {
        await axios.post(`/api/v1/andon/alerts/${alertId}/acknowledge`);
        await fetchStatus();
    } catch (e) {
        console.error('Acknowledge error:', e);
    }
};

const resolveAlert = async (alertId: number) => {
    try {
        await axios.post(`/api/v1/andon/alerts/${alertId}/resolve`);
        await fetchStatus();
    } catch (e) {
        console.error('Resolve error:', e);
    }
};

// ============ STATUS HELPERS ============
const statusConfig: Record<string, any> = {
    running: { color: 'bg-emerald-500', text: 'Running', glow: 'shadow-emerald-500/40', border: 'border-emerald-500/30', textColor: 'text-emerald-400' },
    stopped: { color: 'bg-red-500', text: 'Stopped', glow: 'shadow-red-500/40', border: 'border-red-500/30', textColor: 'text-red-400', pulse: true },
    idle: { color: 'bg-neutral-400', text: 'Idle', glow: '', border: 'border-neutral-500/30', textColor: 'text-neutral-400' },
};

const getStatusConfig = (status: string) => statusConfig[status] || statusConfig.idle;

const severityConfig: Record<string, any> = {
    critical: { icon: XCircle, color: 'text-red-500', bg: 'bg-red-500/10 border-red-500/30', badge: 'destructive' },
    warning: { icon: AlertTriangle, color: 'text-amber-500', bg: 'bg-amber-500/10 border-amber-500/30', badge: 'secondary' },
    info: { icon: Info, color: 'text-blue-500', bg: 'bg-blue-500/10 border-blue-500/30', badge: 'outline' },
};

const getSeverityConfig = (severity: string) => severityConfig[severity] || severityConfig.info;

// ============ FULLSCREEN ============
const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
        isFullscreen.value = true;
    } else {
        document.exitFullscreen();
        isFullscreen.value = false;
    }
};

// ============ AUTO-REFRESH ============
const startAutoRefresh = () => {
    if (refreshTimer) clearInterval(refreshTimer);
    refreshTimer = setInterval(fetchStatus, refreshInterval.value);
};

const updateTimeSinceUpdate = () => {
    if (!lastUpdated.value) { timeSinceUpdate.value = 'Never'; return; }
    const seconds = Math.floor((Date.now() - lastUpdated.value.getTime()) / 1000);
    if (seconds < 60) timeSinceUpdate.value = `${seconds}s ago`;
    else timeSinceUpdate.value = `${Math.floor(seconds / 60)}m ago`;
};

const activeAlertCount = computed(() => alerts.value.filter((a: any) => !a.resolved_at).length);

// ============ LIFECYCLE ============
let timeUpdateTimer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    fetchStatus();
    startAutoRefresh();
    timeUpdateTimer = setInterval(updateTimeSinceUpdate, 1000);
});

onUnmounted(() => {
    if (refreshTimer) clearInterval(refreshTimer);
    if (timeUpdateTimer) clearInterval(timeUpdateTimer);
});

const formatTimeAgo = (dateStr: string) => {
    if (!dateStr) return '';
    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 60000);
    if (diff < 1) return 'just now';
    if (diff < 60) return `${diff}m ago`;
    return `${Math.floor(diff / 60)}h ${diff % 60}m ago`;
};
</script>

<template>
    <div class="flex flex-1 flex-col gap-4" :class="{ 'bg-neutral-950 min-h-screen p-4 md:p-6 fixed inset-0 z-50 overflow-y-auto': isFullscreen }">

        <!-- Header Bar -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                    <Radio class="h-6 w-6 text-emerald-500 animate-pulse" />
                    <h2 class="text-xl md:text-2xl font-bold tracking-tight">Live Status</h2>
                </div>
                <Badge variant="outline" class="font-mono text-xs">
                    <RefreshCw :class="{ 'animate-spin': loading }" class="h-3 w-3 mr-1" />
                    {{ timeSinceUpdate }}
                </Badge>
            </div>
            <div class="flex items-center gap-2">
                <!-- Refresh Button -->
                <Button size="sm" variant="outline" @click="fetchStatus()" :disabled="loading">
                    <RefreshCw :class="{ 'animate-spin': loading }" class="h-4 w-4" />
                </Button>

                <!-- Fullscreen -->
                <Button size="sm" variant="outline" @click="toggleFullscreen">
                    <Maximize2 v-if="!isFullscreen" class="h-4 w-4" />
                    <Minimize2 v-else class="h-4 w-4" />
                </Button>
            </div>
        </div>

        <!-- Summary Strip -->
        <div class="grid grid-cols-4 gap-3">
            <div class="flex items-center gap-3 rounded-xl border bg-card p-3 shadow-sm">
                <Factory class="h-5 w-5 text-muted-foreground" />
                <div>
                    <div class="text-2xl font-bold">{{ summary.total }}</div>
                    <div class="text-xs text-muted-foreground uppercase tracking-wider">Total</div>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-3 shadow-sm">
                <div class="h-3 w-3 rounded-full bg-emerald-500"></div>
                <div>
                    <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ summary.running }}</div>
                    <div class="text-xs text-muted-foreground uppercase tracking-wider">Running</div>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-xl border border-red-500/20 bg-red-500/5 p-3 shadow-sm">
                <div class="h-3 w-3 rounded-full bg-red-500 animate-pulse"></div>
                <div>
                    <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ summary.stopped }}</div>
                    <div class="text-xs text-muted-foreground uppercase tracking-wider">Stopped</div>
                </div>
            </div>

            <div class="flex items-center gap-3 rounded-xl border p-3 shadow-sm">
                <div class="h-3 w-3 rounded-full bg-neutral-400"></div>
                <div>
                    <div class="text-2xl font-bold text-muted-foreground">{{ summary.idle }}</div>
                    <div class="text-xs text-muted-foreground uppercase tracking-wider">Idle</div>
                </div>
            </div>
        </div>

        <!-- Machine Grid (grouped by plant) -->
        <div v-for="(plantMachines, plantName) in grouped" :key="plantName" class="space-y-3">
            <h2 class="text-lg font-semibold tracking-tight flex items-center gap-2">
                <Factory class="h-5 w-5 text-muted-foreground" />
                {{ plantName }}
            </h2>
            <div class="grid gap-3 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                <div
                    v-for="machine in plantMachines"
                    :key="machine.id"
                    class="relative rounded-xl border-2 p-4 transition-all duration-300"
                    :class="[
                        getStatusConfig(machine.status).border,
                        getStatusConfig(machine.status).pulse ? 'animate-pulse' : '',
                        'bg-card hover:shadow-lg',
                        getStatusConfig(machine.status).glow ? 'shadow-md ' + getStatusConfig(machine.status).glow : ''
                    ]"
                >
                    <!-- Status indicator dot -->
                    <div class="absolute top-3 right-3 flex items-center gap-1.5">
                        <span v-if="machine.active_alerts > 0" class="flex items-center gap-1">
                            <AlertTriangle class="h-3.5 w-3.5 text-amber-500" />
                        </span>
                        <div class="h-3 w-3 rounded-full" :class="[getStatusConfig(machine.status).color, getStatusConfig(machine.status).pulse ? 'animate-pulse' : '']"></div>
                    </div>

                    <!-- Machine Name -->
                    <div class="mb-3">
                        <div class="text-base font-bold truncate pr-12">{{ machine.name }}</div>
                        <div class="text-xs text-muted-foreground">{{ machine.line_name }}</div>
                    </div>

                    <!-- Status Badge -->
                    <div class="mb-3">
                        <Badge
                            :variant="machine.status === 'stopped' ? 'destructive' : (machine.status === 'running' ? 'default' : 'secondary')"
                            class="text-xs font-semibold uppercase tracking-wider"
                        >
                            {{ getStatusConfig(machine.status).text }}
                        </Badge>
                    </div>

                    <!-- Details -->
                    <div class="space-y-1 text-xs">
                        <div v-if="machine.product" class="flex items-center gap-1.5 text-muted-foreground">
                            <Activity class="h-3 w-3 shrink-0" />
                            <span class="truncate">{{ machine.product }}</span>
                        </div>
                        <div v-if="machine.throughput" class="flex items-center gap-1.5 text-muted-foreground">
                            <RefreshCw class="h-3 w-3 shrink-0" />
                            <span>{{ machine.throughput }}/hr</span>
                            <span class="text-muted-foreground/50">| {{ machine.good_count }} good, {{ machine.reject_count }} rej</span>
                        </div>
                        <div v-if="machine.shift" class="flex items-center gap-1.5 text-muted-foreground">
                            <Clock class="h-3 w-3 shrink-0" />
                            <span>{{ machine.shift.name }} · {{ machine.shift.started_at }}</span>
                        </div>
                        <div v-if="machine.shift?.operator" class="flex items-center gap-1.5 text-muted-foreground pl-[18px]">
                            <span>{{ machine.shift.operator }}</span>
                        </div>
                    </div>

                    <!-- Downtime Banner -->
                    <div v-if="machine.status === 'stopped'" class="mt-3 -mx-4 -mb-4 px-4 py-2.5 rounded-b-[10px] bg-red-500/10 border-t border-red-500/20">
                        <div class="flex items-center justify-between">
                            <div class="text-xs font-semibold text-red-500">{{ machine.downtime_reason }}</div>
                            <div class="text-xs font-mono text-red-400/80">{{ machine.downtime_minutes }}m</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="Object.keys(grouped).length === 0 && !loading" class="flex flex-col items-center justify-center py-20">
            <Factory class="h-16 w-16 text-muted-foreground/20 mb-4" />
            <p class="text-muted-foreground">No machines found. Configure your assets to see them here.</p>
            <div v-if="error" class="mt-4 p-3 bg-red-500/10 text-red-500 rounded-lg text-sm font-mono max-w-lg overflow-auto">
                {{ error }}
            </div>
        </div>

        <!-- Active Alerts Panel -->
        <div v-if="showAlerts && alerts.length > 0" class="mt-2">
            <Card class="border-amber-500/20">
                <CardHeader class="pb-3 cursor-pointer" @click="showAlerts = !showAlerts">
                    <CardTitle class="text-base flex items-center gap-2">
                        <Bell class="h-4 w-4 text-amber-500" />
                        Active Alerts ({{ activeAlertCount }})
                        <ChevronUp class="h-4 w-4 ml-auto text-muted-foreground" />
                    </CardTitle>
                </CardHeader>
                <CardContent class="pt-0">
                    <div class="space-y-2 max-h-64 overflow-y-auto">
                        <div
                            v-for="alert in alerts.filter(a => !a.resolved_at)"
                            :key="alert.id"
                            class="flex items-start justify-between gap-3 p-3 rounded-lg border"
                            :class="getSeverityConfig(alert.severity).bg"
                        >
                            <div class="flex items-start gap-2 min-w-0">
                                <component :is="getSeverityConfig(alert.severity).icon" class="h-4 w-4 mt-0.5 shrink-0" :class="getSeverityConfig(alert.severity).color" />
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold truncate">{{ alert.title }}</div>
                                    <div class="text-xs text-muted-foreground mt-0.5">{{ alert.message }}</div>
                                    <div class="text-xs text-muted-foreground/60 mt-1">
                                        {{ formatTimeAgo(alert.triggered_at) }}
                                        <span v-if="alert.acknowledged_at" class="ml-2 text-emerald-500">
                                            ✓ Acknowledged by {{ alert.acknowledged_by_user?.name || 'Unknown' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <Button v-if="!alert.acknowledged_at" size="sm" variant="outline" class="h-7 text-xs" @click="acknowledgeAlert(alert.id)">
                                    <CheckCircle class="h-3 w-3 mr-1" /> Ack
                                </Button>
                                <Button size="sm" variant="ghost" class="h-7 text-xs" @click="resolveAlert(alert.id)">
                                    Resolve
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
