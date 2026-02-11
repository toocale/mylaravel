<script setup lang="ts">
import { computed, watch, onMounted } from 'vue';
import { useOeeStore } from '@/stores/oee';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { TrendingUp, TrendingDown, Activity, Clock, AlertTriangle, Zap, Target, Droplets, Settings2 } from 'lucide-vue-next';
import { useTerminology } from '@/composables/useTerminology';

const oeeStore = useOeeStore();
const { plant, line, machine } = useTerminology();

const props = defineProps<{
    plantId?: number | null;
    lineId?: number | null;
    machineId?: number | null;
}>();

// Fetch data on mount and when context changes
const fetchData = () => {
    const defaultDateFrom = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    const defaultDateTo = new Date().toISOString().split('T')[0];
    
    oeeStore.setFilters({
        plantId: props.plantId ?? null,
        lineId: props.lineId ?? null,
        machineId: props.machineId ?? null,
        dateFrom: defaultDateFrom,
        dateTo: defaultDateTo
    });
};

onMounted(() => {
    fetchData();
});

watch(() => [props.plantId, props.lineId, props.machineId], () => {
    fetchData();
}, { deep: true });

// Computed metrics
const metrics = computed(() => oeeStore.metrics);
const breakdown = computed(() => oeeStore.breakdown || []);
const downtimeData = computed(() => oeeStore.downtimeAnalysis || []);
const shiftData = computed(() => oeeStore.shiftAnalysis || []);
const trend = computed(() => oeeStore.trend || []);
const target = computed(() => oeeStore.target);

// Calculate pie chart segments for OEE components
const pieSegments = computed(() => {
    const total = metrics.value.availability + metrics.value.performance + metrics.value.quality;
    if (total === 0) return [];
    
    let startAngle = 0;
    const segments = [
        { name: 'Availability', value: metrics.value.availability, color: '#3b82f6' },
        { name: 'Performance', value: metrics.value.performance, color: '#06b6d4' },
        { name: 'Quality', value: metrics.value.quality, color: '#10b981' },
    ];
    
    return segments.map(seg => {
        const percentage = seg.value / total;
        const angle = percentage * 360;
        const segment = {
            ...seg,
            startAngle,
            endAngle: startAngle + angle,
            percentage: (percentage * 100).toFixed(1)
        };
        startAngle += angle;
        return segment;
    });
});

// Calculate arc path for pie chart
const getArcPath = (startAngle: number, endAngle: number, radius: number, innerRadius: number) => {
    const startRad = (startAngle - 90) * Math.PI / 180;
    const endRad = (endAngle - 90) * Math.PI / 180;
    
    const x1 = 100 + radius * Math.cos(startRad);
    const y1 = 100 + radius * Math.sin(startRad);
    const x2 = 100 + radius * Math.cos(endRad);
    const y2 = 100 + radius * Math.sin(endRad);
    
    const x3 = 100 + innerRadius * Math.cos(endRad);
    const y3 = 100 + innerRadius * Math.sin(endRad);
    const x4 = 100 + innerRadius * Math.cos(startRad);
    const y4 = 100 + innerRadius * Math.sin(startRad);
    
    const largeArc = endAngle - startAngle > 180 ? 1 : 0;
    
    return `M ${x1} ${y1} A ${radius} ${radius} 0 ${largeArc} 1 ${x2} ${y2} L ${x3} ${y3} A ${innerRadius} ${innerRadius} 0 ${largeArc} 0 ${x4} ${y4} Z`;
};

// Wave animation for OEE display
const waveHeight = computed(() => (metrics.value.oee / 100) * 120);

// Status badges
const getStatusColor = (value: number) => {
    if (value >= 85) return 'bg-emerald-500';
    if (value >= 60) return 'bg-amber-500';
    return 'bg-red-500';
};

const getStatusText = (value: number) => {
    if (value >= 85) return 'Excellent';
    if (value >= 60) return 'Good';
    return 'Needs Attention';
};

