<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import OperatorLayout from '@/layouts/OperatorLayout.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { 
    Factory, GitCommit, Monitor, ArrowRight, CheckCircle2, AlertTriangle, 
    Play, Square, PauseCircle, Scale, Package, Clock, User, RefreshCw,
    Plus, Minus, History, X
} from 'lucide-vue-next';
import { useOeeStore } from '@/stores/oee';
import DowntimeLogger from '@/components/operator/DowntimeLogger.vue';
import MaterialLossQuickEntry from '@/components/MaterialLossQuickEntry.vue';
import { useToast } from '@/components/ui/toast/use-toast';

// Props (Data from Controller)
const props = defineProps<{
    plants: Array<{
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
    }>;
    products?: Array<{ id: number; name: string; sku?: string }>;
    shifts?: Array<{ id: number; name: string; type: string }>;
    reasonCodes?: Array<{ id: number; name: string; category?: string }>;
    users?: Array<{ id: number; name: string; email: string; groups: string[] }>;
    currentUser?: { id: number; name: string; permissions?: string[]; assigned_plants?: number[]; is_admin?: boolean };
}>();

const oeeStore = useOeeStore();
const { toast } = useToast();

// ============ MACHINE SELECTION STATE ============
const selectedPlantId = ref<number | null>(null);
const selectedLineId = ref<number | null>(null);
const selectedMachineId = ref<number | null>(null);
const step = ref<1|2|3>(1);

const hasPlantAccess = computed(() => {
    if (props.currentUser?.is_admin) return true;
    if (!selectedPlantId.value) return true; 
    const assigned = props.currentUser?.assigned_plants || [];
    // If assigned is empty and user is not admin, maybe they have NO access? 
    // Usually empty implies no restrictions (legacy)? OR strict empty?
    // Based on user request "unless he is assign ... he can only view", implies strict.
    // If assigned is undefined/null (legacy user), maybe default to true?
    // But backend now sends it. So we respect it.
    if (assigned.length === 0 && !props.currentUser?.is_admin) return false; // Strict by default if array exists
    return assigned.includes(selectedPlantId.value);
});

const canManageShift = computed(() => {
    return (props.currentUser?.permissions?.includes('shift.manage') ?? false) && hasPlantAccess.value;
});

const selectedPlant = computed(() => props.plants?.find(p => p.id === selectedPlantId.value));
const selectedLine = computed(() => selectedPlant.value?.lines.find(l => l.id === selectedLineId.value));
const selectedMachine = computed(() => selectedLine.value?.machines.find(m => m.id === selectedMachineId.value));

const selectPlant = (id: number) => { selectedPlantId.value = id; step.value = 2; };
const selectLine = (id: number) => { selectedLineId.value = id; step.value = 3; };
const selectMachine = (id: number) => { 
    selectedMachineId.value = id; 
    loadMachineContext();
};
const resetSelection = () => {
    selectedPlantId.value = null;
    selectedLineId.value = null;
    selectedMachineId.value = null;
    step.value = 1;
    activeShift.value = null;
};

// ============ ACTIVE SHIFT STATE ============
interface ActiveShift {
    id: number;
    machineId: number;
    productId: number;
    productName: string;
    shiftName: string;
    startedAt: Date;
    startedBy: { id: number; name: string; email: string; groups?: string[] };
    userGroup: string | null;
    batchNumber: string | null;
    goodCount: number;
    rejectCount: number;
    scheduledEndAt: string | null;
}

const activeShift = ref<ActiveShift | null>(null);
const isLoading = ref(false);

// Shift Timer
const currentShiftDuration = ref('00:00:00');
let shiftTimerInterval: ReturnType<typeof setInterval> | null = null;

const updateShiftDuration = () => {
    if (!activeShift.value) {
        currentShiftDuration.value = '00:00:00';
        return;
    }
    const startTime = new Date(activeShift.value.startedAt);
    const now = new Date();
    const diffMs = now.getTime() - startTime.getTime();
    const hours = Math.floor(diffMs / 3600000);
    const minutes = Math.floor((diffMs % 3600000) / 60000);
    const seconds = Math.floor((diffMs % 60000) / 1000);
    currentShiftDuration.value = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
};

onMounted(() => {
    shiftTimerInterval = setInterval(updateShiftDuration, 1000);
});
onUnmounted(() => {
    if (shiftTimerInterval) clearInterval(shiftTimerInterval);
});

// ============ LOAD MACHINE CONTEXT ============
const loadMachineContext = async () => {
    if (!selectedMachineId.value) return;
    isLoading.value = true;
    
    try {
        const response = await fetch(`/api/v1/production-shifts/${selectedMachineId.value}`);
        const data = await response.json();
        
        if (data.active_shift) {
            activeShift.value = {
                id: data.active_shift.id,
                machineId: data.active_shift.machine_id,
                productId: data.active_shift.product_id,
                productName: data.active_shift.product_name || data.active_shift.product?.name || 'Unknown',
                shiftName: data.active_shift.shift_name || 'Shift',
                startedAt: new Date(data.active_shift.started_at),
                startedBy: data.active_shift.started_by || { id: 0, name: 'Unknown', email: '' },
                userGroup: data.active_shift.user_group,
                batchNumber: data.active_shift.batch_number,
                goodCount: 0,
                rejectCount: 0,
                scheduledEndAt: data.active_shift.scheduled_end_at || null,
            };
            updateShiftDuration();
        } else {
            activeShift.value = null;
            showStartShiftModal.value = true; // Prompt to start shift
        }
        
        // Also set OEE store filters
        oeeStore.setFilters({
            plantId: selectedPlantId.value,
            lineId: selectedLineId.value,
            machineId: selectedMachineId.value,
            dateFrom: new Date().toISOString().split('T')[0],
            dateTo: new Date().toISOString().split('T')[0],
        });
        
        // Fetch shift activity feed
        if (activeShift.value) {
            fetchShiftActivity(activeShift.value.id);
        } else {
            shiftActivity.value = [];
        }
    } catch (error) {
        console.error('Failed to load machine context', error);
        toast({ title: 'Error', description: 'Failed to load machine data', variant: 'destructive' });
    } finally {
        isLoading.value = false;
    }
};

// ============ SHIFT ACTIVITY FEED ============
interface ShiftActivityItem {
    id: string;
    type: 'downtime' | 'loss' | 'changeover' | 'production';
    title: string;
    description: string;
    timestamp: Date;
    icon: string;
    color: string;
}

const shiftActivity = ref<ShiftActivityItem[]>([]);

