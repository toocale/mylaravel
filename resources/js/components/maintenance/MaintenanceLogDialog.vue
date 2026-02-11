<script setup lang="ts">
import { ref, computed } from 'vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { Plus, Trash2 } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps<{
    machineId: number;
    open: boolean;
    scheduleId?: number | null;
    taskName?: string;
}>();

const emit = defineEmits(['update:open', 'saved']);

// State
const saving = ref(false);

// Form
const form = ref({
    maintenance_schedule_id: null as number | null,
    task_description: '',
    duration_minutes: null as number | null,
    notes: '',
    parts_replaced: [] as Array<{ name: string; quantity: number; cost: number | null }>,
    cost: null as number | null,
    performed_at: '',
});

// Initialize form when dialog opens
const initializeForm = () => {
    const now = new Date();
    const localDateTime = new Date(now.getTime() - (now.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
    
    form.value = {
        maintenance_schedule_id: props.scheduleId || null,
        task_description: props.taskName || '',
        duration_minutes: null,
        notes: '',
        parts_replaced: [],
        cost: null,
        performed_at: localDateTime,
    };
};

// Watch for dialog open
const dialogOpen = computed({
    get: () => props.open,
    set: (value) => {
        emit('update:open', value);
        if (value) {
            initializeForm();
        }
    }
});

// Parts management
const addPart = () => {
    form.value.parts_replaced.push({
        name: '',
        quantity: 1,
        cost: null,
    });
};

const removePart = (index: number) => {
    form.value.parts_replaced.splice(index, 1);
};

// Calculate total cost
const totalPartsCost = computed(() => {
    return form.value.parts_replaced.reduce((sum, part) => {
        return sum + ((part.cost || 0) * part.quantity);
    }, 0);
});

// Auto-update total cost
const updateTotalCost = () => {
    if (form.value.parts_replaced.length > 0) {
        form.value.cost = totalPartsCost.value || null;
    }
};

// Save
const save = async () => {
    if (!form.value.task_description) {
        alert('Please enter a task description');
        return;
    }

    saving.value = true;
    try {
        // Prepare data
        const data = {
            ...form.value,
            parts_replaced: form.value.parts_replaced.length > 0 ? form.value.parts_replaced : null,
        };

        await axios.post(`/api/v1/machines/${props.machineId}/maintenance/logs`, data);
        
        emit('saved');
        dialogOpen.value = false;
    } catch (error: any) {
        console.error('Failed to log maintenance:', error);
        alert(error.response?.data?.message || 'Failed to log maintenance');
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="sm:max-w-[700px] max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Log Completed Maintenance</DialogTitle>
                <DialogDescription>Record maintenance work that has been completed</DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <!-- Task Description -->
                <div class="space-y-2">
                    <Label>Task Description *</Label>
                    <Input v-model="form.task_description" placeholder="e.g., Weekly lubrication and inspection" />
                </div>

                <!-- Date and Duration -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label>Date & Time Performed *</Label>
                        <Input v-model="form.performed_at" type="datetime-local" />
                    </div>

                    <div class="space-y-2">
                        <Label>Duration (Minutes)</Label>
                        <Input v-model.number="form.duration_minutes" type="number" min="0" placeholder="e.g., 45" :value="form.duration_minutes ?? undefined" />
                    </div>
                </div>

                <!-- Notes -->
                <div class="space-y-2">
                    <Label>Notes</Label>
                    <Textarea 
                        v-model="form.notes" 
                        placeholder="Add any observations, issues found, or additional details..."
                        rows="3"
                    />
                </div>

                <!-- Parts Replaced Section -->
                <div class="border-t pt-4">
                    <div class="flex items-center justify-between mb-3">
                        <Label class="text-base font-semibold">Parts Replaced</Label>
                        <Button @click="addPart" size="sm" variant="outline">
                            <Plus class="h-4 w-4 mr-1" />
                            Add Part
                        </Button>
                    </div>

                    <div v-if="form.parts_replaced.length > 0" class="space-y-3">
                        <div v-for="(part, index) in form.parts_replaced" :key="index" 
                             class="grid grid-cols-12 gap-2 items-end p-3 bg-muted rounded-lg">
                            <div class="col-span-5 space-y-2">
                                <Label class="text-xs">Part Name</Label>
                                <Input v-model="part.name" placeholder="e.g., V-Belt Type A" size="sm" />
                            </div>
                            <div class="col-span-2 space-y-2">
                                <Label class="text-xs">Qty</Label>
                                <Input v-model.number="part.quantity" type="number" min="1" size="sm" />
                            </div>
                            <div class="col-span-4 space-y-2">
                                <Label class="text-xs">Cost ($)</Label>
                                <Input 
                                    v-model.number="part.cost" 
                                    type="number" 
                                    min="0" 
                                    step="0.01" 
                                    placeholder="0.00"
                                    @input="updateTotalCost"
                                    size="sm"
                                    :value="part.cost ?? undefined"
                                />
                            </div>
                            <div class="col-span-1">
                                <Button @click="removePart(index)" variant="ghost" size="sm" class="h-9 w-9 p-0 text-red-600">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>

                        <!-- Total Cost Display -->
                        <div v-if="totalPartsCost > 0" class="flex items-center justify-between p-3 bg-blue-50 dark:bg-blue-950/20 rounded-lg border border-blue-200">
                            <span class="font-semibold">Total Parts Cost:</span>
                            <span class="text-lg font-bold text-blue-600">${{ totalPartsCost.toFixed(2) }}</span>
                        </div>
                    </div>

                    <div v-else class="text-center py-4 text-sm text-muted-foreground">
                        No parts replaced during this maintenance
                    </div>
                </div>

                <!-- Manual Total Cost Override -->
                <div v-if="form.parts_replaced.length === 0" class="space-y-2">
                    <Label>Total Cost (Optional)</Label>
                    <Input v-model.number="form.cost" type="number" min="0" step="0.01" placeholder="0.00" :value="form.cost ?? undefined" />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="dialogOpen = false">Cancel</Button>
                <Button @click="save" :disabled="saving || !form.task_description">
                    {{ saving ? 'Saving...' : 'Log Maintenance' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
