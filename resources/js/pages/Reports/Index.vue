<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { useToast } from '@/components/ui/toast/use-toast';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Download, FileText, FileSpreadsheet, Table, Calendar, TrendingUp, AlertCircle, BarChart3, Mail, Wrench, Activity } from 'lucide-vue-next';
import { useTerminology } from '@/composables/useTerminology';
import axios from 'axios';

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

const props = defineProps<{
    plants: Plant[];
}>();

const { plant: plantTerm, line: lineTerm, machine: machineTerm, plants: plantsTerm, lines: linesTerm, machines: machinesTerm } = useTerminology();

// Filters
const filters = useForm({
    date_from: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    date_to: new Date().toISOString().split('T')[0],
    plant_id: null as number | null,
    line_id: null as number | null,
    machine_id: null as number | null,
    shift_id: null as number | null,
});

// Report data
const reportData = ref<any>(null);
const maintenanceData = ref<any>(null);
const isLoading = ref(false);
const error = ref<string | null>(null);
const { toast } = useToast();
const activeTab = ref('oee');

// Email modal
const showEmailModal = ref(false);
const emailAddress = ref('');
const isSendingEmail = ref(false);

// Yearly report
const yearlyReportData = ref<any>(null);
const selectedYear = ref(new Date().getFullYear());
const yearOptions = computed(() => {
    const currentYear = new Date().getFullYear();
    const years = [];
    for (let y = currentYear; y >= currentYear - 5; y--) {
        years.push(y);
    }
    return years;
});

// Computed options
const availableLines = computed(() => {
    if (!filters.plant_id) return [];
    const plant = props.plants.find(p => p.id === filters.plant_id);
    return plant?.lines || [];
});

const availableMachines = computed(() => {
    if (!filters.line_id) return [];
    const line = availableLines.value.find(l => l.id === filters.line_id);
    return line?.machines || [];
});

// Clear dependent filters
const onPlantChange = () => {
    filters.line_id = null;
    filters.machine_id = null;
};

const onLineChange = () => {
    filters.machine_id = null;
};

// Quick date range presets
const setQuickRange = (range: string) => {
    const today = new Date();
    const to = today.toISOString().split('T')[0];
    
    switch (range) {
        case 'today':
            filters.date_from = to;
            filters.date_to = to;
            break;
        case 'yesterday':
            const yesterday = new Date(today.getTime() - 24 * 60 * 60 * 1000);
            filters.date_from = yesterday.toISOString().split('T')[0];
            filters.date_to = yesterday.toISOString().split('T')[0];
            break;
        case 'week':
            const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
            filters.date_from = weekAgo.toISOString().split('T')[0];
            filters.date_to = to;
            break;
        case 'month':
            const monthAgo = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);
            filters.date_from = monthAgo.toISOString().split('T')[0];
            filters.date_to = to;
            break;
    }
};

// Generate report
const generateReport = async () => {
    isLoading.value = true;
    error.value = null;
    
    try {
        const response = await axios.post('/admin/reports/generate', filters.data());
        reportData.value = response.data;
    } catch (e: any) {
        error.value = e.response?.data?.message || 'Failed to generate report';
        console.error('Report generation error:', e);
    } finally {
        isLoading.value = false;
    }
};

// Generate maintenance report
const generateMaintenanceReport = async () => {
    if (!filters.machine_id) {
        toast({
            title: 'Machine Required',
            description: 'Please select a machine to generate maintenance report',
            variant: 'destructive',
        });
        return;
    }

    isLoading.value = true;
    error.value = null;
    
    try {
        const response = await axios.get(`/api/v1/machines/${filters.machine_id}/health`);
        const schedulesResponse = await axios.get(`/api/v1/machines/${filters.machine_id}/maintenance/schedules`);
        const componentsResponse = await axios.get(`/api/v1/machines/${filters.machine_id}/components`);
        const historyResponse = await axios.get(`/api/v1/machines/${filters.machine_id}/maintenance/logs`);
        
        maintenanceData.value = {
            health: response.data,
            schedules: schedulesResponse.data,
            components: componentsResponse.data,
            history: historyResponse.data.logs || [],
        };
    } catch (e: any) {
        error.value = e.response?.data?.message || 'Failed to generate maintenance report';
        console.error('Maintenance report generation error:', e);
    } finally {
        isLoading.value = false;
    }
};

