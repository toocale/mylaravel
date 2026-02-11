<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { Loader2, Package, Plus, X, ArrowRight } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps<{
    open: boolean;
    machineId: number;
    shifts?: any[]; // Shift definitions
    products?: any[];
    users?: any[];
    reasonCodes?: any[];
}>();

const emit = defineEmits(['update:open', 'created']);

const loading = ref(false);

const createForm = ref({
    machine_id: null as number | null,
    shift_id: null as number | null,
    product_id: null as number | null,
    user_id: null as number | null,
    started_at: '',
    ended_at: '',
    good_count: 0,
    reject_count: 0,
    material_loss_units: 0,
    downtime_minutes: 0,
    downtime_records: [] as Array<{
        reason_code_id: number;
        minutes: number;
        start_time?: string;
        end_time?: string;
        comment: string;
    }>,
    batch_number: '',
    comment: '',
    changeovers: [] as Array<{
        to_product_id: number | null;
        changed_at: string;
        batch_number: string;
        notes: string;
        good_count: number;
        reject_count: number;
    }>
});

// Initialize form when opening
watch(() => props.open, (newVal) => {
    if (newVal) {
        createForm.value = {
            machine_id: props.machineId,
            shift_id: props.shifts?.[0]?.id || null,
            product_id: props.products?.[0]?.id || null,
            user_id: props.users?.[0]?.id || null,
            started_at: '',
            ended_at: '',
            good_count: 0,
            reject_count: 0,
            material_loss_units: 0,
            downtime_minutes: 0,
            downtime_records: [],
            batch_number: '',
            comment: '',
            changeovers: []
        };
    }
});

// Computed Totals
const totalGood = computed(() => {
    let total = Number(createForm.value.good_count) || 0;
    createForm.value.changeovers.forEach(c => {
        total += Number(c.good_count) || 0;
    });
    return total;
});

const totalReject = computed(() => {
    let total = Number(createForm.value.reject_count) || 0;
    createForm.value.changeovers.forEach(c => {
        total += Number(c.reject_count) || 0;
    });
    return total;
});

const calculatedDowntime = computed(() => {
    return createForm.value.downtime_records.reduce((sum, d) => sum + (Number(d.minutes) || 0), 0);
});

const submitCreate = async () => {
    loading.value = true;
    try {
        const payload = { ...createForm.value };
        if (payload.started_at) payload.started_at = new Date(payload.started_at).toISOString();
        if (payload.ended_at) payload.ended_at = new Date(payload.ended_at).toISOString();
        
        const res = await axios.post('/admin/production-shifts/create', payload);
        
        if (res.data.success) {
            emit('created');
            emit('update:open', false);
        }
    } catch (e: any) {
        console.error('Failed to create shift:', e);
        alert(e.response?.data?.error || 'Failed to create shift report.');
    } finally {
        loading.value = false;
    }
};

const addChangeover = () => {
    createForm.value.changeovers.push({
        to_product_id: null,
        changed_at: '',
        batch_number: '',
        notes: '',
        good_count: 0,
        reject_count: 0
    });
};

const addDowntime = () => {
    createForm.value.downtime_records.push({
        reason_code_id: props.reasonCodes?.[0]?.id || 0,
        minutes: 0,
        start_time: '',
        end_time: '',
        comment: ''
    });
};

const calculateDowntimeDuration = (dt: any) => {
    if (dt.start_time && dt.end_time) {
        const start = new Date(dt.start_time).getTime();
        const end = new Date(dt.end_time).getTime();
        if (end > start) {
            dt.minutes = Math.round((end - start) / 60000);
        } else {
            dt.minutes = 0;
        }
    }
};

const removeDowntime = (index: number) => {
    createForm.value.downtime_records.splice(index, 1);
};