const fetchShiftActivity = async (shiftId: number) => {
    try {
        const response = await fetch(`/api/v1/production-shifts/${shiftId}/activity`);
        const data = await response.json();
        
        if (data.success && data.activity) {
            shiftActivity.value = data.activity.map((item: any) => ({
                id: item.id || `${item.type}-${item.timestamp}`,
                type: item.type,
                title: item.title,
                description: item.description,
                timestamp: new Date(item.timestamp),
                icon: item.icon || getActivityIcon(item.type),
                color: item.color || getActivityColor(item.type),
            }));
        }
    } catch (e) {
        console.error('Failed to fetch shift activity:', e);
        // Don't show error toast, just leave activity empty
    }
};

const getActivityIcon = (type: string) => {
    switch (type) {
        case 'downtime': return 'pause';
        case 'loss': return 'scale';
        case 'changeover': return 'package';
        case 'production': return 'plus';
        default: return 'clock';
    }
};

const getActivityColor = (type: string) => {
    switch (type) {
        case 'downtime': return 'amber';
        case 'loss': return 'purple';
        case 'changeover': return 'blue';
        case 'production': return 'green';
        default: return 'gray';
    }
};

const addActivityItem = (type: ShiftActivityItem['type'], title: string, description: string) => {
    const item: ShiftActivityItem = {
        id: `${type}-${Date.now()}`,
        type,
        title,
        description,
        timestamp: new Date(),
        icon: getActivityIcon(type),
        color: getActivityColor(type),
    };
    shiftActivity.value.unshift(item);
};

// ============ START SHIFT ============
const showStartShiftModal = ref(false);
const startShiftForm = ref({
    product_id: '',
    shift_id: '',
    operator_user_id: '',
    batch_number: '',
});
const isStartingShift = ref(false);

const handleStartShift = async () => {
    if (!selectedMachineId.value || !startShiftForm.value.product_id || !startShiftForm.value.shift_id) {
        toast({ title: 'Validation', description: 'Please select Product and Shift', variant: 'destructive' });
        return;
    }
    
    isStartingShift.value = true;
    try {
        const response = await fetch(`/api/v1/production-shifts/${selectedMachineId.value}/start`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                product_id: parseInt(startShiftForm.value.product_id),
                shift_id: parseInt(startShiftForm.value.shift_id),
                operator_user_id: startShiftForm.value.operator_user_id ? parseInt(startShiftForm.value.operator_user_id) : props.currentUser?.id,
                batch_number: startShiftForm.value.batch_number,
            }),
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showStartShiftModal.value = false;
            toast({ title: 'Shift Started', description: 'Production is now live.' });
            await loadMachineContext();
        } else {
            toast({ title: 'Error', description: data.error || 'Failed to start shift', variant: 'destructive' });
        }
    } catch (e) {
        toast({ title: 'Error', description: 'Failed to start shift.', variant: 'destructive' });
    } finally {
        isStartingShift.value = false;
    }
};

// ============ END SHIFT ============
const showEndShiftModal = ref(false);
const endShiftForm = ref({
    good_count: null as number | null,
    reject_count: null as number | null,
    notes: '',
    early_exit_reason_id: '',
});
const productRuns = ref<Array<{
    product_id: number;
    product_name: string;
    duration_minutes: number;
    start_time?: string;
    end_time?: string;
    good_count: number | null;
    reject_count: number | null;
}>>([]);
const hasChangeovers = ref(false);

watch(showEndShiftModal, async (isOpen) => {
    if (isOpen && selectedMachineId.value) {
        // Reset state
        productRuns.value = [];
        hasChangeovers.value = false;
        
        // Fetch product runs
        try {
            const response = await fetch(`/admin/production-shifts/${selectedMachineId.value}/product-runs`);
            const data = await response.json();
            
            if (data.success && data.has_changeovers) {
                hasChangeovers.value = true;
                productRuns.value = data.product_runs.map((p: any) => ({
                    ...p,
                    good_count: null,
                    reject_count: null
                }));
            }
        } catch (e) {
            console.error('Failed to fetch product runs:', e);
        }
    }
});

const isEarlyExit = ref(false);
const earlyExitDuration = ref(0);

watch(showEndShiftModal, (isOpen) => {
    if (isOpen && activeShift.value?.scheduledEndAt) {
        const scheduledEnd = new Date(activeShift.value.scheduledEndAt).getTime();
        const now = new Date().getTime();
        // Tolerance of 5 minutes before scheduled end
        if (now < scheduledEnd - (5 * 60 * 1000)) {
            isEarlyExit.value = true;
            earlyExitDuration.value = Math.round((scheduledEnd - now) / 60000);
        } else {
            isEarlyExit.value = false;
        }
    } else {
        isEarlyExit.value = false;
    }
});

const handleEndShift = async () => {
    if (!selectedMachineId.value) return;

    if (isEarlyExit.value && !endShiftForm.value.early_exit_reason_id) {
        toast({ title: 'Validation Required', description: 'Please select a reason for ending the shift early.', variant: 'destructive' });
        return;
    }
    
    try {
        const response = await fetch(`/api/v1/production-shifts/${selectedMachineId.value}/end`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                good_count: endShiftForm.value.good_count,
                reject_count: endShiftForm.value.reject_count,
                comment: endShiftForm.value.notes,
                product_counts: hasChangeovers.value ? productRuns.value : undefined,
                early_exit_reason_id: isEarlyExit.value && endShiftForm.value.early_exit_reason_id ? parseInt(endShiftForm.value.early_exit_reason_id) : undefined
            }),
        });
        
        if (response.ok) {
            showEndShiftModal.value = false;
            activeShift.value = null;
            toast({ title: 'Shift Ended', description: 'Production shift has been completed.' });
            // Reset form
            endShiftForm.value = { good_count: null, reject_count: null, notes: '', early_exit_reason_id: '' };
            isEarlyExit.value = false;
        } else {
            const data = await response.json();
            toast({ title: 'Error', description: data.error || 'Failed to end shift', variant: 'destructive' });
        }
    } catch (e) {
        toast({ title: 'Error', description: 'Failed to end shift.', variant: 'destructive' });
    }
};

// ============ LOG DOWNTIME (Multi-Entry) ============
const showDowntimeModal = ref(false);
const downtimeEntries = ref<any[]>([]);
const downtimeForm = ref({
    reason_code_id: '',
    start_time: '',
    end_time: '',
    comment: '',
});
const isSubmittingDowntime = ref(false);

const openDowntimeDialog = () => {
    downtimeEntries.value = [];
    // Set default times to now
    const now = new Date();
    const fiveMinutesAgo = new Date(now.getTime() - 5 * 60000);
    downtimeForm.value = {
        reason_code_id: '',
        start_time: fiveMinutesAgo.toISOString().slice(0, 16),
        end_time: now.toISOString().slice(0, 16),
        comment: ''
    };
    showDowntimeModal.value = true;
};