// Export maintenance data
const exportMaintenanceData = async (type: 'pdf' | 'excel' | 'history') => {
    if (!filters.machine_id) {
        toast({
            title: 'Machine Required',
            description: 'Please select a machine first',
            variant: 'destructive',
        });
        return;
    }

    try {
        let url = '';
        let filename = '';
        
        if (type === 'pdf') {
            url = `/api/v1/machines/${filters.machine_id}/maintenance/export/schedules/pdf`;
            filename = 'maintenance-schedules.pdf';
        } else if (type === 'excel') {
            url = `/api/v1/machines/${filters.machine_id}/maintenance/export/schedules/excel`;
            filename = 'maintenance-schedules.csv';
        } else if (type === 'history') {
            url = `/api/v1/machines/${filters.machine_id}/maintenance/export/history`;
            filename = 'maintenance-history.csv';
        }

        const response = await axios.get(url, { responseType: 'blob' });
        
        // Create download link
        const blob = new Blob([response.data]);
        const link = document.createElement('a');
        link.href = window.URL.createObjectURL(blob);
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(link.href);

        toast({
            title: 'Export Successful',
            description: `Downloaded ${filename}`,
        });
    } catch (error: any) {
        console.error('Export error:', error);
        toast({
            title: 'Export Failed',
            description: error.response?.data?.message || 'Failed to export data',
            variant: 'destructive',
        });
    }
};

// Export functions
const exportReport = async (format: 'pdf' | 'excel' | 'csv') => {
    let downloadSuccessful = false;
    
    try {
        toast({
            title: 'Exporting Report',
            description: `Preparing ${format.toUpperCase()} file...`,
        });

        const response = await axios.post(`/admin/reports/export/${format}`, filters.data(), {
            responseType: 'blob'
        });
        
        // Create blob link to download
        const url = (window as any).URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        
        const ext = format === 'excel' ? 'xlsx' : format;
        link.setAttribute('download', `oee-report-${filters.date_from}-to-${filters.date_to}.${ext}`);
        document.body.appendChild(link);
        link.click();
        
        // Mark as successful before cleanup
        downloadSuccessful = true;
        
        // Cleanup
        setTimeout(() => {
            link.remove();
            (window as any).URL.revokeObjectURL(url);
        }, 100);

        toast({
            title: 'Export Successful',
            description: `Your ${format.toUpperCase()} report has been downloaded.`,
        });
    } catch (e: any) {
        console.error('Export error:', e);
        
        // Only show error if download didn't succeed
        if (!downloadSuccessful) {
            toast({
                title: 'Export Failed',
                description: e.response?.data?.message || 'Failed to export report. Please try again.',
                variant: 'destructive',
            });
        }
    }
};

const printReport = () => {
    window.print();
};

// Generate yearly report
const generateYearlyReport = async () => {
    isLoading.value = true;
    error.value = null;
    
    try {
        const response = await axios.post('/admin/reports/yearly/generate', {
            year: selectedYear.value,
            plant_id: filters.plant_id,
            line_id: filters.line_id,
            machine_id: filters.machine_id,
        });
        yearlyReportData.value = response.data;
    } catch (e: any) {
        error.value = e.response?.data?.message || 'Failed to generate yearly report';
        console.error('Yearly report generation error:', e);
    } finally {
        isLoading.value = false;
    }
};

// Export yearly report
const exportYearlyReport = async () => {
    try {
        toast({
            title: 'Exporting Yearly Report',
            description: 'Preparing Excel file...',
        });

        const response = await axios.post('/admin/reports/yearly/export/excel', {
            year: selectedYear.value,
            plant_id: filters.plant_id,
            line_id: filters.line_id,
            machine_id: filters.machine_id,
        }, {
            responseType: 'blob'
        });
        
        const url = (window as any).URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `yearly-oee-report-${selectedYear.value}.xlsx`);
        document.body.appendChild(link);
        link.click();
        
        setTimeout(() => {
            link.remove();
            (window as any).URL.revokeObjectURL(url);
        }, 100);

        toast({
            title: 'Export Successful',
            description: 'Your yearly report has been downloaded.',
        });
    } catch (e: any) {
        console.error('Export error:', e);
        toast({
            title: 'Export Failed',
            description: e.response?.data?.message || 'Failed to export yearly report.',
            variant: 'destructive',
        });
    }
};

