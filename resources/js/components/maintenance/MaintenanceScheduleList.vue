<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Plus, Calendar, AlertCircle, Clock, User, Pencil } from 'lucide-vue-next';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';

const props = defineProps<{
    machineId: number;
}>();

const emit = defineEmits(['scheduleCreated']);

// State
const loading = ref(false);
const schedules = ref<any[]>([]);
const dialogOpen = ref(false);
const saving = ref(false);
const editingId = ref<number | null>(null);

// Form
const form = ref({
    task_name: '',
    description: '',
    maintenance_type: 'weekly',
    frequency_days: 7,
    priority: 'medium',
    estimated_duration_minutes: 30,
    next_due_at: '',
});

// Permissions
const page = usePage();
const can = (permission: string) => {
    return (page.props.auth as any).permissions?.includes(permission);
};

// Methods
const fetchSchedules = async () => {
    loading.value = true;
    try {
        const response = await axios.get(`/api/v1/machines/${props.machineId}/maintenance/schedules`);
        schedules.value = response.data;
    } catch (error) {
        console.error('Failed to fetch schedules:', error);
    } finally {
        loading.value = false;
    }
};

const openDialog = () => {
    editingId.value = null;
    form.value = {
        task_name: '',
        description: '',
        maintenance_type: 'weekly',
        frequency_days: 7,
        priority: 'medium',
        estimated_duration_minutes: 30,
        next_due_at: '',
    };
    dialogOpen.value = true;
};

const openEditDialog = (schedule: any) => {
    editingId.value = schedule.id;
    form.value = {
        task_name: schedule.task_name,
        description: schedule.description || '',
        maintenance_type: schedule.maintenance_type,
        frequency_days: schedule.frequency_days,
        priority: schedule.priority,
        estimated_duration_minutes: schedule.estimated_duration_minutes,
        next_due_at: schedule.next_due_at ? schedule.next_due_at.split(' ')[0] : '',
    };
    dialogOpen.value = true;
};

const saveSchedule = async () => {
    saving.value = true;
    try {
        if (editingId.value) {
            // Update existing schedule
            await axios.put(`/api/v1/maintenance/schedules/${editingId.value}`, form.value);
        } else {
            // Create new schedule
            await axios.post(`/api/v1/machines/${props.machineId}/maintenance/schedules`, form.value);
        }
        dialogOpen.value = false;
        await fetchSchedules();
        emit('scheduleCreated');
    } catch (error: any) {
        console.error('Failed to save schedule:', error);
        alert(error.response?.data?.message || 'Failed to save schedule');
    } finally {
        saving.value = false;
    }
};

const deleteSchedule = async (scheduleId: number) => {
    if (!confirm('Are you sure you want to delete this schedule?')) return;
    
    try {
        await axios.delete(`/api/v1/maintenance/schedules/${scheduleId}`);
        await fetchSchedules();
    } catch (error) {
        console.error('Failed to delete schedule:', error);
        alert('Failed to delete schedule');
    }
};

const getPriorityColor = (priority: string) => {
    const colors: any = {
        low: 'bg-blue-100 text-blue-700 border-blue-200',
        medium: 'bg-yellow-100 text-yellow-700 border-yellow-200',
        high: 'bg-orange-100 text-orange-700 border-orange-200',
        critical: 'bg-red-100 text-red-700 border-red-200',
    };
    return colors[priority] || colors.medium;
};

const formatDate = (date: string) => {
    if (!date) return 'Not set';
    return new Date(date).toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric' 
    });
};

const isOverdue = (schedule: any) => {
    return schedule.is_overdue || new Date(schedule.next_due_at) < new Date();
};

onMounted(() => {
    fetchSchedules();
});
</script>