const addDowntimeEntry = () => {
    if (!downtimeForm.value.reason_code_id || !downtimeForm.value.start_time || !downtimeForm.value.end_time) {
        toast({ title: 'Validation Error', description: 'Please complete the form to add an entry.', variant: 'destructive' });
        return;
    }
    const start = new Date(downtimeForm.value.start_time);
    const end = new Date(downtimeForm.value.end_time);
    if (end <= start) {
        toast({ title: 'Validation Error', description: 'End time must be after start time.', variant: 'destructive' });
        return;
    }
    
    // Add to list
    downtimeEntries.value.push({
        reason_code_id: downtimeForm.value.reason_code_id,
        start_time: downtimeForm.value.start_time,
        end_time: downtimeForm.value.end_time,
        comment: downtimeForm.value.comment
    });
    
    // Reset form for next entry (next start = previous end)
    downtimeForm.value.start_time = downtimeForm.value.end_time;
    const newEnd = new Date(new Date(downtimeForm.value.end_time).getTime() + 5 * 60000);
    downtimeForm.value.end_time = newEnd.toISOString().slice(0, 16);
    downtimeForm.value.reason_code_id = '';
    downtimeForm.value.comment = '';
    
    toast({ title: 'Added', description: 'Entry added to list.' });
};

const removeDowntimeEntry = (index: number) => {
    downtimeEntries.value.splice(index, 1);
};

const handleLogDowntime = async () => {
    if (!selectedMachineId.value) return;
    
    const hasEntries = downtimeEntries.value.length > 0;
    const hasValidForm = downtimeForm.value.reason_code_id && downtimeForm.value.start_time && downtimeForm.value.end_time;

    if (!hasEntries && !hasValidForm) {
        toast({ title: 'Validation Error', description: 'Please add at least one downtime entry.', variant: 'destructive' });
        return;
    }
    
    isSubmittingDowntime.value = true;
    
    try {
        let payload: any = {};
        
        if (hasEntries) {
            const events = [...downtimeEntries.value];
            if (hasValidForm) {
                events.push({
                    reason_code_id: downtimeForm.value.reason_code_id,
                    start_time: downtimeForm.value.start_time,
                    end_time: downtimeForm.value.end_time,
                    comment: downtimeForm.value.comment
                });
            }
            
            payload = {
                events: events.map(e => ({
                    ...e,
                    start_time: new Date(e.start_time).toISOString(),
                    end_time: new Date(e.end_time).toISOString()
                }))
            };
        } else {
            const start = new Date(downtimeForm.value.start_time);
            const end = new Date(downtimeForm.value.end_time);
            if (end <= start) {
                toast({ title: 'Validation Error', description: 'End time must be after start time.', variant: 'destructive' });
                isSubmittingDowntime.value = false;
                return;
            }
            
            payload = {
                reason_code_id: downtimeForm.value.reason_code_id,
                start_time: start.toISOString(),
                end_time: end.toISOString(),
                comment: downtimeForm.value.comment
            };
        }

        const response = await fetch(`/admin/production-shifts/${selectedMachineId.value}/downtime`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify(payload)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showDowntimeModal.value = false;
            const count = hasEntries ? downtimeEntries.value.length + (hasValidForm ? 1 : 0) : 1;
            toast({ title: 'Downtime Logged', description: `${count} event(s) recorded.` });
            
            // Refresh activity from API to get accurate data
            if (activeShift.value) {
                fetchShiftActivity(activeShift.value.id);
            }
            
            downtimeEntries.value = [];
            downtimeForm.value = { reason_code_id: '', start_time: '', end_time: '', comment: '' };
        } else {
            toast({ title: 'Error', description: data.error || 'Failed to log downtime', variant: 'destructive' });
        }
    } catch (e) {
        toast({ title: 'Error', description: 'Failed to log downtime.', variant: 'destructive' });
    } finally {
        isSubmittingDowntime.value = false;
    }
};

// ============ CHANGE PRODUCT ============
const showChangeProductModal = ref(false);
const changeProductForm = ref({
    to_product_id: '',
    batch_number: '',
    notes: '',
});
const isChangingProduct = ref(false);

const handleChangeProduct = async () => {
    if (!activeShift.value || !changeProductForm.value.to_product_id) return;
    
    isChangingProduct.value = true;
    try {
        const response = await fetch(`/api/v1/production-shifts/shift/${activeShift.value.id}/changeover`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                to_product_id: parseInt(changeProductForm.value.to_product_id),
                batch_number: changeProductForm.value.batch_number,
                notes: changeProductForm.value.notes,
            }),
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            // Update active shift with new product
            if (activeShift.value) {
                activeShift.value.productId = parseInt(changeProductForm.value.to_product_id);
                const product = props.products?.find(p => p.id === parseInt(changeProductForm.value.to_product_id));
                if (product) activeShift.value.productName = product.name;
                if (data.changeover?.batch_number) {
                    activeShift.value.batchNumber = data.changeover.batch_number;
                }
            }
            showChangeProductModal.value = false;
            changeProductForm.value = { to_product_id: '', batch_number: '', notes: '' };
            const newProductName = data.changeover?.to_product?.name || 'New Product';
            toast({ title: 'Product Changed', description: `Now producing: ${newProductName}` });
            // Refresh activity from API
            if (activeShift.value) {
                fetchShiftActivity(activeShift.value.id);
            }
        } else {
            toast({ title: 'Error', description: data.error || 'Failed to change product', variant: 'destructive' });
        }
    } catch (e) {
        toast({ title: 'Error', description: 'Failed to change product.', variant: 'destructive' });
    } finally {
        isChangingProduct.value = false;
    }
};

// ============ LOG MATERIAL LOSS ============
const showMaterialLossModal = ref(false);
const materialLossCategories = ref<any[]>([]);
const materialLossProducts = ref<any[]>([]);  // Products available for loss logging

// Compute active product with required fields for MaterialLossQuickEntry
const activeProduct = computed(() => {
    if (!activeShift.value) return null;
    const product = props.products?.find(p => p.id === activeShift.value?.productId);
    if (!product) return null;
    return {
        id: product.id,
        name: product.name,
        unit_of_measure: (product as any).unit_of_measure || 'units',
        finished_unit: (product as any).finished_unit || null,
        fill_volume: (product as any).fill_volume || null,
        fill_volume_unit: (product as any).fill_volume_unit || null,
    };
});

// Fetch loss categories when modal opens
const fetchLossCategories = async () => {
    try {
        const response = await fetch('/api/v1/material-loss/categories');
        const data = await response.json();
        materialLossCategories.value = data.categories || [];
    } catch (e) {
        console.error('Failed to load loss categories:', e);
    }
};