// Send email
const sendEmail = async () => {
    if (!emailAddress.value) {
        toast({
            title: 'Email Required',
            description: 'Please enter an email address.',
            variant: 'destructive',
        });
        return;
    }

    isSendingEmail.value = true;
    
    try {
        await axios.post('/admin/reports/send-email', {
            email: emailAddress.value,
            ...filters.data()
        });

        toast({
            title: 'Email Sent',
            description: `Report sent successfully to ${emailAddress.value}`,
        });

        // Close modal and reset
        showEmailModal.value = false;
        emailAddress.value = '';
    } catch (e: any) {
        console.error('Email send error:', e);
        toast({
            title: 'Failed to Send Email',
            description: e.response?.data?.message || 'Unable to send email. Please try again.',
            variant: 'destructive',
        });
    } finally {
        isSendingEmail.value = false;
    }
};

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/admin/reports' },
];
</script>

<template>
    <Head title="OEE Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6 space-y-6">
            <!-- Header with Tabs -->
            <div class="flex flex-col gap-4">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h2 class="text-2xl font-bold tracking-tight">Reports & Analytics</h2>
                        <p class="text-muted-foreground">Generate comprehensive reports with exports</p>
                    </div>
                    <Button as="a" href="/admin/reports/comparison" variant="outline">
                        <BarChart3 class="mr-2 h-4 w-4" />
                        Comparison Reports
                    </Button>
                </div>

                <!-- Report Type Tabs -->
                <Tabs v-model="activeTab" class="w-full">
                    <TabsList class="grid w-full md:w-[600px] grid-cols-3">
                        <TabsTrigger value="oee">
                            <TrendingUp class="h-4 w-4 mr-2" />
                            OEE Report
                        </TabsTrigger>
                        <TabsTrigger value="yearly">
                            <Calendar class="h-4 w-4 mr-2" />
                            Yearly Report
                        </TabsTrigger>
                        <TabsTrigger value="maintenance">
                            <Wrench class="h-4 w-4 mr-2" />
                            Maintenance Report
                        </TabsTrigger>
                    </TabsList>

                    <!-- OEE Report Tab Content -->
                    <TabsContent value="oee" class="space-y-6 mt-6">

            <!-- Filters Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Calendar class="h-5 w-5" />
                        Report Filters
                    </CardTitle>
                    <CardDescription>Select date range and scope for your report</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Quick Date Range -->
                    <div>
                        <Label class="mb-2 block">Quick Range</Label>
                        <div class="flex flex-wrap gap-2">
                            <Button size="sm" variant="outline" @click="setQuickRange('today')">Today</Button>
                            <Button size="sm" variant="outline" @click="setQuickRange('yesterday')">Yesterday</Button>
                            <Button size="sm" variant="outline" @click="setQuickRange('week')">Last 7 Days</Button>
                            <Button size="sm" variant="outline" @click="setQuickRange('month')">Last 30 Days</Button>
                        </div>
                    </div>

                    <!-- Date Range -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <Label>From Date</Label>
                            <Input type="date" v-model="filters.date_from" />
                        </div>
                        <div>
                            <Label>To Date</Label>
                            <Input type="date" v-model="filters.date_to" />
                        </div>
                    </div>

                    <!-- Scope Filters -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <Label>{{ plantTerm }}</Label>
                            <select v-model="filters.plant_id" @change="onPlantChange" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option :value="null">All {{ plantsTerm }}</option>
                                <option v-for="plant in props.plants" :key="plant.id" :value="plant.id">{{ plant.name }}</option>
                            </select>
                        </div>
                        <div>
                            <Label>{{ lineTerm }}</Label>
                            <select v-model="filters.line_id" @change="onLineChange" :disabled="!filters.plant_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option :value="null">All {{ linesTerm }}</option>
                                <option v-for="line in availableLines" :key="line.id" :value="line.id">{{ line.name }}</option>
                            </select>
                        </div>
                        <div>
                            <Label>{{ machineTerm }}</Label>
                            <select v-model="filters.machine_id" :disabled="!filters.line_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option :value="null">All {{ machinesTerm }}</option>
                                <option v-for="machine in availableMachines" :key="machine.id" :value="machine.id">{{ machine.name }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Generate Button -->
                    <div class="flex justify-end pt-4">
                        <Button @click="activeTab === 'oee' ? generateReport() : generateMaintenanceReport()" :disabled="isLoading" size="lg">
                            <TrendingUp v-if="activeTab === 'oee'" class="mr-2 h-4 w-4" />
                            <Activity v-else class="mr-2 h-4 w-4" />
                            {{ isLoading ? 'Generating...' : 'Generate Report' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Error Message -->
            <Card v-if="error" class="border-red-500 bg-red-50 dark:bg-red-950/20">
                <CardContent class="pt-6">
                    <div class="flex items-center gap-2 text-red-600">
                        <AlertCircle class="h-5 w-5" />
                        <span class="font-medium">{{ error }}</span>
                    </div>
                </CardContent>
            </Card>

            <!-- Report Results -->
            <div v-if="reportData" class="space-y-6">
                <!-- Export Buttons -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Download class="h-5 w-5" />
                            Export Report
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-wrap gap-3">
                        <Button variant="outline" @click="exportReport('pdf')">
                            <FileText class="mr-2 h-4 w-4" />
                            Export PDF
                        </Button>
                        <Button variant="outline" @click="exportReport('excel')">
                            <FileSpreadsheet class="mr-2 h-4 w-4" />
                            Export Excel
                        </Button>
                        <Button variant="outline" @click="exportReport('csv')">
                            <Table class="mr-2 h-4 w-4" />
                            Export CSV
                        </Button>
                        <Button variant="outline" @click="showEmailModal = true">
                            <Mail class="mr-2 h-4 w-4" />
                            Email Report
                        </Button>
                        <Button variant="outline" @click="printReport">
                            <FileText class="mr-2 h-4 w-4" />
                            Print
                        </Button>
                    </CardContent>
                </Card>

                <!-- Summary Statistics -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <Card>
                        <CardHeader class="pb-3">
                            <CardDescription>Average OEE</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold" :class="reportData.summary.avg_oee >= 85 ? 'text-green-600' : reportData.summary.avg_oee >= 60 ? 'text-yellow-600' : 'text-red-600'">
                                {{ reportData.summary.avg_oee }}%
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-3">
                            <CardDescription>Availability</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold text-green-600">{{ reportData.summary.avg_availability }}%</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-3">
                            <CardDescription>Performance</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold text-yellow-600">{{ reportData.summary.avg_performance }}%</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-3">
                            <CardDescription>Quality</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold text-purple-600">{{ reportData.summary.avg_quality }}%</div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Production Summary -->
                <Card>
                    <CardHeader>
                        <CardTitle>Production Summary</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-3 gap-4 text-sm">
                            <div>
                                <div class="text-muted-foreground mb-1">Total Produced</div>
                                <div class="text-2xl font-bold">{{ reportData.summary.total_produced?.toLocaleString() || 0 }}</div>
                            </div>
                            <div>
                                <div class="text-muted-foreground mb-1">Good Units</div>
                                <div class="text-2xl font-bold text-green-600">{{ reportData.summary.total_good?.toLocaleString() || 0 }}</div>
                            </div>
                            <div>
                                <div class="text-muted-foreground mb-1">Rejects</div>
                                <div class="text-2xl font-bold text-red-600">{{ reportData.summary.total_reject?.toLocaleString() || 0 }}</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Top Downtime Reasons -->
                <Card v-if="reportData.downtime && reportData.downtime.length">
                    <CardHeader>
                        <CardTitle>Top Downtime Reasons</CardTitle>
                        <CardDescription>Most common causes of production loss</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div v-for="(item, index) in reportData.downtime.slice(0, 5)" :key="index" class="flex items-center justify-between p-3 bg-muted/50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <Badge :variant="index === 0 ? 'destructive' : 'secondary'">{{ (index as number) + 1 }}</Badge>
                                    <div>
                                        <div class="font-medium">{{ item.reason }}</div>
                                        <div class="text-xs text-muted-foreground capitalize">{{ item.category }} • {{ item.count }} events</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-semibold">{{ item.total_duration }} min</div>
                                    <div class="text-xs text-muted-foreground">{{ (item.total_duration / 60).toFixed(1) }} hrs</div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Daily Metrics Table -->
                <Card>
                    <CardHeader>
                        <CardTitle>Daily Metrics</CardTitle>
                        <CardDescription>{{ reportData.metrics.length }} days of data</CardDescription>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-muted">
                                    <tr>
                                        <th class="p-3 text-left font-medium">Date</th>
                                        <th class="p-3 text-left font-medium">{{ machineTerm }}</th>
                                        <th class="p-3 text-center font-medium">OEE</th>
                                        <th class="p-3 text-center font-medium">A</th>
                                        <th class="p-3 text-center font-medium">P</th>
                                        <th class="p-3 text-center font-medium">Q</th>
                                        <th class="p-3 text-right font-medium">Units</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-for="metric in reportData.metrics" :key="metric.id" class="hover:bg-muted/50">
                                        <td class="p-3">{{ metric.date }}</td>
                                        <td class="p-3">{{ metric.machine.name }}</td>
                                        <td class="p-3 text-center">
                                            <Badge :variant="metric.oee >= 85 ? 'default' : metric.oee >= 60 ? 'secondary' : 'destructive'">
                                                {{ metric.oee }}%
                                            </Badge>
                                        </td>
                                        <td class="p-3 text-center text-xs">{{ metric.availability }}%</td>
                                        <td class="p-3 text-center text-xs">{{ metric.performance }}%</td>
                                        <td class="p-3 text-center text-xs">{{ metric.quality }}%</td>
                                        <td class="p-3 text-right font-mono">{{ metric.good_count }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
                    </TabsContent>

                    <!-- Yearly Report Tab Content -->
                    <TabsContent value="yearly" class="space-y-6 mt-6">
                        <!-- Year Selection Card -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Calendar class="h-5 w-5" />
                                    Yearly Report Filters
                                </CardTitle>
                                <CardDescription>Select year and scope for yearly OEE breakdown</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div>
                                        <Label>Year</Label>
                                        <select v-model="selectedYear" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                            <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <Label>{{ plantTerm }}</Label>
                                        <select v-model="filters.plant_id" @change="onPlantChange" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                            <option :value="null">All {{ plantsTerm }}</option>
                                            <option v-for="plant in props.plants" :key="plant.id" :value="plant.id">{{ plant.name }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <Label>{{ lineTerm }}</Label>
                                        <select v-model="filters.line_id" @change="onLineChange" :disabled="!filters.plant_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                            <option :value="null">All {{ linesTerm }}</option>
                                            <option v-for="line in availableLines" :key="line.id" :value="line.id">{{ line.name }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <Label>{{ machineTerm }}</Label>
                                        <select v-model="filters.machine_id" :disabled="!filters.line_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                            <option :value="null">All {{ machinesTerm }}</option>
                                            <option v-for="machine in availableMachines" :key="machine.id" :value="machine.id">{{ machine.name }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="flex justify-end pt-4">
                                    <Button @click="generateYearlyReport()" :disabled="isLoading" size="lg">
                                        <Calendar class="mr-2 h-4 w-4" />
                                        {{ isLoading ? 'Generating...' : 'Generate Yearly Report' }}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Yearly Report Results -->
                        <div v-if="yearlyReportData" class="space-y-6">
                            <!-- Export Button -->
                            <Card>
                                <CardHeader>
                                    <CardTitle class="flex items-center gap-2">
                                        <Download class="h-5 w-5" />
                                        Export Yearly Report
                                    </CardTitle>
                                </CardHeader>
                                <CardContent class="flex flex-wrap gap-3">
                                    <Button variant="outline" @click="exportYearlyReport()">
                                        <FileSpreadsheet class="mr-2 h-4 w-4" />
                                        Export Excel
                                    </Button>
                                </CardContent>
                            </Card>

                            <!-- Yearly Data Table -->
                            <Card>
                                <CardHeader>
                                    <CardTitle>{{ machineTerm }} Performance - {{ yearlyReportData.year }}</CardTitle>
                                    <CardDescription>{{ yearlyReportData.machines?.length || 0 }} {{ machinesTerm }} with monthly OEE breakdown</CardDescription>
                                </CardHeader>
                                <CardContent class="p-0">
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-sm">
                                            <thead class="bg-muted">
                                                <tr>
                                                    <th class="p-3 text-left font-medium sticky left-0 bg-muted z-10">{{ machineTerm }}</th>
                                                    <th class="p-3 text-center font-medium">Target</th>
                                                    <th class="p-3 text-center font-medium">Jan</th>
                                                    <th class="p-3 text-center font-medium">Feb</th>
                                                    <th class="p-3 text-center font-medium">Mar</th>
                                                    <th class="p-3 text-center font-medium">Apr</th>
                                                    <th class="p-3 text-center font-medium">May</th>
                                                    <th class="p-3 text-center font-medium">Jun</th>
                                                    <th class="p-3 text-center font-medium">Jul</th>
                                                    <th class="p-3 text-center font-medium">Aug</th>
                                                    <th class="p-3 text-center font-medium">Sep</th>
                                                    <th class="p-3 text-center font-medium">Oct</th>
                                                    <th class="p-3 text-center font-medium">Nov</th>
                                                    <th class="p-3 text-center font-medium">Dec</th>
                                                    <th class="p-3 text-center font-medium">Avail</th>
                                                    <th class="p-3 text-center font-medium">Util</th>
                                                    <th class="p-3 text-center font-medium">Quality</th>
                                                    <th class="p-3 text-right font-medium">Time Lost</th>
                                                    <th class="p-3 text-right font-medium">Waste</th>
                                                    <th class="p-3 text-center font-medium">UoM</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y">
                                                <tr v-for="machine in yearlyReportData.machines" :key="machine.id" class="hover:bg-muted/50">
                                                    <td class="p-3 font-medium sticky left-0 bg-background">{{ machine.name }}</td>
                                                    <td class="p-3 text-center">
                                                        <Badge v-if="machine.target_oee" variant="outline">{{ machine.target_oee?.toFixed(1) }}%</Badge>
                                                        <span v-else class="text-muted-foreground">-</span>
                                                    </td>
                                                    <td v-for="month in 12" :key="month" class="p-3 text-center text-xs">
                                                        <Badge v-if="machine.monthly_oee['month_' + month] !== null" 
                                                               :variant="machine.monthly_oee['month_' + month] >= 85 ? 'default' : machine.monthly_oee['month_' + month] >= 60 ? 'secondary' : 'destructive'"
                                                               class="text-xs">
                                                            {{ machine.monthly_oee['month_' + month]?.toFixed(1) }}%
                                                        </Badge>
                                                        <span v-else class="text-muted-foreground">-</span>
                                                    </td>
                                                    <td class="p-3 text-center text-xs">{{ machine.availability?.toFixed(1) || '-' }}%</td>
                                                    <td class="p-3 text-center text-xs">{{ machine.utilization?.toFixed(1) || '-' }}%</td>
                                                    <td class="p-3 text-center text-xs">{{ machine.quality?.toFixed(1) || '-' }}%</td>
                                                    <td class="p-3 text-right text-xs font-mono">{{ machine.time_lost_hours?.toFixed(1) || '0' }}h</td>
                                                    <td class="p-3 text-right text-xs font-mono">{{ machine.total_waste?.toLocaleString() || '0' }}</td>
                                                    <td class="p-3 text-center text-xs">{{ machine.uom }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    <!-- Maintenance Report Tab -->
                    <TabsContent value="maintenance" class="space-y-6">
                        <!-- Filters for Maintenance -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Select Machine</CardTitle>
                                <CardDescription>Choose a machine to view maintenance report</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <Label>{{ plantTerm }}</Label>
                                        <select v-model="filters.plant_id" @change="onPlantChange" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                            <option :value="null">Select {{ plantTerm }}</option>
                                            <option v-for="plant in props.plants" :key="plant.id" :value="plant.id">{{ plant.name }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <Label>{{ lineTerm }}</Label>
                                        <select v-model="filters.line_id" @change="onLineChange" :disabled="!filters.plant_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                            <option :value="null">Select {{ lineTerm }}</option>
                                            <option v-for="line in availableLines" :key="line.id" :value="line.id">{{ line.name }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <Label>{{ machineTerm }} *</Label>
                                        <select v-model="filters.machine_id" :disabled="!filters.line_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                            <option :value="null">Select {{ machineTerm }}</option>
                                            <option v-for="machine in availableMachines" :key="machine.id" :value="machine.id">{{ machine.name }}</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <Button @click="generateMaintenanceReport()" :disabled="isLoading || !filters.machine_id" size="lg">
                                        <Activity class="mr-2 h-4 w-4" />
                                        {{ isLoading ? 'Generating...' : 'Generate Report' }}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Maintenance Data Display -->
                        <div v-if="maintenanceData" class="space-y-6">
                            <!-- Export Buttons -->
                            <Card>
                                <CardHeader>
                                    <CardTitle class="flex items-center gap-2">
                                        <Download class="h-5 w-5" />
                                        Export Maintenance Data
                                    </CardTitle>
                                    <CardDescription>Download schedules, components, and history</CardDescription>
                                </CardHeader>
                                <CardContent class="flex flex-wrap gap-3">
                                    <Button variant="outline" @click="exportMaintenanceData('pdf')">
                                        <FileText class="mr-2 h-4 w-4" />
                                        Export PDF
                                    </Button>
                                    <Button variant="outline" @click="exportMaintenanceData('excel')">
                                        <FileSpreadsheet class="mr-2 h-4 w-4" />
                                        Export Excel
                                    </Button>
                                    <Button variant="outline" @click="exportMaintenanceData('history')">
                                        <FileSpreadsheet class="mr-2 h-4 w-4" />
                                        Export History
                                    </Button>
                                    <Button variant="outline" @click="printReport">
                                        <FileText class="mr-2 h-4 w-4" />
                                        Print Report
                                    </Button>
                                </CardContent>
                            </Card>

                            <!-- Health Metrics -->
                            <div class="grid gap-4 md:grid-cols-3">
                                <Card>
                                    <CardHeader class="pb-3">
                                        <CardDescription>MTBF (Hours)</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div class="text-3xl font-bold">{{ maintenanceData.health.mtbf_hours || 'N/A' }}</div>
                                        <p class="text-xs text-muted-foreground mt-1">Mean Time Between Failures</p>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader class="pb-3">
                                        <CardDescription>MTTR (Minutes)</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div class="text-3xl font-bold text-blue-600">{{ maintenanceData.health.mttr_minutes || 'N/A' }}</div>
                                        <p class="text-xs text-muted-foreground mt-1">Mean Time To Repair</p>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader class="pb-3">
                                        <CardDescription>Compliance Rate</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div class="text-3xl font-bold text-green-600">{{ maintenanceData.health.compliance_rate || 0 }}%</div>
                                        <p class="text-xs text-muted-foreground mt-1">Schedule Adherence</p>
                                    </CardContent>
                                </Card>
                            </div>

                            <!-- Summary Stats -->
                            <div class="grid gap-4 md:grid-cols-4">
                                <Card>
                                    <CardContent class="p-4">
                                        <div class="text-2xl font-bold text-red-600">{{ maintenanceData.health.overdue_tasks || 0 }}</div>
                                        <p class="text-xs text-muted-foreground">Overdue Tasks</p>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardContent class="p-4">
                                        <div class="text-2xl font-bold text-amber-600">{{ maintenanceData.health.upcoming_tasks || 0 }}</div>
                                        <p class="text-xs text-muted-foreground">Due This Week</p>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardContent class="p-4">
                                        <div class="text-2xl font-bold text-orange-600">{{ maintenanceData.health.critical_components || 0 }}</div>
                                        <p class="text-xs text-muted-foreground">Critical Parts</p>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardContent class="p-4">
                                        <div class="text-2xl font-bold text-blue-600">{{ maintenanceData.health.recent_maintenance || 0 }}</div>
                                        <p class="text-xs text-muted-foreground">Recent Activities</p>
                                    </CardContent>
                                </Card>
                            </div>

                            <!-- Schedules Table -->
                            <Card v-if="maintenanceData.schedules && maintenanceData.schedules.length > 0">
                                <CardHeader>
                                    <CardTitle>Maintenance Schedules ({{ maintenanceData.schedules.length }})</CardTitle>
                                    <CardDescription>Upcoming and overdue maintenance tasks</CardDescription>
                                </CardHeader>
                                <CardContent class="p-0">
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-sm">
                                            <thead class="bg-muted">
                                                <tr>
                                                    <th class="p-3 text-left font-medium">Task</th>
                                                    <th class="p-3 text-center font-medium">Type</th>
                                                    <th class="p-3 text-center font-medium">Priority</th>
                                                    <th class="p-3 text-center font-medium">Frequency</th>
                                                    <th class="p-3 text-center font-medium">Next Due</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y">
                                                <tr v-for="schedule in maintenanceData.schedules.slice(0, 10)" :key="schedule.id" class="hover:bg-muted/50">
                                                    <td class="p-3">{{ schedule.task_name }}</td>
                                                    <td class="p-3 text-center capitalize text-xs">{{ schedule.maintenance_type }}</td>
                                                    <td class="p-3 text-center">
                                                        <Badge :variant="schedule.priority === 'critical' ? 'destructive' : schedule.priority === 'high' ? 'default' : 'secondary'">
                                                            {{ schedule.priority }}
                                                        </Badge>
                                                    </td>
                                                    <td class="p-3 text-center text-xs">{{ schedule.frequency_days }} days</td>
                                                    <td class="p-3 text-center text-xs">{{ schedule.next_due_at }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Components List -->
                            <Card v-if="maintenanceData.components && maintenanceData.components.length > 0">
                                <CardHeader>
                                    <CardTitle>Machine Components ({{ maintenanceData.components.length }})</CardTitle>
                                    <CardDescription>Component status and lifespan tracking</CardDescription>
                                </CardHeader>
                                <CardContent class="p-0">
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-sm">
                                            <thead class="bg-muted">
                                                <tr>
                                                    <th class="p-3 text-left font-medium">Component</th>
                                                    <th class="p-3 text-center font-medium">Type</th>
                                                    <th class="p-3 text-center font-medium">Runtime</th>
                                                    <th class="p-3 text-center font-medium">Lifespan</th>
                                                    <th class="p-3 text-center font-medium">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y">
                                                <tr v-for="component in maintenanceData.components.slice(0, 10)" :key="component.id" class="hover:bg-muted/50">
                                                    <td class="p-3">{{ component.component_name }}</td>
                                                    <td class="p-3 text-center capitalize text-xs">{{ component.component_type }}</td>
                                                    <td class="p-3 text-center text-xs">{{ component.current_runtime_hours }}h</td>
                                                    <td class="p-3 text-center text-xs">{{ component.expected_lifespan_hours }}h</td>
                                                    <td class="p-3 text-center">
                                                        <Badge :variant="component.status === 'critical' ? 'destructive' : component.status === 'warning' ? 'default' : 'secondary'">
                                                            {{ component.status }}
                                                        </Badge>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Recent Maintenance History -->
                            <Card v-if="maintenanceData.history && maintenanceData.history.length > 0">
                                <CardHeader>
                                    <CardTitle>Recent Maintenance History ({{ maintenanceData.history.length }})</CardTitle>
                                    <CardDescription>Last 10 maintenance activities</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <div v-for="log in maintenanceData.history.slice(0, 10)" :key="log.id" class="flex items-start gap-3 p-3 bg-muted/50 rounded-lg">
                                        <div class="flex-1">
                                            <div class="font-medium">{{ log.task_description }}</div>
                                            <div class="text-xs text-muted-foreground mt-1">
                                                {{ log.performed_at }} • {{ log.duration_minutes }} minutes
                                                <span v-if="log.cost"> • ${{ log.cost }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>
                </Tabs>
            </div>

            <!-- Email Modal (Shared) -->
            <div v-if="showEmailModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showEmailModal = false">
                <Card class="w-full max-w-md mx-4">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Mail class="h-5 w-5" />
                            Email Report
                        </CardTitle>
                        <CardDescription>Send this report as a PDF attachment</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div>
                            <Label for="email">Email Address</Label>
                            <Input 
                                id="email" 
                                type="email" 
                                v-model="emailAddress" 
                                placeholder="recipient@example.com"
                                @keyup.enter="sendEmail"
                            />
                        </div>
                        <div class="text-sm text-muted-foreground">
                            <p>The report will be sent with:</p>
                            <ul class="list-disc list-inside mt-2 space-y-1">
                                <li>PDF attachment</li>
                                <li>Summary statistics</li>
                                <li>Period: {{ filters.date_from }} to {{ filters.date_to }}</li>
                            </ul>
                        </div>
                    </CardContent>
                    <div class="p-6 pt-0 flex justify-end gap-2">
                        <Button variant="outline" @click="showEmailModal = false" :disabled="isSendingEmail">
                            Cancel
                        </Button>
                        <Button @click="sendEmail" :disabled="isSendingEmail">
                            <Mail class="mr-2 h-4 w-4" />
                            {{ isSendingEmail ? 'Sending...' : 'Send Email' }}
                        </Button>
                    </div>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