<template>
    <div class="space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-base font-semibold">Maintenance Schedule</h3>
                <p class="text-sm text-muted-foreground">Manage preventive maintenance tasks</p>
            </div>
            <Button v-if="can('maintenance.create')" @click="openDialog" size="sm">
                <Plus class="h-4 w-4 mr-2" />
                Add Schedule
            </Button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="text-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
        </div>

        <!-- Schedules List -->
        <div v-else-if="schedules.length > 0" class="space-y-3">
            <Card v-for="schedule in schedules" :key="schedule.id" :class="{'border-red-300 bg-red-50/50 dark:bg-red-950/20': isOverdue(schedule)}">
                <CardContent class="p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 space-y-2">
                            <div class="flex items-center gap-2">
                                <h4 class="font-semibold">{{ schedule.task_name }}</h4>
                                <Badge :class="getPriorityColor(schedule.priority)" variant="outline" class="text-xs">
                                    {{ schedule.priority }}
                                </Badge>
                                <Badge v-if="isOverdue(schedule)" variant="destructive" class="text-xs">
                                    <AlertCircle class="h-3 w-3 mr-1" />
                                    Overdue
                                </Badge>
                            </div>
                            
                            <p v-if="schedule.description" class="text-sm text-muted-foreground">
                                {{ schedule.description }}
                            </p>

                            <div class="flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                                <div class="flex items-center gap-1">
                                    <Calendar class="h-3 w-3" />
                                    <span class="capitalize">{{ schedule.maintenance_type }}</span>
                                    <span v-if="schedule.frequency_days">(Every {{ schedule.frequency_days }} days)</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <Clock class="h-3 w-3" />
                                    {{ schedule.estimated_duration_minutes }} min
                                </div>
                                <div v-if="schedule.assigned_to" class="flex items-center gap-1">
                                    <User class="h-3 w-3" />
                                    {{ schedule.assigned_to.name }}
                                </div>
                            </div>

                            <div class="flex items-center gap-4 text-sm">
                                <div>
                                    <span class="text-muted-foreground">Last:</span>
                                    <span class="ml-1 font-medium">{{ schedule.last_performed_at ? formatDate(schedule.last_performed_at) : 'Never' }}</span>
                                </div>
                                <div :class="{'text-red-600 font-semibold': isOverdue(schedule)}">
                                    <span class="text-muted-foreground">Next Due:</span>
                                    <span class="ml-1 font-medium">{{ formatDate(schedule.next_due_at) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <Button v-if="can('maintenance.edit')" variant="ghost" size="sm" @click="openEditDialog(schedule)" class="text-blue-600 hover:text-blue-700">
                                <Pencil class="h-3 w-3 mr-1" />
                                Edit
                            </Button>
                            <Button v-if="can('maintenance.delete')" variant="ghost" size="sm" @click="deleteSchedule(schedule.id)" class="text-red-600 hover:text-red-700">
                                Delete
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Empty State -->
        <Card v-else>
            <CardContent class="p-8 text-center">
                <Calendar class="h-12 w-12 mx-auto mb-3 text-muted-foreground opacity-50" />
                <p class="text-sm text-muted-foreground mb-3">No maintenance schedules yet</p>
                <Button v-if="can('maintenance.create')" @click="openDialog" size="sm" variant="outline">
                    <Plus class="h-4 w-4 mr-2" />
                    Create First Schedule
                </Button>
            </CardContent>
        </Card>

        <!-- Add Schedule Dialog -->
        <Dialog v-model:open="dialogOpen">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>{{ editingId ? 'Edit Maintenance Schedule' : 'Add Maintenance Schedule' }}</DialogTitle>
                    <DialogDescription>{{ editingId ? 'Update preventive maintenance task' : 'Create a preventive maintenance task' }}</DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label>Task Name *</Label>
                        <Input v-model="form.task_name" placeholder="e.g., Weekly Lubrication" />
                    </div>

                    <div class="space-y-2">
                        <Label>Description</Label>
                        <Input v-model="form.description" placeholder="Brief description of the task" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label>Maintenance Type *</Label>
                            <select v-model="form.maintenance_type" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="annual">Annual</option>
                                <option value="conditional">Conditional</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <Label>Frequency (Days) *</Label>
                            <Input v-model.number="form.frequency_days" type="number" min="1" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label>Priority *</Label>
                            <select v-model="form.priority" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <Label>Duration (Minutes)</Label>
                            <Input v-model.number="form.estimated_duration_minutes" type="number" min="1" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label>Next Due Date</Label>
                        <Input v-model="form.next_due_at" type="date" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="dialogOpen = false">Cancel</Button>
                    <Button @click="saveSchedule" :disabled="saving || !form.task_name">
                        {{ saving ? 'Saving...' : (editingId ? 'Update Schedule' : 'Create Schedule') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