// Recent trend direction
const trendDirection = computed(() => {
    if (trend.value.length < 2) return 'neutral';
    const recent = trend.value.slice(-7);
    const avgRecent = recent.reduce((a, b) => a + b.oee, 0) / recent.length;
    const avgPrev = trend.value.slice(-14, -7).reduce((a, b) => a + b.oee, 0) / Math.max(1, trend.value.slice(-14, -7).length);
    return avgRecent > avgPrev ? 'up' : avgRecent < avgPrev ? 'down' : 'neutral';
});
</script>

<template>
    <div class="ocean-dashboard space-y-6 transition-opacity duration-200" :class="{ 'opacity-60': oeeStore.loading }">
        <!-- Hero Section with Liquid OEE Display -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-600 via-cyan-500 to-teal-400 p-8 text-white shadow-2xl">
            <div class="absolute inset-0 opacity-30">
                <svg class="w-full h-full" preserveAspectRatio="none" viewBox="0 0 400 200">
                    <defs>
                        <linearGradient id="waveGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" style="stop-color:rgba(255,255,255,0.4)" />
                            <stop offset="100%" style="stop-color:rgba(255,255,255,0.1)" />
                        </linearGradient>
                    </defs>
                    <path 
                        :d="`M0,${200 - waveHeight} Q100,${180 - waveHeight} 200,${200 - waveHeight} T400,${200 - waveHeight} V200 H0 Z`" 
                        fill="url(#waveGradient)"
                        class="animate-pulse"
                    />
                </svg>
            </div>
            
            <div class="relative z-10 flex flex-col lg:flex-row items-center justify-between gap-8">
                <!-- Main OEE Score -->
                <div class="text-center lg:text-left">
                    <div class="flex items-baseline gap-2 justify-center lg:justify-start">
                        <span class="text-7xl lg:text-8xl font-black tracking-tight">{{ metrics.oee.toFixed(1) }}</span>
                        <span class="text-3xl font-light opacity-80">%</span>
                    </div>
                    <p class="text-xl font-medium opacity-90 mt-2">Overall Equipment Effectiveness</p>
                    <div class="flex items-center gap-3 mt-4 justify-center lg:justify-start">
                        <Badge :class="getStatusColor(metrics.oee)" class="text-white px-4 py-1.5 text-sm font-semibold">
                            {{ getStatusText(metrics.oee) }}
                        </Badge>
                        <Badge v-if="trendDirection !== 'neutral'" variant="outline" class="border-white/50 text-white">
                            <component :is="trendDirection === 'up' ? TrendingUp : TrendingDown" class="h-4 w-4 mr-1" />
                            {{ trendDirection === 'up' ? 'Trending Up' : 'Trending Down' }}
                        </Badge>
                    </div>
                </div>
                
                <!-- Donut Pie Chart for Components -->
                <div class="relative">
                    <svg width="200" height="200" viewBox="0 0 200 200">
                        <defs>
                            <filter id="oceanGlow">
                                <feGaussianBlur stdDeviation="3" result="blur"/>
                                <feComposite in="SourceGraphic" in2="blur" operator="over"/>
                            </filter>
                        </defs>
                        
                        <!-- Pie segments -->
                        <g filter="url(#oceanGlow)">
                            <path 
                                v-for="(seg, idx) in pieSegments" 
                                :key="idx"
                                :d="getArcPath(seg.startAngle, seg.endAngle, 85, 55)"
                                :fill="seg.color"
                                class="transition-all duration-700 hover:opacity-80 cursor-pointer"
                                :class="{ 'drop-shadow-lg': true }"
                            />
                        </g>
                        
                        <!-- Center circle -->
                        <circle cx="100" cy="100" r="50" fill="rgba(255,255,255,0.15)" />
                        <text x="100" y="95" text-anchor="middle" class="fill-white text-2xl font-bold">OEE</text>
                        <text x="100" y="115" text-anchor="middle" class="fill-white/80 text-sm">Components</text>
                    </svg>
                    
                    <!-- Legend -->
                    <div class="flex gap-4 mt-4 text-sm justify-center">
                        <div v-for="seg in pieSegments" :key="seg.name" class="flex items-center gap-1.5">
                            <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: seg.color }"></div>
                            <span class="opacity-90">{{ seg.name }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Component Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Availability Card -->
            <Card class="group relative overflow-hidden border-0 bg-white/80 dark:bg-slate-800/80 backdrop-blur-xl shadow-lg hover:shadow-xl transition-all hover:-translate-y-1">
                <div class="absolute inset-0 bg-gradient-to-br from-blue-500/10 to-transparent"></div>
                <CardHeader class="pb-2 relative z-10">
                    <CardTitle class="flex items-center gap-2 text-blue-600 dark:text-blue-400">
                        <Clock class="h-5 w-5" />
                        Availability
                    </CardTitle>
                </CardHeader>
                <CardContent class="relative z-10">
                    <div class="flex items-end gap-2">
                        <span class="text-4xl font-black text-foreground">{{ metrics.availability.toFixed(1) }}</span>
                        <span class="text-xl text-muted-foreground mb-1">%</span>
                    </div>
                    <!-- Mini bar chart -->
                    <div class="mt-4 h-2 bg-blue-100 dark:bg-blue-900/50 rounded-full overflow-hidden">
                        <div 
                            class="h-full bg-gradient-to-r from-blue-500 to-blue-400 rounded-full transition-all duration-1000"
                            :style="{ width: `${metrics.availability}%` }"
                        ></div>
                    </div>
                    <p class="text-xs text-muted-foreground mt-2">Running time vs planned time</p>
                </CardContent>
            </Card>
            
            <!-- Performance Card -->
            <Card class="group relative overflow-hidden border-0 bg-white/80 dark:bg-slate-800/80 backdrop-blur-xl shadow-lg hover:shadow-xl transition-all hover:-translate-y-1">
                <div class="absolute inset-0 bg-gradient-to-br from-cyan-500/10 to-transparent"></div>
                <CardHeader class="pb-2 relative z-10">
                    <CardTitle class="flex items-center gap-2 text-cyan-600 dark:text-cyan-400">
                        <Zap class="h-5 w-5" />
                        Performance
                    </CardTitle>
                </CardHeader>
                <CardContent class="relative z-10">
                    <div class="flex items-end gap-2">
                        <span class="text-4xl font-black text-foreground">{{ metrics.performance.toFixed(1) }}</span>
                        <span class="text-xl text-muted-foreground mb-1">%</span>
                    </div>
                    <div class="mt-4 h-2 bg-cyan-100 dark:bg-cyan-900/50 rounded-full overflow-hidden">
                        <div 
                            class="h-full bg-gradient-to-r from-cyan-500 to-cyan-400 rounded-full transition-all duration-1000"
                            :style="{ width: `${metrics.performance}%` }"
                        ></div>
                    </div>
                    <p class="text-xs text-muted-foreground mt-2">Actual vs theoretical output</p>
                </CardContent>
            </Card>
            
            <!-- Quality Card -->
            <Card class="group relative overflow-hidden border-0 bg-white/80 dark:bg-slate-800/80 backdrop-blur-xl shadow-lg hover:shadow-xl transition-all hover:-translate-y-1">
                <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/10 to-transparent"></div>
                <CardHeader class="pb-2 relative z-10">
                    <CardTitle class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                        <Target class="h-5 w-5" />
                        Quality
                    </CardTitle>
                </CardHeader>
                <CardContent class="relative z-10">
                    <div class="flex items-end gap-2">
                        <span class="text-4xl font-black text-foreground">{{ metrics.quality.toFixed(1) }}</span>
                        <span class="text-xl text-muted-foreground mb-1">%</span>
                    </div>
                    <div class="mt-4 h-2 bg-emerald-100 dark:bg-emerald-900/50 rounded-full overflow-hidden">
                        <div 
                            class="h-full bg-gradient-to-r from-emerald-500 to-emerald-400 rounded-full transition-all duration-1000"
                            :style="{ width: `${metrics.quality}%` }"
                        ></div>
                    </div>
                    <p class="text-xs text-muted-foreground mt-2">Good units vs total produced</p>
                </CardContent>
            </Card>
        </div>
        
        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- OEE Trend Sparkline -->
            <Card class="border-0 bg-white/80 dark:bg-slate-800/80 backdrop-blur-xl shadow-lg">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Activity class="h-5 w-5 text-blue-500" />
                        OEE Trend
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="h-48 flex items-end gap-1">
                        <div 
                            v-for="(point, idx) in trend.slice(-30)" 
                            :key="idx"
                            class="flex-1 bg-gradient-to-t from-blue-500 to-cyan-400 rounded-t transition-all duration-300 hover:opacity-80 cursor-pointer relative group"
                            :style="{ height: `${point.oee}%` }"
                        >
                            <div class="absolute -top-8 left-1/2 -translate-x-1/2 bg-slate-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10">
                                {{ point.date }}: {{ point.oee.toFixed(1) }}%
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-between text-xs text-muted-foreground mt-2">
                        <span>{{ trend[0]?.date || '—' }}</span>
                        <span>{{ trend[trend.length - 1]?.date || '—' }}</span>
                    </div>
                </CardContent>
            </Card>
            
            <!-- Downtime Breakdown -->
            <Card class="border-0 bg-white/80 dark:bg-slate-800/80 backdrop-blur-xl shadow-lg">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <AlertTriangle class="h-5 w-5 text-amber-500" />
                        Top Downtime Reasons
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-3">
                        <div 
                            v-for="(item, idx) in downtimeData.slice(0, 5)" 
                            :key="idx"
                            class="flex items-center gap-3"
                        >
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center text-white text-sm font-bold">
                                {{ idx + 1 }}
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="font-medium text-sm truncate">{{ item.description }}</span>
                                    <span class="text-sm text-muted-foreground">{{ item.total_duration }}min</span>
                                </div>
                                <div class="h-1.5 bg-amber-100 dark:bg-amber-900/30 rounded-full overflow-hidden">
                                    <div 
                                        class="h-full bg-gradient-to-r from-amber-500 to-orange-400 rounded-full"
                                        :style="{ width: `${Math.min(100, (item.total_duration / (downtimeData[0]?.total_duration || 1)) * 100)}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                        <div v-if="downtimeData.length === 0" class="text-center text-muted-foreground py-8">
                            <AlertTriangle class="h-8 w-8 mx-auto mb-2 opacity-50" />
                            <p>No downtime recorded</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
        
        <!-- Breakdown Grid (if available) -->
        <Card v-if="breakdown.length > 0" class="border-0 bg-white/80 dark:bg-slate-800/80 backdrop-blur-xl shadow-lg">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Settings2 class="h-5 w-5 text-blue-500" />
                    Asset Breakdown
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <div 
                        v-for="item in breakdown" 
                        :key="item.id"
                        class="p-4 rounded-xl bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-700/50 dark:to-slate-800/50 border border-slate-200 dark:border-slate-600 hover:shadow-md transition-all cursor-pointer"
                    >
                        <div class="text-sm font-medium text-muted-foreground mb-1">{{ item.name }}</div>
                        <div class="text-2xl font-bold" :class="item.oee >= 85 ? 'text-emerald-600' : item.oee >= 60 ? 'text-amber-600' : 'text-red-600'">
                            {{ item.oee.toFixed(1) }}%
                        </div>
                        <div class="mt-2 h-1 bg-slate-200 dark:bg-slate-600 rounded-full overflow-hidden">
                            <div 
                                class="h-full rounded-full transition-all duration-500"
                                :class="item.oee >= 85 ? 'bg-emerald-500' : item.oee >= 60 ? 'bg-amber-500' : 'bg-red-500'"
                                :style="{ width: `${item.oee}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

<style scoped>
.ocean-dashboard {
    font-family: 'Inter', sans-serif;
}

@keyframes wave {
    0%, 100% { transform: translateX(0); }
    50% { transform: translateX(-10px); }
}
</style>
