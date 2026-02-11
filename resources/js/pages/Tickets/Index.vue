<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Ticket, AlertCircle, Clock, CheckCircle, XCircle, Plus } from 'lucide-vue-next';
import { useTerminology } from '@/composables/useTerminology';

// Use global route function
const route = (window as any).route;

const props = defineProps<{
    tickets: any;
    plants: any[];
    filters: {
        status?: string;
        priority?: string;
        plant_id?: number;
        line_id?: number;
        machine_id?: number;
    };
}>();

const { plant: plantTerm, line: lineTerm, machine: machineTerm, plants: plantsTerm, lines: linesTerm, machines: machinesTerm } = useTerminology();

const statusFilter = ref(props.filters.status || 'all');
const priorityFilter = ref(props.filters.priority || 'all');
const plantFilter = ref(props.filters.plant_id || null);
const lineFilter = ref(props.filters.line_id || null);
const machineFilter = ref(props.filters.machine_id || null);

const availableLines = computed(() => {
    if (!plantFilter.value) return [];
    const plant = props.plants.find(p => p.id === plantFilter.value);
    return plant?.lines || [];
});

const availableMachines = computed(() => {
    if (!lineFilter.value) return [];
    const line = availableLines.value.find((l: any) => l.id === lineFilter.value);
    return line?.machines || [];
});

const onPlantChange = () => {
    lineFilter.value = null;
    machineFilter.value = null;
    applyFilters();
};

const onLineChange = () => {
    machineFilter.value = null;
    applyFilters();
};

const applyFilters = () => {
    router.get('/tickets', {
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
        priority: priorityFilter.value !== 'all' ? priorityFilter.value : undefined,
        plant_id: plantFilter.value || undefined,
        line_id: lineFilter.value || undefined,
        machine_id: machineFilter.value || undefined,
    }, {
        preserveState: true,
    });
};


const getPriorityColor = (priority: string) => {
    const colors: any = {
        urgent: 'bg-red-100 text-red-800 border-red-200',
        high: 'bg-orange-100 text-orange-800 border-orange-200',
        medium: 'bg-yellow-100 text-yellow-800 border-yellow-200',
        low: 'bg-blue-100 text-blue-800 border-blue-200',
    };
    return colors[priority] || 'bg-gray-100 text-gray-800';
};

