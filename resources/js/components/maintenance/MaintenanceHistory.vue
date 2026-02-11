<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Clock, User, Wrench, DollarSign, Package, Plus } from 'lucide-vue-next';
import MaintenanceLogDialog from './MaintenanceLogDialog.vue';
import axios from 'axios';

const props = defineProps<{
    machineId: number;
}>();

// State
const loading = ref(false);
const logs = ref<any[]>([]);
const logDialogOpen = ref(false);

// Methods
const fetchLogs = async () => {
    loading.value = true;
    try {
        const response = await axios.get(`/api/v1/machines/${props.machineId}/maintenance/logs`);
        logs.value = response.data.logs || [];
    } catch (error) {
        console.error('Failed to fetch maintenance logs:', error);
    } finally {
        loading.value = false;
    }
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const formatDuration = (minutes: number | null) => {
    if (!minutes) return 'N/A';
    if (minutes < 60) return `${minutes} min`;
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
};

onMounted(() => {
    fetchLogs();
});
</script>

<template>
    <div class="space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-base font-semibold">Maintenance History</h3>
                <p class="text-sm text-muted-foreground">Record of completed maintenance activities</p>
            </div>
            <Button @click="logDialogOpen = true" size="sm">
                <Plus class="h-4 w-4 mr-2" />
                Log Maintenance
            </Button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="text-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
        </div>

        <!-- Logs Timeline -->
        <div v-else-if="logs.length > 0" class="space-y-3">
            <Card v-for="(log, index) in logs" :key="log.id">
                <CardContent class="p-4">
                    <div class="flex gap-4">
                        <!-- Timeline marker -->
                        <div class="flex flex-col items-center pt-1">
                            <div class="w-3 h-3 rounded-full bg-blue-500 ring-4 ring-blue-100"></div>
                            <div v-if="index < logs.length - 1" class="w-0.5 h-full bg-gray-200 mt-2"></div>
                        </div>

                        <!-- Content -->
                        <div class="flex-1 pb-4">
                            <div class="flex items-start justify-between mb-2">
                                <div>
                                    <h4 class="font-semibold text-base">{{ log.task_description }}</h4>
                                    <div class="flex items-center gap-2 text-sm text-muted-foreground mt-1">
                                        <Clock class="h-3 w-3" />
                                        <span>{{ formatDate(log.performed_at) }}</span>
                                    </div>
                                </div>
                                <Badge variant="outline" class="bg-blue-50 text-blue-700 border-blue-200">
                                    Completed
                                </Badge>
                            </div>

                            <!-- Details Grid -->
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3 text-sm">
                                <div v-if="log.performed_by" class="flex items-center gap-2">
                                    <User class="h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <div class="text-xs text-muted-foreground">Performed By</div>
                                        <div class="font-medium">{{ log.performed_by.name }}</div>
                                    </div>
                                </div>

                                <div v-if="log.duration_minutes" class="flex items-center gap-2">
                                    <Wrench class="h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <div class="text-xs text-muted-foreground">Duration</div>
                                        <div class="font-medium">{{ formatDuration(log.duration_minutes) }}</div>
                                    </div>
                                </div>

                                <div v-if="log.cost" class="flex items-center gap-2">
                                    <DollarSign class="h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <div class="text-xs text-muted-foreground">Cost</div>
                                        <div class="font-medium">${{ log.cost }}</div>
                                    </div>
                                </div>

                                <div v-if="log.parts_replaced && log.parts_replaced.length > 0" class="flex items-center gap-2">
                                    <Package class="h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <div class="text-xs text-muted-foreground">Parts Replaced</div>
                                        <div class="font-medium">{{ log.parts_replaced.length }} item(s)</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div v-if="log.notes" class="mt-3 p-3 bg-muted rounded-lg text-sm">
                                <div class="text-xs text-muted-foreground mb-1">Notes:</div>
                                <div>{{ log.notes }}</div>
                            </div>

                            <!-- Parts Details -->
                            <div v-if="log.parts_replaced && log.parts_replaced.length > 0" class="mt-3">
                                <div class="text-xs text-muted-foreground mb-2">Parts Replaced:</div>
                                <div class="space-y-1">
                                    <div v-for="(part, pidx) in log.parts_replaced" :key="pidx" 
                                         class="flex items-center justify-between text-sm bg-muted px-3 py-2 rounded">
                                        <span>{{ part.name }} <span class="text-muted-foreground">× {{ part.quantity }}</span></span>
                                        <span v-if="part.cost" class="font-medium">${{ part.cost }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Empty State -->
        <Card v-else>
            <CardContent class="p-8 text-center">
                <Clock class="h-12 w-12 mx-auto mb-3 text-muted-foreground opacity-50" />
                <p class="text-sm text-muted-foreground">No maintenance history yet</p>
                <p class="text-xs mt-1 text-muted-foreground">Completed maintenance activities will appear here</p>
            </CardContent>
        </Card>

        <!-- Log Maintenance Dialog -->
        <MaintenanceLogDialog 
            v-model:open="logDialogOpen" 
            :machineId="machineId"
            @saved="fetchLogs"
        />
    </div>
</template>