const openMaterialLossDialog = async () => {
    fetchLossCategories();
    materialLossProducts.value = [];
    
    if (selectedMachineId.value) {
        // Fetch product runs to get all products used during shift
        try {
            const response = await fetch(`/admin/production-shifts/${selectedMachineId.value}/product-runs`);
            const data = await response.json();
            
            if (data.success && data.product_runs && data.product_runs.length > 0) {
                const productMap = new Map<number, any>();
                for (const run of data.product_runs) {
                    if (run.product_id && !productMap.has(run.product_id)) {
                        const fullProduct = props.products?.find((p: any) => p.id === run.product_id);
                        if (fullProduct) {
                            productMap.set(run.product_id, {
                                id: fullProduct.id,
                                name: fullProduct.name,
                                unit_of_measure: fullProduct.unit_of_measure || 'units',
                                finished_unit: fullProduct.finished_unit || null,
                                fill_volume: fullProduct.fill_volume || null,
                                fill_volume_unit: fullProduct.fill_volume_unit || null,
                            });
                        } else {
                            productMap.set(run.product_id, {
                                id: run.product_id,
                                name: run.product_name,
                                unit_of_measure: 'units',
                                finished_unit: 'units',
                                fill_volume: null,
                                fill_volume_unit: null,
                            });
                        }
                    }
                }
                materialLossProducts.value = Array.from(productMap.values());
            }
        } catch (e) {
            console.error('Failed to fetch product runs for material loss:', e);
        }
        
        // If no products from runs, use current active product
        if (materialLossProducts.value.length === 0 && activeProduct.value) {
            materialLossProducts.value = [activeProduct.value];
        }
    }
    
    showMaterialLossModal.value = true;
};

const onMaterialLossSuccess = () => {
    showMaterialLossModal.value = false;
    toast({ title: 'Loss Logged', description: 'Material loss entries have been recorded.' });
    // Refresh activity from API to get accurate loss details
    if (activeShift.value) {
        fetchShiftActivity(activeShift.value.id);
    }
};

// Quick downtime from grid
const handleQuickDowntime = async (reason: any) => {
    if (!selectedMachineId.value) return;
    
    try {
        await fetch('/api/v1/ingest/downtime', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                machine_id: selectedMachineId.value,
                reason_code_id: reason.id,
                start_time: new Date().toISOString(),
                duration_seconds: 60, // 1 min default for quick taps
            }),
        });
        
        toast({ title: 'Quick Stop Logged', description: `${reason.name} (1 min)` });
    } catch (e) {
        toast({ title: 'Error', description: 'Failed to log downtime.', variant: 'destructive' });
    }
};

// ============ LOG PRODUCTION ============
const handleLogProduction = async (good: number, reject: number) => {
    if (!selectedMachineId.value || !activeShift.value) {
        toast({ title: 'Error', description: 'No active shift', variant: 'destructive' });
        return;
    }
    
    try {
        await fetch('/api/v1/ingest/production', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                machine_id: selectedMachineId.value,
                product_id: activeShift.value.productId,
                good_count: good,
                reject_count: reject,
            }),
        });
        
        // Update local counts
        if (activeShift.value) {
            activeShift.value.goodCount += good;
            activeShift.value.rejectCount += reject;
        }
        
        const label = good > 0 ? 'Good Units' : 'Rejects';
        toast({ title: 'Count Added', description: `+${good || reject} ${label}` });
    } catch (e) {
        toast({ title: 'Error', description: 'Failed to log production.', variant: 'destructive' });
    }
};