const getStatusColor = (status: string) => {
    const colors: any = {
        open: 'bg-green-100 text-green-800 border-green-200',
        in_progress: 'bg-blue-100 text-blue-800 border-blue-200',
        resolved: 'bg-purple-100 text-purple-800 border-purple-200',
        closed: 'bg-gray-100 text-gray-800 border-gray-200',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
};

const getStatusIcon = (status: string) => {
    const icons: any = {
        open: AlertCircle,
        in_progress: Clock,
        resolved: CheckCircle,
        closed: XCircle,
    };
    return icons[status] || AlertCircle;
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Tickets" />

        <div class="p-4 sm:p-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold flex items-center gap-2">
                        <Ticket class="w-6 h-6 sm:w-8 sm:h-8" />
                        Tickets
                    </h1>
                    <p class="text-muted-foreground mt-1 text-sm sm:text-base">Manage and track support tickets</p>
                </div>
                <Link href="/tickets/create">
                    <Button class="gap-2 w-full sm:w-auto">
                        <Plus class="w-4 h-4" />
                        New Ticket
                    </Button>
                </Link>
            </div>

            <!-- Filters -->
            <Card class="mb-6">
                <CardContent class="pt-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                        <div>
                            <label class="text-sm font-medium mb-2 block">Status</label>
                            <select 
                                v-model="statusFilter" 
                                @change="applyFilters"
                                class="w-full h-10 rounded-md border border-input bg-background text-foreground px-3 py-2 text-sm"
                            >
                                <option value="all">All Statuses</option>
                                <option value="open">Open</option>
                                <option value="in_progress">In Progress</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium mb-2 block">Priority</label>
                            <select 
                                v-model="priorityFilter" 
                                @change="applyFilters"
                                class="w-full h-10 rounded-md border border-input bg-background text-foreground px-3 py-2 text-sm"
                            >
                                <option value="all">All Priorities</option>
                                <option value="urgent">Urgent</option>
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="text-sm font-medium mb-2 block">{{ plantTerm }}</label>
                            <select 
                                v-model="plantFilter" 
                                @change="onPlantChange"
                                class="w-full h-10 rounded-md border border-input bg-background text-foreground px-3 py-2 text-sm"
                            >
                                <option :value="null">All {{ plantsTerm }}</option>
                                <option v-for="plant in plants" :key="plant.id" :value="plant.id">
                                    {{ plant.name }}
                                </option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="text-sm font-medium mb-2 block">{{ lineTerm }}</label>
                            <select 
                                v-model="lineFilter" 
                                @change="onLineChange"
                                :disabled="!plantFilter"
                                class="w-full h-10 rounded-md border border-input bg-background text-foreground px-3 py-2 text-sm disabled:opacity-50"
                            >
                                <option :value="null">All {{ linesTerm }}</option>
                                <option v-for="line in availableLines" :key="line.id" :value="line.id">
                                    {{ line.name }}
                                </option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="text-sm font-medium mb-2 block">{{ machineTerm }}</label>
                            <select 
                                v-model="machineFilter" 
                                @change="applyFilters"
                                :disabled="!lineFilter"
                                class="w-full h-10 rounded-md border border-input bg-background text-foreground px-3 py-2 text-sm disabled:opacity-50"
                            >
                                <option :value="null">All {{ machinesTerm }}</option>
                                <option v-for="machine in availableMachines" :key="machine.id" :value="machine.id">
                                    {{ machine.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Tickets List -->
            <div class="space-y-4">
                <Card v-for="ticket in tickets.data" :key="ticket.id" class="hover:shadow-md transition-shadow">
                    <CardContent class="p-4 sm:p-6">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                            <div class="flex-1">
                                <Link :href="`/tickets/${ticket.id}`" class="group">
                                    <div class="flex items-start gap-3">
                                        <component :is="getStatusIcon(ticket.status)" class="w-5 h-5 mt-1 text-muted-foreground flex-shrink-0" />
                                        <div class="flex-1 min-w-0">
                                            <h3 class="text-base sm:text-lg font-semibold group-hover:text-primary transition-colors">
                                                #{{ ticket.id }} - {{ ticket.subject }}
                                            </h3>
                                            <p class="text-sm text-muted-foreground mt-1 line-clamp-2">
                                                {{ ticket.description }}
                                            </p>
                                            
                                            <div class="flex flex-wrap items-center gap-2 sm:gap-3 mt-3">
                                                <Badge :class="getPriorityColor(ticket.priority)" class="border text-xs">
                                                    {{ ticket.priority.toUpperCase() }}
                                                </Badge>
                                                <Badge :class="getStatusColor(ticket.status)" class="border text-xs">
                                                    {{ ticket.status.replace('_', ' ').toUpperCase() }}
                                                </Badge>
                                                <span class="text-xs text-muted-foreground">
                                                    Created {{ formatDate(ticket.created_at) }}
                                                </span>
                                                <span v-if="ticket.closed_at" class="text-xs text-muted-foreground hidden sm:inline">
                                                    • Closed {{ formatDate(ticket.closed_at) }}
                                                </span>
                                                <span v-if="ticket.plant" class="text-xs text-muted-foreground">
                                                    📍 {{ ticket.plant.name }}
                                                </span>
                                                <span v-if="ticket.machine" class="text-xs text-muted-foreground hidden sm:inline">
                                                    🔧 {{ ticket.machine.name }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </Link>
                            </div>
                            
                            <div class="sm:ml-4 sm:text-right flex sm:flex-col gap-1">
                                <div class="text-sm font-medium">
                                    {{ ticket.creator.name }}
                                </div>
                                <div v-if="ticket.assignee" class="text-xs text-muted-foreground">
                                    Assigned to: {{ ticket.assignee.name }}
                                </div>
                                <div v-else class="text-xs text-muted-foreground">
                                    Unassigned
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Empty State -->
                <Card v-if="tickets.data.length === 0">
                    <CardContent class="p-12 text-center">
                        <Ticket class="w-16 h-16 mx-auto text-muted-foreground mb-4" />
                        <h3 class="text-lg font-semibold mb-2">No tickets found</h3>
                        <p class="text-muted-foreground mb-4">Get started by creating your first ticket</p>
                        <Link href="/tickets/create">
                            <Button>
                                <Plus class="w-4 h-4 mr-2" />
                                Create Ticket
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>

            <!-- Pagination -->
            <div v-if="tickets.last_page > 1" class="mt-6 flex justify-center gap-2">
                <Button 
                    v-for="page in tickets.last_page" 
                    :key="page"
                    :variant="page === tickets.current_page ? 'default' : 'outline'"
                    size="sm"
                    @click="router.get(tickets.path + '?page=' + page)"
                >
                    {{ page }}
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