const removeChangeover = (index: number) => {
    createForm.value.changeovers.splice(index, 1);
};
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="w-[95vw] sm:w-[90vw] md:w-[85vw] lg:max-w-[1100px] max-h-[90vh] flex flex-col">
            <DialogHeader>
                <DialogTitle>Log Past Shift Report</DialogTitle>
                <DialogDescription>
                    Manually record a completed shift that wasn't tracked in real-time.
                </DialogDescription>
            </DialogHeader>
            
            <div class="flex-1 overflow-y-auto min-h-0 pr-1 space-y-3 py-2">
                <div class="space-y-2">
                    <Label>Shift Information</Label>
                    <div class="text-sm">
                         <div class="grid grid-cols-3 gap-4 mb-4">
                            <div class="space-y-2">
                                <Label>Shift Type</Label>
                                <select v-model="createForm.shift_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                    <option v-for="shift in shifts" :key="shift.id" :value="shift.id">
                                        {{ shift.name }} ({{ shift.start_time }}-{{ shift.end_time }})
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label>Operator</Label>
                                <select v-model="createForm.user_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                    <option v-for="user in users" :key="user.id" :value="user.id">
                                        {{ user.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label>Product</Label>
                                <select v-model="createForm.product_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                    <option v-for="product in products" :key="product.id" :value="product.id">
                                        {{ product.name }}{{ product.sku ? ` (${product.sku})` : '' }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div class="space-y-2">
                                <Label>Start Time</Label>
                                <Input type="datetime-local" v-model="createForm.started_at" />
                            </div>
                            <div class="space-y-2">
                                <Label>End Time</Label>
                                <Input type="datetime-local" v-model="createForm.ended_at" />
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">

                    <div class="space-y-2">
                        <Label>Initial Good Count</Label>
                        <Input type="number" v-model="createForm.good_count" min="0" />
                        <p class="text-[10px] text-muted-foreground" v-if="createForm.changeovers.length > 0">
                            For initial product. Add changeover counts below.
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label>Initial Reject Count</Label>
                        <Input type="number" v-model="createForm.reject_count" min="0" />
                    </div>
                    <div class="space-y-2">
                        <Label>Material Loss (Units)</Label>
                        <Input type="number" v-model="createForm.material_loss_units" min="0" />
                    </div>

                    <div class="space-y-2">
                         <Label>Batch Number</Label>
                         <Input v-model="createForm.batch_number" placeholder="Enter Batch Number" />
                    </div>
                    <!-- Downtime Area -->
                    <div class="col-span-1 sm:col-span-2 md:col-span-3 lg:col-span-2 border rounded-md p-3 space-y-2">
                        <div class="flex items-center justify-between">
                            <Label>Downtime ({{ calculatedDowntime }} min)</Label>
                            <Button type="button" variant="outline" size="sm" @click="addDowntime" class="h-6 text-xs">
                                + Add
                            </Button>
                        </div>
                        
                        <div v-if="createForm.downtime_records.length === 0" class="text-xs text-muted-foreground italic">
                            No downtime recorded.
                        </div>

                        <div v-for="(dt, idx) in createForm.downtime_records" :key="idx" class="flex flex-col gap-2 p-2 border rounded-md mb-2 bg-muted/20">
                            <div class="flex gap-2 items-center">
                                <div class="flex-1">
                                    <Label class="text-xs mb-1 block">Reason</Label>
                                    <select v-model="dt.reason_code_id" class="flex h-8 w-full rounded-md border border-input bg-background px-2 py-1 text-xs">
                                        <option :value="0" disabled>Select Reason...</option>
                                        <option v-for="rc in reasonCodes" :key="rc.id" :value="rc.id">
                                            {{ rc.code }} - {{ rc.description }}
                                        </option>
                                    </select>
                                </div>
                                <Button type="button" variant="ghost" size="icon" @click="removeDowntime(idx)" class="h-8 w-8 text-red-500 mt-5">
                                    <X class="h-3 w-3" />
                                </Button>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <Label class="text-xs mb-1 block">Start</Label>
                                    <Input type="datetime-local" v-model="dt.start_time" class="h-8 text-xs" @change="calculateDowntimeDuration(dt)" />
                                </div>
                                <div>
                                    <Label class="text-xs mb-1 block">End</Label>
                                    <Input type="datetime-local" v-model="dt.end_time" class="h-8 text-xs" @change="calculateDowntimeDuration(dt)" />
                                </div>
                            </div>
                            <div class="text-[10px] text-muted-foreground text-right" v-if="dt.minutes > 0">
                                Duration: {{ dt.minutes }} min
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-2">
                    <Label>Comment (Optional)</Label>
                    <Textarea v-model="createForm.comment" placeholder="Reason for manual entry..." class="resize-none" rows="2" />
                </div>
                
                <!-- Product Changeovers Section -->
                <div class="space-y-3 pt-3 border-t">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Package class="h-4 w-4 text-purple-600" />
                            <Label class="text-sm font-semibold">Product Changeovers (Optional)</Label>
                        </div>
                        <Button type="button" variant="outline" size="sm" @click="addChangeover" class="h-8">
                            <Plus class="h-3 w-3 mr-1" />
                            Add Changeover
                        </Button>
                    </div>
                    
                    <div v-if="createForm.changeovers.length === 0" class="text-xs text-muted-foreground italic p-2 bg-muted/30 rounded">
                        No product changeovers. Add changeovers if the product changed during this shift.
                    </div>
                    
                    <div v-else class="space-y-2">
                        <div v-for="(changeover, idx) in createForm.changeovers" :key="idx" class="p-3 bg-purple-50/30 dark:bg-purple-900/10 rounded-lg border border-purple-200/50 dark:border-purple-800/30 space-y-2">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2 text-xs font-medium text-purple-700 dark:text-purple-400">
                                    <ArrowRight class="h-3 w-3" />
                                    Changeover #{{ idx + 1 }}
                                </div>
                                <Button type="button" variant="ghost" size="sm" @click="removeChangeover(idx)" class="h-6 px-2">
                                    <X class="h-3 w-3" />
                                </Button>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-1.5">
                                    <Label class="text-xs">To Product *</Label>
                                    <select v-model="changeover.to_product_id" class="flex h-9 w-full rounded-md border border-input bg-background px-2 py-1 text-sm">
                                        <option :value="null">Select product...</option>
                                        <option v-for="product in products" :key="product.id" :value="product.id">
                                            {{ product.name }}{{ product.sku ? ` (${product.sku})` : '' }}
                                        </option>
                                    </select>
                                </div>
                                <div class="space-y-1.5">
                                    <Label class="text-xs">Changed At *</Label>
                                    <Input type="datetime-local" v-model="changeover.changed_at" class="h-9 text-sm" />
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-1.5">
                                    <Label class="text-xs">New Batch Number</Label>
                                    <Input v-model="changeover.batch_number" placeholder="Batch #" class="h-9 text-sm font-mono" />
                                </div>
                                <div class="space-y-1.5">
                                    <Label class="text-xs">Notes</Label>
                                    <Input v-model="changeover.notes" placeholder="Reason for change..." class="h-9 text-sm" />
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-3 bg-white dark:bg-black/20 p-2 rounded">
                                <div class="space-y-1.5">
                                    <Label class="text-xs">Good Count</Label>
                                    <Input type="number" v-model="changeover.good_count" class="h-9 text-sm" min="0" />
                                </div>
                                <div class="space-y-1.5">
                                    <Label class="text-xs">Reject Count</Label>
                                    <Input type="number" v-model="changeover.reject_count" class="h-9 text-sm" min="0" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg bg-muted p-3 text-xs flex items-center justify-between">
                     <span class="font-medium text-muted-foreground">Total Output: {{ totalGood }} Good / {{ totalReject }} Reject ({{ totalGood + totalReject }} Total)</span>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="$emit('update:open', false)">Cancel</Button>
                <Button @click="submitCreate" :disabled="loading || !createForm.started_at || !createForm.ended_at">
                    <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                    Create Shift Report
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>