// Helpers
const formatDate = (date: Date) => new Date(date).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
const formatTime = (date: Date) => new Date(date).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <OperatorLayout>
        <Head title="Operator Interface" />

        <!-- ============ MACHINE SELECTION WIZARD ============ -->
        <div v-if="!selectedMachineId" class="flex-1 flex flex-col items-center justify-center p-8 bg-slate-50 dark:bg-slate-950">
           <div class="max-w-4xl w-full space-y-8">
                <div class="text-center space-y-2">
                    <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
                        {{ step === 1 ? 'Select Plant' : step === 2 ? 'Select Line' : 'Select Machine' }}
                    </h2>
                    <p class="text-slate-500 dark:text-slate-400">
                        {{ step === 1 ? 'Choose your facility to begin.' : step === 2 ? `Select a production line in ${selectedPlant?.name}.` : `Choose the machine you are operating.` }}
                    </p>
                </div>

                <!-- Step 1: Plants -->
                <div v-if="step === 1" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <button v-for="plant in plants" :key="plant.id" @click="selectPlant(plant.id)"
                        class="group relative flex flex-col items-center p-8 bg-white dark:bg-slate-900 rounded-2xl shadow-sm border-2 border-slate-200 dark:border-slate-800 hover:border-blue-500 transition-all hover:shadow-lg">
                        <div class="bg-blue-100 dark:bg-blue-900/30 p-4 rounded-full mb-4 group-hover:scale-110 transition-transform">
                            <Factory class="h-8 w-8 text-blue-600 dark:text-blue-400" />
                        </div>
                        <h3 class="text-xl font-semibold text-slate-900 dark:text-white">{{ plant.name }}</h3>
                        <p class="text-sm text-slate-500 mt-2">{{ plant.lines?.length || 0 }} Lines</p>
                    </button>
                    <div v-if="!plants || plants.length === 0" class="col-span-full text-center text-muted-foreground">
                        No plants found.
                    </div>
                </div>

                <!-- Step 2: Lines -->
                <div v-if="step === 2" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <button v-for="line in selectedPlant?.lines" :key="line.id" @click="selectLine(line.id)"
                            class="group relative flex flex-col items-center p-8 bg-white dark:bg-slate-900 rounded-2xl shadow-sm border-2 border-slate-200 dark:border-slate-800 hover:border-indigo-500 transition-all hover:shadow-lg">
                            <div class="bg-indigo-100 dark:bg-indigo-900/30 p-4 rounded-full mb-4 group-hover:scale-110 transition-transform">
                                <GitCommit class="h-8 w-8 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <h3 class="text-xl font-semibold text-slate-900 dark:text-white">{{ line.name }}</h3>
                            <p class="text-sm text-slate-500 mt-2">{{ line.machines?.length || 0 }} Machines</p>
                        </button>
                    </div>
                    <div class="flex justify-center">
                        <Button variant="ghost" @click="step = 1">Back to Plants</Button>
                    </div>
                </div>

                <!-- Step 3: Machines -->
                <div v-if="step === 3" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <button v-for="machine in selectedLine?.machines" :key="machine.id" @click="selectMachine(machine.id)"
                            class="group relative flex flex-col items-center p-8 bg-white dark:bg-slate-900 rounded-2xl shadow-sm border-2 border-slate-200 dark:border-slate-800 hover:border-emerald-500 transition-all hover:shadow-lg">
                            <div class="bg-emerald-100 dark:bg-emerald-900/30 p-4 rounded-full mb-4 group-hover:scale-110 transition-transform">
                                <Monitor class="h-8 w-8 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <h3 class="text-xl font-semibold text-slate-900 dark:text-white">{{ machine.name }}</h3>
                            <div class="flex items-center gap-1 mt-2 text-emerald-600 text-sm font-medium opacity-0 group-hover:opacity-100">
                                Select <ArrowRight class="h-4 w-4" />
                            </div>
                        </button>
                    </div>
                    <div class="flex justify-center">
                        <Button variant="ghost" @click="step = 2">Back to Lines</Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ OPERATOR DASHBOARD ============ -->
        <div v-else class="flex-1 flex flex-col overflow-hidden min-h-screen md:h-[calc(100vh-65px)]">
            
            <!-- Header Bar -->
            <div class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 md:px-6 py-3 md:py-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 sm:gap-0">
                <div class="flex items-center gap-2 md:gap-4 w-full sm:w-auto">
                    <button @click="resetSelection" class="text-xs md:text-sm text-slate-500 hover:text-blue-500 transition-colors flex items-center gap-1 shrink-0">
                        <ArrowRight class="h-3 w-3 md:h-4 md:w-4 rotate-180" /> <span class="hidden sm:inline">Change Machine</span><span class="sm:hidden">Back</span>
                    </button>
                    <div class="h-4 md:h-6 w-px bg-slate-200 dark:bg-slate-700 hidden sm:block"></div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-base md:text-xl font-bold text-slate-900 dark:text-white truncate">{{ selectedMachine?.name }}</h2>
                        <p class="text-[10px] md:text-xs text-slate-500 truncate">{{ selectedPlant?.name }} • {{ selectedLine?.name }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 self-end sm:self-auto">
                    <Badge v-if="activeShift" variant="outline" class="bg-green-100 text-green-700 border-green-300 font-mono text-sm md:text-lg px-3 md:px-4 py-1 md:py-2 animate-pulse">
                        {{ currentShiftDuration }}
                    </Badge>
                    <Badge v-else variant="outline" class="bg-slate-100 text-slate-500 text-xs md:text-sm">
                        No Active Shift
                    </Badge>
                </div>
            </div>

            <!-- View Only Banner -->
            <div v-if="!hasPlantAccess" class="bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300 px-4 py-2 text-sm font-medium text-center border-b border-amber-200 dark:border-amber-800 animate-in fade-in slide-in-from-top-1">
                <AlertTriangle class="inline-block h-4 w-4 mr-2 -mt-0.5" />
                View Only Mode: You are not assigned to this plant.
            </div>

            <!-- Main Content -->
            <div class="flex-1 flex flex-col md:grid md:grid-cols-12 gap-0 overflow-y-auto md:overflow-hidden">
                
                <!-- Left Panel: Shift Status & Controls -->
                <div class="md:col-span-4 bg-white dark:bg-slate-900 md:border-r border-b md:border-b-0 border-slate-200 dark:border-slate-800 p-4 md:p-6 flex flex-col md:overflow-y-auto">
                    
                    <!-- Active Shift Card -->
                    <Card v-if="activeShift" class="border-green-500 bg-green-50 dark:bg-green-950/20 mb-4 md:mb-6">
                        <CardHeader class="pb-2 md:pb-3">
                            <div class="flex items-center gap-2 md:gap-3">
                                <div class="h-10 w-10 md:h-12 md:w-12 rounded-full bg-green-600 flex items-center justify-center shrink-0">
                                    <Play class="h-5 w-5 md:h-6 md:w-6 text-white" />
                                </div>
                                <div>
                                    <CardTitle class="text-green-700 dark:text-green-400">Shift In Progress</CardTitle>
                                    <div class="flex items-center gap-2 mt-1">
                                        <Badge variant="outline" class="bg-white/50 border-green-200 text-green-800 text-xs">
                                            {{ activeShift.productName }}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="pt-0 space-y-3">
                            <div class="flex items-center gap-3 p-3 bg-white dark:bg-gray-900/50 rounded-lg border text-sm">
                                <User class="h-4 w-4 text-muted-foreground" />
                                <div>
                                    <p class="font-medium">{{ activeShift.startedBy.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ activeShift.userGroup || 'No group' }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 p-3 bg-white dark:bg-gray-900/50 rounded-lg border text-sm">
                                <Clock class="h-4 w-4 text-muted-foreground" />
                                <div>
                                    <p class="font-medium font-mono">{{ formatTime(activeShift.startedAt) }}</p>
                                    <p class="text-xs text-muted-foreground">{{ formatDate(activeShift.startedAt) }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    
                    <!-- No Active Shift -->
                    <div v-else class="flex-1 flex flex-col items-center justify-center p-8 border border-dashed rounded-lg bg-muted/20 mb-6">
                        <h3 class="text-lg font-bold mb-2">No Active Shift</h3>
                        <p class="text-sm text-muted-foreground mb-4">{{ formatDate(new Date()) }}</p>
                        <Button v-if="!activeShift && canManageShift" size="lg" class="w-full md:w-auto bg-green-600 hover:bg-green-700 h-16 text-lg shadow-lg shadow-green-900/20 animate-pulse" @click="showStartShiftModal = true">
                            <PlayCircle class="mr-2 h-6 w-6" /> Start Shift
                        </Button>
                        <!-- Show placeholder if no shift and cannot manage -->
                        <div v-if="!activeShift && !canManageShift" class="px-4 py-3 bg-muted rounded text-muted-foreground text-sm">
                            Waiting for shift start...
                        </div>
                    </div>

                    <!-- Action Buttons (when shift active) -->
                    <div v-if="activeShift" class="flex flex-col gap-3 mt-4">
                        <!-- Primary Actions Row -->
                        <div class="grid grid-cols-2 gap-3">
                            <Button size="lg" :disabled="!hasPlantAccess" class="bg-amber-500 hover:bg-amber-600 text-white h-16 text-base font-semibold" @click="openDowntimeDialog">
                                <PauseCircle class="h-5 w-5 mr-2" /> Log Downtime
                            </Button>
                            <Button size="lg" :disabled="!hasPlantAccess" class="bg-purple-500 hover:bg-purple-600 text-white h-16 text-base font-semibold" @click="openMaterialLossDialog">
                                <Scale class="h-5 w-5 mr-2" /> Log Loss
                            </Button>
                        </div>
                        <!-- Secondary Actions Row -->
                        <div class="grid grid-cols-2 gap-3">
                            <Button size="lg" :disabled="!hasPlantAccess" class="bg-blue-500 hover:bg-blue-600 text-white h-16 text-base font-semibold" @click="showChangeProductModal = true">
                                <Package class="h-5 w-5 mr-2" /> Change Product
                            </Button>
                            <Button v-if="canManageShift" size="lg" variant="destructive" class="h-16 text-base font-semibold" @click="showEndShiftModal = true">
                                <Square class="h-5 w-5 mr-2" /> End Shift
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Right Panel: Production Counters & Quick Downtime -->
                <div class="md:col-span-8 bg-slate-50 dark:bg-slate-950 p-4 md:p-6 flex flex-col gap-4 md:gap-6 flex-1 md:overflow-y-auto">
                    
                    <!-- Production Counters -->
                    <div class="grid grid-cols-2 gap-3 md:gap-6">
                        <button @click="handleLogProduction(1, 0)" :disabled="!activeShift || !hasPlantAccess"
                            class="bg-white dark:bg-slate-900 rounded-xl md:rounded-2xl shadow-lg p-4 md:p-8 flex flex-col relative overflow-hidden transition-all active:scale-[0.98] border-l-4 md:border-l-8 border-green-500 text-left disabled:opacity-50 disabled:cursor-not-allowed min-h-[120px] md:min-h-0">
                            <div class="text-xs md:text-sm font-bold text-slate-500 mb-1 md:mb-2 uppercase tracking-wider">Good Units</div>
                            <div class="text-4xl md:text-7xl font-black text-slate-900 dark:text-white">
                                {{ activeShift?.goodCount || 0 }}
                            </div>
                            <div class="absolute bottom-3 md:bottom-6 right-3 md:right-6 bg-green-100 dark:bg-green-900/30 p-2 md:p-3 rounded-full">
                                <Plus class="h-5 w-5 md:h-8 md:w-8 text-green-600" />
                            </div>
                        </button>

                        <button @click="handleLogProduction(0, 1)" :disabled="!activeShift || !hasPlantAccess"
                            class="bg-white dark:bg-slate-900 rounded-xl md:rounded-2xl shadow-lg p-4 md:p-8 flex flex-col relative overflow-hidden transition-all active:scale-[0.98] border-l-4 md:border-l-8 border-red-500 text-left disabled:opacity-50 disabled:cursor-not-allowed min-h-[120px] md:min-h-0">
                            <div class="text-xs md:text-sm font-bold text-slate-500 mb-1 md:mb-2 uppercase tracking-wider">Rejects</div>
                            <div class="text-4xl md:text-7xl font-black text-red-500">
                                {{ activeShift?.rejectCount || 0 }}
                            </div>
                            <div class="absolute bottom-3 md:bottom-6 right-3 md:right-6 bg-red-100 dark:bg-red-900/30 p-2 md:p-3 rounded-full">
                                <Plus class="h-5 w-5 md:h-8 md:w-8 text-red-600" />
                            </div>
                        </button>
                    </div>

                    <!-- Shift Activity Feed - 3 Column Layout -->
                    <div v-if="activeShift" class="grid grid-cols-1 md:grid-cols-3 gap-4 flex-1">
                        <!-- Downtime Column -->
                        <Card class="overflow-hidden">
                            <CardHeader class="py-2 px-3 border-b bg-amber-50 dark:bg-amber-900/20">
                                <CardTitle class="text-xs font-semibold flex items-center gap-2 text-amber-700 dark:text-amber-400">
                                    <PauseCircle class="h-3.5 w-3.5" /> Downtime Events
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="p-0 overflow-y-auto max-h-[250px]">
                                <div v-if="shiftActivity.filter(a => a.type === 'downtime').length === 0" class="flex flex-col items-center justify-center py-8 text-muted-foreground">
                                    <PauseCircle class="h-6 w-6 mb-2 opacity-30" />
                                    <p class="text-xs">No downtime logged</p>
                                </div>
                                <div v-else class="divide-y">
                                    <div v-for="item in shiftActivity.filter(a => a.type === 'downtime')" :key="item.id" class="p-2.5 hover:bg-amber-50/50 dark:hover:bg-amber-900/10">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-amber-800 dark:text-amber-300 truncate">{{ item.title }}</p>
                                                <p class="text-xs text-amber-600 dark:text-amber-500 font-mono">{{ item.description }}</p>
                                            </div>
                                            <span class="text-[10px] text-muted-foreground whitespace-nowrap">
                                                {{ item.timestamp.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Material Loss Column -->
                        <Card class="overflow-hidden">
                            <CardHeader class="py-2 px-3 border-b bg-purple-50 dark:bg-purple-900/20">
                                <CardTitle class="text-xs font-semibold flex items-center gap-2 text-purple-700 dark:text-purple-400">
                                    <Scale class="h-3.5 w-3.5" /> Material Losses
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="p-0 overflow-y-auto max-h-[250px]">
                                <div v-if="shiftActivity.filter(a => a.type === 'loss').length === 0" class="flex flex-col items-center justify-center py-8 text-muted-foreground">
                                    <Scale class="h-6 w-6 mb-2 opacity-30" />
                                    <p class="text-xs">No losses logged</p>
                                </div>
                                <div v-else class="divide-y">
                                    <div v-for="item in shiftActivity.filter(a => a.type === 'loss')" :key="item.id" class="p-2.5 hover:bg-purple-50/50 dark:hover:bg-purple-900/10">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-purple-800 dark:text-purple-300 truncate">{{ item.title }}</p>
                                                <p class="text-xs text-purple-600 dark:text-purple-500 font-mono">{{ item.description }}</p>
                                            </div>
                                            <span class="text-[10px] text-muted-foreground whitespace-nowrap">
                                                {{ item.timestamp.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Changeovers Column -->
                        <Card class="overflow-hidden">
                            <CardHeader class="py-2 px-3 border-b bg-blue-50 dark:bg-blue-900/20">
                                <CardTitle class="text-xs font-semibold flex items-center gap-2 text-blue-700 dark:text-blue-400">
                                    <Package class="h-3.5 w-3.5" /> Product Changes
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="p-0 overflow-y-auto max-h-[250px]">
                                <div v-if="shiftActivity.filter(a => a.type === 'changeover').length === 0" class="flex flex-col items-center justify-center py-8 text-muted-foreground">
                                    <Package class="h-6 w-6 mb-2 opacity-30" />
                                    <p class="text-xs">No changeovers</p>
                                </div>
                                <div v-else class="divide-y">
                                    <div v-for="item in shiftActivity.filter(a => a.type === 'changeover')" :key="item.id" class="p-2.5 hover:bg-blue-50/50 dark:hover:bg-blue-900/10">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-blue-800 dark:text-blue-300 truncate">{{ item.title }}</p>
                                                <p class="text-xs text-blue-600 dark:text-blue-500">{{ item.description }}</p>
                                            </div>
                                            <span class="text-[10px] text-muted-foreground whitespace-nowrap">
                                                {{ item.timestamp.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ START SHIFT MODAL ============ -->
        <Dialog :open="showStartShiftModal" @update:open="(val) => showStartShiftModal = val">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Start Production Shift</DialogTitle>
                    <DialogDescription>
                        Select the product and shift pattern to begin operations on {{ selectedMachine?.name }}.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-3 md:gap-4 py-4">
                    <div class="flex flex-col md:grid md:grid-cols-4 items-start md:items-center gap-2 md:gap-4">
                        <Label class="md:text-right">Product *</Label>
                        <Select :model-value="startShiftForm.product_id" @update:model-value="(val: any) => startShiftForm.product_id = String(val || '')">
                            <SelectTrigger class="col-span-3">
                                <SelectValue placeholder="Select Product" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="product in products" :key="product.id" :value="String(product.id)">
                                    {{ product.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col md:grid md:grid-cols-4 items-start md:items-center gap-2 md:gap-4">
                        <Label class="md:text-right">Shift *</Label>
                        <Select :model-value="startShiftForm.shift_id" @update:model-value="(val: any) => startShiftForm.shift_id = String(val || '')">
                            <SelectTrigger class="col-span-3">
                                <SelectValue placeholder="Select Shift" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="shift in shifts" :key="shift.id" :value="String(shift.id)">
                                    {{ shift.name }} ({{ shift.type }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col md:grid md:grid-cols-4 items-start md:items-center gap-2 md:gap-4">
                        <Label class="md:text-right">Operator</Label>
                        <Select :model-value="startShiftForm.operator_user_id" @update:model-value="(val: any) => startShiftForm.operator_user_id = String(val || '')">
                            <SelectTrigger class="col-span-3 w-full">
                                <SelectValue :placeholder="currentUser?.name || 'Current User'" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="user in users" :key="user.id" :value="String(user.id)">
                                    {{ user.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col md:grid md:grid-cols-4 items-start md:items-center gap-2 md:gap-4">
                         <Label class="md:text-right">Batch #</Label>
                         <Input v-model="startShiftForm.batch_number" class="col-span-3 w-full" placeholder="Optional" />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showStartShiftModal = false">Cancel</Button>
                    <Button @click="handleStartShift" :disabled="isStartingShift" class="bg-green-600 hover:bg-green-700">
                        <RefreshCw v-if="isStartingShift" class="mr-2 h-4 w-4 animate-spin" />
                        <Play v-else class="mr-2 h-4 w-4" />
                        Start Shift
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ============ END SHIFT MODAL ============ -->
        <Dialog v-model:open="showEndShiftModal">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>End Production Shift</DialogTitle>
                    <DialogDescription>
                        Confirm the final counts before ending this shift.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-3 md:gap-4 py-4">
                    <div v-if="hasChangeovers" class="space-y-4">
                         <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-md text-sm text-blue-700 dark:text-blue-300">
                            Changeovers detected. Please enter production counts for each product run.
                        </div>
                        
                        <div v-for="(run, index) in productRuns" :key="index" class="border rounded-md p-3 space-y-3">
                            <div class="font-medium flex justify-between items-center text-sm">
                                <div class="flex items-center gap-2">
                                    <Badge variant="outline">{{ run.product_name }}</Badge>
                                    <span class="text-muted-foreground text-xs">
                                        ({{ Math.round(run.duration_minutes) }} min)
                                    </span>
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ new Date(run.start_time!).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }} - 
                                    {{ new Date(run.end_time!).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }}
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div class="grid gap-2">
                                    <Label class="text-xs">Good Count <span class="text-red-500">*</span></Label>
                                    <Input 
                                        type="number" 
                                        min="0"
                                        :model-value="run.good_count ?? ''"
                                        @update:model-value="(val) => run.good_count = val === '' ? null : Number(val)"
                                        placeholder="0"
                                        class="h-9"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label class="text-xs">Rejects <span class="text-red-500">*</span></Label>
                                    <Input 
                                        type="number" 
                                        min="0"
                                        :model-value="run.reject_count ?? ''"
                                        @update:model-value="(val) => run.reject_count = val === '' ? null : Number(val)"
                                        placeholder="0"
                                        class="h-9"
                                    />
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center text-sm font-medium pt-2 border-t">
                            <span>Total Output:</span>
                            <div>
                                <span class="text-green-600">Good: {{ productRuns.reduce((sum, p) => sum + (p.good_count || 0), 0) }}</span>
                                <span class="mx-2 text-gray-300">|</span>
                                <span class="text-red-600">Reject: {{ productRuns.reduce((sum, p) => sum + (p.reject_count || 0), 0) }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div v-else class="space-y-4">
                        <div class="flex flex-col md:grid md:grid-cols-4 items-start md:items-center gap-2 md:gap-4">
                            <Label class="md:text-right">Good Count</Label>
                            <Input 
                                type="number" 
                                :model-value="endShiftForm.good_count ?? ''" 
                                @update:model-value="(val) => endShiftForm.good_count = val === '' ? null : Number(val)"
                                class="col-span-3 w-full" 
                                :placeholder="String(activeShift?.goodCount ?? 0)" 
                            />
                        </div>
                        <div class="flex flex-col md:grid md:grid-cols-4 items-start md:items-center gap-2 md:gap-4">
                            <Label class="md:text-right">Reject Count</Label>
                            <Input 
                                type="number" 
                                :model-value="endShiftForm.reject_count ?? ''" 
                                @update:model-value="(val) => endShiftForm.reject_count = val === '' ? null : Number(val)"
                                class="col-span-3 w-full" 
                                :placeholder="String(activeShift?.rejectCount ?? 0)" 
                            />
                        </div>
                    </div>
                    
                    <!-- Early Exit Prompt -->
                    <div v-if="isEarlyExit" class="space-y-3 bg-amber-50 dark:bg-amber-950/30 p-4 rounded-lg border border-amber-200 dark:border-amber-800">
                        <div class="flex items-start gap-3">
                            <AlertTriangle class="h-5 w-5 text-amber-600 dark:text-amber-500 mt-0.5" />
                            <div>
                                <h4 class="font-semibold text-amber-800 dark:text-amber-400">Early Exit Detected</h4>
                                <p class="text-sm text-amber-700 dark:text-amber-500 mt-1">
                                    You are ending the shift {{ earlyExitDuration }} minutes early. Please specify a reason to account for the remaining time.
                                </p>
                            </div>
                        </div>
                        
                        <div class="space-y-2">
                             <Label class="text-amber-900 dark:text-amber-400">Reason for Early Exit <span class="text-red-500">*</span></Label>
                             <Select :model-value="endShiftForm.early_exit_reason_id" @update:model-value="(val) => endShiftForm.early_exit_reason_id = String(val || '')">
                                <SelectTrigger class="w-full bg-white dark:bg-slate-900">
                                    <SelectValue placeholder="Select Downtime Reason" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="code in reasonCodes" :key="code.id" :value="String(code.id)">
                                        {{ code.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="flex flex-col md:grid md:grid-cols-4 items-start md:items-center gap-2 md:gap-4">
                        <Label class="md:text-right">Notes</Label>
                        <Input v-model="endShiftForm.notes" class="col-span-3 w-full" placeholder="Optional notes" />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showEndShiftModal = false">Cancel</Button>
                        <Button v-if="canManageShift" variant="destructive" size="default" class="h-10 px-4" @click="handleEndShift">
                            <StopCircle class="mr-2 h-4 w-4" /> End Shift
                        </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ============ LOG DOWNTIME MODAL (Multi-Entry) ============ -->
        <Dialog v-model:open="showDowntimeModal">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Log Downtime Event</DialogTitle>
                    <DialogDescription>
                        Record stoppages for the current shift. You can add multiple events.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4 py-4">
                    <!-- Pending Entries List -->
                    <div v-if="downtimeEntries.length > 0" class="border rounded-md p-3 bg-muted/20">
                        <div class="flex justify-between items-center mb-2">
                            <h4 class="text-xs font-semibold uppercase text-muted-foreground">Pending Entries ({{ downtimeEntries.length }})</h4>
                            <Button variant="ghost" size="sm" class="h-6 text-xs" @click="downtimeEntries = []">Clear All</Button>
                        </div>
                        <div class="space-y-2 max-h-[200px] overflow-y-auto pr-2">
                            <div v-for="(entry, index) in downtimeEntries" :key="index" class="flex justify-between items-center text-sm bg-background p-2 rounded shadow-sm border">
                                <div>
                                    <div class="font-medium flex items-center gap-2">
                                        <Badge variant="outline">{{ reasonCodes?.find((r: any) => String(r.id) === entry.reason_code_id)?.name || 'Unknown' }}</Badge>
                                        <span v-if="entry.comment" class="text-xs text-muted-foreground truncate max-w-[150px]">{{ entry.comment }}</span>
                                    </div>
                                    <div class="text-xs text-muted-foreground mt-1">
                                        {{ new Date(entry.start_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }} - 
                                        {{ new Date(entry.end_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }}
                                        <span class="ml-1 text-red-500 font-mono">({{ Math.round((new Date(entry.end_time).getTime() - new Date(entry.start_time).getTime()) / 60000) }}m)</span>
                                    </div>
                                </div>
                                <Button variant="ghost" size="sm" @click="removeDowntimeEntry(index)" class="h-8 w-8 p-0 text-red-500 hover:text-red-700 hover:bg-red-50">
                                    <X class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Form -->
                    <div class="space-y-4 border p-4 rounded-md bg-slate-50 dark:bg-slate-900/50">
                        <div class="grid gap-2">
                            <Label>Reason Code *</Label>
                            <Select :model-value="downtimeForm.reason_code_id" @update:model-value="(val: any) => downtimeForm.reason_code_id = String(val || '')">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Select Reason..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="reason in reasonCodes" :key="reason.id" :value="String(reason.id)">
                                        {{ reason.name }} {{ reason.category ? `(${reason.category})` : '' }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label>Start Time</Label>
                                <Input type="datetime-local" v-model="downtimeForm.start_time" />
                            </div>
                            <div class="grid gap-2">
                                <Label>End Time</Label>
                                <Input type="datetime-local" v-model="downtimeForm.end_time" />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label>Comment (Optional)</Label>
                            <Input v-model="downtimeForm.comment" placeholder="Any additional details..." @keydown.enter="addDowntimeEntry" />
                        </div>
                        
                        <Button variant="secondary" size="sm" class="w-full" @click="addDowntimeEntry">
                            <Plus class="h-4 w-4 mr-2" />
                            Add To List
                        </Button>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showDowntimeModal = false">Cancel</Button>
                    <Button @click="handleLogDowntime" :disabled="isSubmittingDowntime" class="bg-amber-500 hover:bg-amber-600">
                        <RefreshCw v-if="isSubmittingDowntime" class="mr-2 h-4 w-4 animate-spin" />
                        <PauseCircle v-else class="mr-2 h-4 w-4" />
                        {{ downtimeEntries.length > 0 ? `Submit ${downtimeEntries.length + (downtimeForm.reason_code_id ? 1 : 0)} Events` : 'Submit Log' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ============ CHANGE PRODUCT MODAL ============ -->
        <Dialog :open="showChangeProductModal" @update:open="(val) => showChangeProductModal = val">
            <DialogContent class="sm:max-w-[500px] flex flex-col max-h-[90vh]">
                <DialogHeader>
                    <DialogTitle>Change Product</DialogTitle>
                    <DialogDescription>
                        Switch to a different product during the active shift. This changeover will be tracked.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4 py-4 flex-1 overflow-y-auto min-h-0">
                    <!-- Current Product and Batch -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Current Product</Label>
                            <div class="p-3 bg-muted rounded-md text-sm font-medium">
                                {{ activeShift?.productName || 'Unknown' }}
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label>Current Batch Number</Label>
                            <div class="p-3 bg-muted rounded-md text-sm font-medium font-mono">
                                {{ activeShift?.batchNumber || 'N/A' }}
                            </div>
                        </div>
                    </div>

                    <!-- New Product -->
                    <div class="grid gap-2">
                        <Label>New Product *</Label>
                        <Select :model-value="changeProductForm.to_product_id" @update:model-value="(val: any) => changeProductForm.to_product_id = String(val || '')">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select Product" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="product in products" :key="product.id" :value="String(product.id)">
                                    {{ product.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- New Batch Number -->
                    <div class="grid gap-2">
                        <Label>New Batch Number (Optional)</Label>
                        <Input
                            v-model="changeProductForm.batch_number"
                            placeholder="Enter new batch number..."
                            class="font-mono"
                        />
                    </div>

                    <!-- Notes -->
                    <div class="grid gap-2">
                        <textarea
                            v-model="changeProductForm.notes"
                            placeholder="Reason for product change..."
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm resize-none"
                        ></textarea>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showChangeProductModal = false">Cancel</Button>
                    <Button @click="handleChangeProduct" :disabled="!changeProductForm.to_product_id || isChangingProduct" class="bg-blue-500 hover:bg-blue-600">
                        <RefreshCw v-if="isChangingProduct" class="mr-2 h-4 w-4 animate-spin" />
                        <Package v-else class="mr-2 h-4 w-4" /> Change Product
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ============ LOG MATERIAL LOSS MODAL (Multi-Entry) ============ -->
        <MaterialLossQuickEntry
            :open="showMaterialLossModal"
            @update:open="(val) => showMaterialLossModal = val"
            :shift-id="activeShift?.id ?? null"
            :machine-id="selectedMachineId ?? 0"
            :product="activeProduct"
            :products="materialLossProducts"
            :categories="materialLossCategories"
            @success="onMaterialLossSuccess"
        />
    </OperatorLayout>
</template>
