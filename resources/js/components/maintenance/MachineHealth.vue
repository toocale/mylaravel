<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { AlertCircle, Wrench, Package, Clock, TrendingUp, TrendingDown, Calendar, Download, FileText, FileSpreadsheet, Printer } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import MaintenanceScheduleList from './MaintenanceScheduleList.vue';
import ComponentsList from './ComponentsList.vue';
import MaintenanceHistory from './MaintenanceHistory.vue';
import SparePartsInventory from './SparePartsInventory.vue';
import MaintenanceCalendar from './MaintenanceCalendar.vue';
import { useToast } from '@/components/ui/toast/use-toast';
import axios from 'axios';

const props = defineProps<{
    machineId: number;
}>();

// State
const loading = ref(false);
const healthData = ref<any>(null);
const activeTab = ref('overview');
const { toast } = useToast();

// Computed
const complianceColor = computed(() => {
    if (!healthData.value) return 'gray';
    const rate = healthData.value.compliance_rate;
    if (rate >= 90) return 'green';
    if (rate >= 70) return 'yellow';
    return 'red';
});

const mtbfTrend = computed(() => {
    // Simplified trend indicator
    return 'up'; // Would compare to historical data
});

// Methods
const fetchHealthData = async () => {
    loading.value = true;
    try {
        const response = await axios.get(`/api/v1/machines/${props.machineId}/health`);
        healthData.value = response.data;
    } catch (error) {
        console.error('Failed to fetch health data:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchHealthData();
});

// Export functions
const exportSchedules = async (format: 'pdf' | 'excel') => {
    try {
        toast({
            title: 'Exporting Schedules',
            description: `Preparing ${format.toUpperCase()} file...`,
        });

        const response = await axios.get(`/api/v1/machines/${props.machineId}/maintenance/export/schedules/${format}`, {
            responseType: 'blob'
        });

        downloadFile(response.data, `maintenance-schedules-${Date.now()}.${format === 'excel' ? 'xlsx' : format}`);

        toast({
            title: 'Export Successful',
            description: 'Schedules exported successfully',
        });
    } catch (error) {
        toast({
            title: 'Export Failed',
            description: 'Failed to export schedules',
            variant: 'destructive',
        });
    }
};

const exportHistory = async () => {
    try {
        toast({
            title: 'Exporting History',
            description: 'Preparing Excel file...',
        });

        const response = await axios.get(`/api/v1/machines/${props.machineId}/maintenance/export/history`, {
            responseType: 'blob'
        });

        downloadFile(response.data, `maintenance-history-${Date.now()}.xlsx`);

        toast({
            title: 'Export Successful',
            description: 'History exported successfully',
        });
    } catch (error) {
        toast({
            title: 'Export Failed',
            description: 'Failed to export history',
            variant: 'destructive',
        });
    }
};

const printComponents = () => {
    window.print();
};

const downloadFile = (data: Blob, filename: string) => {
    const url = window.URL.createObjectURL(new Blob([data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    setTimeout(() => {
        link.remove();
        window.URL.revokeObjectURL(url);
    }, 100);
};
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div>
            <h3 class="text-lg font-bold">Machine Health Monitoring</h3>
            <p class="text-sm text-muted-foreground">
                Track maintenance, component health, and reliability metrics
            </p>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <div class="text-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
                <p class="text-sm text-muted-foreground mt-2">Loading health data...</p>
            </div>
        </div>

        <!-- Health Overview -->
        <div v-else-if="healthData" class="space-y-6">
            <!-- Key Metrics Grid -->
            <div class="grid gap-4 md:grid-cols-3">
                <!-- MTBF Card -->
                <Card>
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-sm font-medium">MTBF (Mean Time Between Failures)</CardTitle>
                            <TrendingUp v-if="mtbfTrend === 'up'" class="h-4 w-4 text-green-600" />
                            <TrendingDown v-else class="h-4 w-4 text-red-600" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold">
                            {{ healthData.mtbf_hours ? `${healthData.mtbf_hours}h` : 'N/A' }}
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">Average hours between failures</p>
                    </CardContent>
                </Card>

                <!-- MTTR Card -->
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">MTTR (Mean Time To Repair)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold text-blue-600">
                            {{ healthData.mttr_minutes ? `${healthData.mttr_minutes}m` : 'N/A' }}
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">Average repair duration</p>
                    </CardContent>
                </Card>

                <!-- Compliance Card -->
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Maintenance Compliance</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold" :class="{
                            'text-green-600': complianceColor === 'green',
                            'text-yellow-600': complianceColor === 'yellow',
                            'text-red-600': complianceColor === 'red'
                        }">
                            {{ healthData.compliance_rate }}%
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">On-time maintenance rate</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Alert Cards -->
            <div class="grid gap-4 md:grid-cols-4">
                <!-- Overdue Tasks -->
                <Card :class="healthData.overdue_tasks > 0 ? 'border-red-500 bg-red-50 dark:bg-red-950/20' : ''">
                    <CardContent class="p-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-full bg-red-100 dark:bg-red-900/30">
                                <AlertCircle class="h-5 w-5 text-red-600" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-red-600">{{ healthData.overdue_tasks }}</div>
                                <p class="text-xs text-muted-foreground">Overdue Tasks</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Upcoming Tasks -->
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-full bg-amber-100 dark:bg-amber-900/30">
                                <Calendar class="h-5 w-5 text-amber-600" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-amber-600">{{ healthData.upcoming_tasks }}</div>
                                <p class="text-xs text-muted-foreground">Due This Week</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Critical Components -->
                <Card :class="healthData.critical_components > 0 ? 'border-orange-500 bg-orange-50 dark:bg-orange-950/20' : ''">
                    <CardContent class="p-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-full bg-orange-100 dark:bg-orange-900/30">
                                <Package class="h-5 w-5 text-orange-600" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-orange-600">{{ healthData.critical_components }}</div>
                                <p class="text-xs text-muted-foreground">Critical Parts</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Recent Maintenance -->
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-full bg-blue-100 dark:bg-blue-900/30">
                                <Wrench class="h-5 w-5 text-blue-600" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-blue-600">{{ healthData.recent_maintenance }}</div>
                                <p class="text-xs text-muted-foreground">Last 30 Days</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Detailed Tabs -->
            <Tabs v-model="activeTab" class="w-full">
                <TabsList class="grid w-full grid-cols-6">
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="calendar">Calendar</TabsTrigger>
                    <TabsTrigger value="schedule">Schedule</TabsTrigger>
                    <TabsTrigger value="components">Components</TabsTrigger>
                    <TabsTrigger value="spare-parts">Spare Parts</TabsTrigger>
                    <TabsTrigger value="history">History</TabsTrigger>
                </TabsList>

                <TabsContent value="overview" class="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Health Summary</CardTitle>
                            <CardDescription>Current maintenance and component status</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3 text-sm">
                                <div class="flex items-center justify-between p-3 bg-muted rounded-lg">
                                    <span class="font-medium">Machine Status</span>
                                    <Badge variant="outline" class="bg-green-50 text-green-700 border-green-200">
                                        Healthy
                                    </Badge>
                                </div>
                                <div class="flex items-center justify-between p-3 bg-muted rounded-lg">
                                    <span class="font-medium">Scheduled Tasks</span>
                                    <span class="text-muted-foreground">
                                        {{ healthData.upcoming_tasks }} upcoming, {{ healthData.overdue_tasks }} overdue
                                    </span>
                                </div>
                                <div class="flex items-center justify-between p-3 bg-muted rounded-lg">
                                    <span class="font-medium">Component Health</span>
                                    <span class="text-muted-foreground">
                                        {{ healthData.critical_components }} need attention
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="calendar">
                    <MaintenanceCalendar :machineId="machineId" />
                </TabsContent>

                <TabsContent value="schedule">
                    <MaintenanceScheduleList :machineId="machineId" @scheduleCreated="fetchHealthData" />
                </TabsContent>

                <TabsContent value="components">
                    <ComponentsList :machineId="machineId" @componentAdded="fetchHealthData" />
                </TabsContent>

                <TabsContent value="spare-parts">
                    <SparePartsInventory :machineId="machineId" />
                </TabsContent>

                <TabsContent value="history">
                    <MaintenanceHistory :machineId="machineId" />
                </TabsContent>
            </Tabs>
        </div>

        <!-- Error State -->
        <Card v-else>
            <CardContent class="p-8">
                <div class="text-center text-muted-foreground">
                    <AlertCircle class="h-12 w-12 mx-auto mb-3 opacity-50" />
                    <p class="text-sm">Unable to load health data</p>
                    <p class="text-xs mt-1">Please try again later</p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
