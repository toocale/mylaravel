<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, watch, computed, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Factory, GitCommit, Monitor, Plus, Pencil, Trash2, Package, Clock, Eye, CheckCircle, Play, Square, User, ChevronRight, ChevronDown, ChevronUp, Settings, Settings2, Target, PackageX, Info, PauseCircle, Scale, History, History as HistoryIcon, Activity, X, AlertTriangle, Power, Calendar } from 'lucide-vue-next';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTerminology } from '@/composables/useTerminology';
import OeeDashboard from '@/components/OeeDashboard.vue';
import ShiftReport from '@/components/oee/ShiftReport.vue';
import ShiftCreateDialog from '@/components/oee/ShiftCreateDialog.vue';
import MaterialLossQuickEntry from '@/components/MaterialLossQuickEntry.vue';
import MachineHealth from '@/components/maintenance/MachineHealth.vue';
import OceanLayout from '@/components/layouts/OceanLayout.vue';
import IndustrialLayout from '@/components/layouts/IndustrialLayout.vue';
import MinimalLayout from '@/components/layouts/MinimalLayout.vue';
import ConfigurationOceanView from '@/components/configuration/ConfigurationOceanView.vue';
import ConfigurationMinimalView from '@/components/configuration/ConfigurationMinimalView.vue';
import OceanDashboard from '@/components/dashboards/OceanDashboard.vue';

const { isOcean, isIndustrial, isMinimal } = useTheme();
const { plant: plantTerm, line: lineTerm, machine: machineTerm, plants: plantsTerm, lines: linesTerm, machines: machinesTerm } = useTerminology();

const getAssetLabel = (type: string) => {
    const t = type.toUpperCase();
    if (t === 'PLANT') return plantTerm.value;
    if (t === 'LINE') return lineTerm.value;
    if (t === 'MACHINE') return machineTerm.value;
    return type;
};

// Props
const props = defineProps<{
    organization: any;
    plants: any[];
    products: any[];
    reasonCodes: any[];
    materialLossCategories?: any[];
    downtimeTypes?: any[];
    lossTypes?: any[];
    userPermissions?: {
        managedPlantIds: number[];
    };
    targets?: any[];
    shifts?: any[];
    organizations?: any[];
}>();

const canManage = (plantId: number) => {
    if (!props.userPermissions || !props.userPermissions.managedPlantIds) return true; // Default to true if not restricted (backwards compat)
    return props.userPermissions.managedPlantIds.includes(plantId);
};

const permissions = computed(() => (page.props.auth as any).permissions || []);
const can = (permission: string) => {
    return permissions.value.includes(permission) || (page.props.auth as any).isAdmin;
};

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Assets', href: '/admin/configuration' },
];

const selectedPlantId = ref<number | null>(null);
const selectedLineId = ref<number | null>(null);

// Dashboard Context State
const selectedContext = ref<{
    plantId: number | null;
    lineId: number | null;
    machineId: number | null;
}>({
    plantId: null,
    lineId: null,
    machineId: null,
});

// Industrial View State
const isDashboardCollapsed = ref(typeof window !== 'undefined' && window.innerWidth < 1024);

// Ref to ShiftReport component for refreshing
const shiftReportRef = ref<InstanceType<typeof ShiftReport> | null>(null);

const isManualShiftDialogOpen = ref(false);

// Material Loss Dialog State
const isMaterialLossDialogOpen = ref(false);
const materialLossProducts = ref<any[]>([]);  // Products available for loss logging

const openMaterialLossDialog = async () => {
    materialLossProducts.value = [];
    const machineId = selectedContext.value.machineId;
    
    if (machineId) {
        // Fetch product runs (same as End Shift dialog)
        try {
            const response = await fetch(`/admin/production-shifts/${machineId}/product-runs`);
            const data = await response.json();
            
            if (data.success && data.product_runs && data.product_runs.length > 0) {
                // Extract unique products from runs
                const productMap = new Map<number, any>();
                for (const run of data.product_runs) {
                    if (run.product_id && !productMap.has(run.product_id)) {
                        // Find full product details from props.products
                        const fullProduct = props.products.find((p: any) => p.id === run.product_id);
                        if (fullProduct) {
                            productMap.set(run.product_id, fullProduct);
                        } else {
                            // Fallback to basic info from run
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
        
        // If no products from runs, use current active shift product
        if (materialLossProducts.value.length === 0) {
            const shift = activeShifts.value.get(machineId);
            if (shift?.product) {
                materialLossProducts.value = [shift.product];
            }
        }
    }
    
    isMaterialLossDialogOpen.value = true;
};

// Users for operator selection
const users = ref<any[]>([]);
const fetchUsers = async () => {
    try {
        const response = await fetch('/admin/api/users');
        const data = await response.json();
        users.value = data.users || [];
    } catch (e) {
        console.error('Failed to fetch users:', e);
    }
};

// Check if current user is admin
const page = usePage();
const isAdmin = computed(() => {
    const user = (page.props as any).auth?.user;
    return user?.role === 'admin';
});

// Organization rename dialog
const isOrgDialogOpen = ref(false);
const orgForm = useForm({
    name: '',
    description: '',
});

const editingOrgId = ref<number | null>(null);

const openOrgDialog = (org: any = null) => {
    // Default to strict prop if no arg, but preferably we always pass arg now
    const targetOrg = org || props.organization;
    editingOrgId.value = targetOrg.id;
    orgForm.name = targetOrg.name;
    orgForm.description = targetOrg.description || '';
    isOrgDialogOpen.value = true;
};

const submitOrgForm = () => {
    if (!editingOrgId.value) return;
    orgForm.put(`/admin/organization/${editingOrgId.value}`, {
        onSuccess: () => {
            isOrgDialogOpen.value = false;
        },
    });
};


// Collapsible Tree Logic
const isCollapsible = ref(false);
const expandedNodes = ref(new Set<string>());

// Auto-refresh interval reference
let autoRefreshInterval: any = null;

onMounted(() => {
    fetchUsers();
    
    // Auto-refresh active shift every 30 seconds to keep data in sync
    autoRefreshInterval = setInterval(() => {
        if (selectedContext.value.machineId) {
            loadActiveShift(selectedContext.value.machineId);
        }
    }, 30000);
});

const toggleNode = (key: string) => {
    if (expandedNodes.value.has(key)) {
        expandedNodes.value.delete(key);
    } else {
        expandedNodes.value.add(key);
    }
};

const isExpanded = (key: string) => {
    return !isCollapsible.value || expandedNodes.value.has(key);
};

// Modified Select Context to handle expansion
const selectContext = (plantId: number | null, lineId: number | null, machineId: number | null) => {
    selectedContext.value = { plantId, lineId, machineId };
    
    // Auto-expand on click if in collapsible mode
    if (isCollapsible.value) {
        if (plantId && !lineId) toggleNode(`plant_${plantId}`);
        if (plantId && lineId && !machineId) toggleNode(`line_${lineId}`);
    }
};

const activeTab = ref('dashboard');

// Update active tab when context changes
watch(() => selectedContext.value, (newCtx, oldCtx) => {
    // Only reset tab if context level changed (not just tab clicks)
    const contextChanged = newCtx.plantId !== oldCtx?.plantId || 
                          newCtx.lineId !== oldCtx?.lineId || 
                          newCtx.machineId !== oldCtx?.machineId;
    
    if (!contextChanged) return; // Don't interfere with manual tab clicks
    
    if (newCtx.machineId) {
         if (!['dashboard', 'machine_shifts', 'machine_products', 'machine_settings', 'targets', 'products'].includes(activeTab.value)) {
             activeTab.value = 'dashboard';
         }
    } else if (newCtx.plantId || newCtx.lineId) {
         // When at plant/line level, default to dashboard
         activeTab.value = 'dashboard';
    } else {
        // When at root level (no context), use global_products for default theme
        if (!['global_products', 'dashboard'].includes(activeTab.value)) {
            activeTab.value = 'global_products';
        }
    }
}, { deep: true });

const handleContextUpdate = (ctx: any) => {
    selectedContext.value = ctx;
};

// Computed: Current Machine's Plant ID (for permission check)
const currentMachinePlantId = computed(() => {
    const mId = selectedContext.value.machineId;
    if (!mId) return null;
    
    // Find machine in props.plants structure
    for (const plant of props.plants) {
        if (plant.lines) {
            for (const line of plant.lines) {
                if (line.machines) {
                    for (const machine of line.machines) {
                        if (machine.id === mId) return plant.id;
                    }
                }
            }
        }
    }
    return null;
});

// --- ACTIVE SHIFT STATE (API-backed) ---
const currentUser = computed(() => (page.props as any).auth?.user);

interface ActiveShift {
    id: number;
    machineId: number;
    machineName: string;
    userGroup: string | null;
    startedBy: {
        id: number;
        name: string;
        email: string;
        groups: string[];
    };
    startedAt: Date;
    productId?: number | null;
    productName?: string;
    shiftName?: string;
    batchNumber?: string;
    product?: {  // Full product object for material loss conversion
        id: number;
        name: string;
        sku: string;
        unit_of_measure: string;
        finished_unit: string | null;
        fill_volume: number | null;
        fill_volume_unit: string | null;
    } | null;
    scheduledEndAt: string | null;
}

const activeShifts = ref<Map<number, ActiveShift>>(new Map());
const shiftLoading = ref<Set<number>>(new Set());

const getActiveShift = (machineId: number | null) => {
    if (!machineId) return null;
    return activeShifts.value.get(machineId) || null;
};

const isShiftLoading = (machineId: number | null) => {
    if (!machineId) return false;
    return shiftLoading.value.has(machineId);
};

// Timer for active shift duration
const currentShiftDuration = ref('');
let shiftTimerInterval: any = null;

const startShiftTimer = () => {
    if (shiftTimerInterval) clearInterval(shiftTimerInterval);
    
    const update = () => {
        const mId = selectedContext.value.machineId;
        const shift = activeShifts.value.get(mId!);
        
        if (mId && shift) {
            const start = new Date(shift.startedAt).getTime();
            const now = new Date().getTime();
            const diff = now - start;
            
            if (diff >= 0) {
                const h = Math.floor(diff / 3600000);
                const m = Math.floor((diff % 3600000) / 60000);
                const s = Math.floor((diff % 60000) / 1000);
                currentShiftDuration.value = `${h}h ${m}m ${s}s`;
            }
        } else {
            currentShiftDuration.value = '';
        }
    };
    
    update(); // Initial run
    shiftTimerInterval = setInterval(update, 1000);
};

// Load active shift for a machine from API
const loadActiveShift = async (machineId: number) => {
    try {
        const response = await fetch(`/admin/production-shifts/${machineId}`);
        const data = await response.json();
        
        if (data.active_shift) {
            const machine = props.plants
                .flatMap((p: any) => p.lines || [])
                .flatMap((l: any) => l.machines || [])
                .find((m: any) => m.id === machineId);
                
            const shift: ActiveShift = {
                id: data.active_shift.id,
                machineId: data.active_shift.machine_id,
                machineName: machine?.name || `Machine ${machineId}`,
                userGroup: data.active_shift.user_group,
                startedBy: data.active_shift.started_by,
                startedAt: new Date(data.active_shift.started_at),
                productId: data.active_shift.product_id,
                shiftName: data.active_shift.shift_name,
                productName: data.active_shift.product_name,
                batchNumber: data.active_shift.batch_number,
                product: data.active_shift.product || null,
                scheduledEndAt: data.active_shift.scheduled_end_at || null
            };
            activeShifts.value.set(machineId, shift);
            startShiftTimer();
            
            // Fetch activity feed for this shift
            fetchShiftActivity(data.active_shift.id);
        } else {
            activeShifts.value.delete(machineId);
            currentShiftDuration.value = '';
            shiftActivity.value = [];
        }
    } catch (error) {
        console.error('Failed to load active shift:', error);
    }
};

// ============ SHIFT ACTIVITY FEED ============
interface ShiftActivityItem {
    id: string;
    type: 'downtime' | 'loss' | 'changeover' | 'production';
    title: string;
    description: string;
    timestamp: Date;
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
            }));
        }
    } catch (e) {
        console.error('Failed to fetch shift activity:', e);
    }
};

const addActivityItem = (type: ShiftActivityItem['type'], title: string, description: string) => {
    const item: ShiftActivityItem = {
        id: `${type}-${Date.now()}`,
        type,
        title,
        description,
        timestamp: new Date(),
    };
    shiftActivity.value.unshift(item);
};

import { onUnmounted } from 'vue';
onUnmounted(() => {
    if (shiftTimerInterval) clearInterval(shiftTimerInterval);
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
});

// Start Shift with Product Selection
const isStartShiftDialogOpen = ref(false);
const startShiftForm = useForm({
    machine_id: null as number | null,
    product_id: '',
    shift_id: '',
    operator_user_id: '' as string | number, // Required: Operator who's starting the shift
    batch_number: '',
});

const openStartShiftDialog = (machineId: number) => {
    startShiftForm.reset();
    startShiftForm.machine_id = machineId;
    // Reset to empty (user must select an operator)
    startShiftForm.operator_user_id = '';
    isStartShiftDialogOpen.value = true;
};

const machineProducts = computed(() => {
    const mId = selectedContext.value.machineId;
    if (!mId) return [];
    
    const machine = props.plants.flatMap((p: any) => p.lines).flatMap((l: any) => l.machines).find((m: any) => m.id === mId);
    
    if (machine && machine.machine_product_configs) {
        return machine.machine_product_configs.map((mpc: any) => ({
             id: mpc.product.id,
             name: mpc.product.name,
             sku: mpc.product.sku,
             ideal_rate: mpc.ideal_rate
        }));
    }
    return [];
});

const machineShifts = computed(() => {
    const pId = selectedContext.value.plantId;
    if (!pId) return [];
    
    const plant = props.plants.find((p: any) => p.id === pId);
    
    if (plant && plant.shifts) {
        return plant.shifts.map((s: any) => ({
             id: s.id,
             name: s.name,
             type: s.type,
             start_time: s.start_time,
             end_time: s.end_time
        }));
    }
    return [];
});

import { useToast } from '@/composables/useToast';
const { addToast } = useToast();

const confirmStartShift = async () => {
    if (!startShiftForm.machine_id || !startShiftForm.product_id) return;
    
    // Validate operator selection
    if (!startShiftForm.operator_user_id) {
        addToast({ title: 'Validation Error', message: 'Please select an operator to start the shift.', type: 'error' });
        return;
    }
    
    const machineId = startShiftForm.machine_id;
    shiftLoading.value.add(machineId);

    try {
         const response = await fetch(`/admin/production-shifts/${machineId}/start`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                product_id: startShiftForm.product_id,
                shift_id: startShiftForm.shift_id,
                operator_user_id: startShiftForm.operator_user_id, // Required: operator starting the shift
                batch_number: startShiftForm.batch_number,
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            const machine = props.plants
                .flatMap((p: any) => p.lines || [])
                .flatMap((l: any) => l.machines || [])
                .find((m: any) => m.id === machineId);

            activeShifts.value.set(machineId, {
                id: data.active_shift.id,
                machineId: data.active_shift.machine_id,
                machineName: machine?.name || `Machine ${machineId}`,
                userGroup: data.active_shift.user_group,
                startedBy: data.active_shift.started_by,
                startedAt: new Date(data.active_shift.started_at),
                shiftName: data.active_shift.shift_name,
                productName: data.active_shift.product_name,
                productId: data.active_shift.product_id,
                product: data.active_shift.product, // Ensure full product object is available immediately
                batchNumber: data.active_shift.batch_number,
                scheduledEndAt: data.active_shift.scheduled_end_at || null
            });
            isStartShiftDialogOpen.value = false;
            addToast({ title: 'Shift Started', message: 'Production shift has been started successfully.', type: 'success' });
        } else if (data.error) {
            addToast({ title: 'Error', message: data.error, type: 'error' });
        }
    } catch (e) {
        console.error(e);
        addToast({ title: 'Error', message: 'Failed to start shift.', type: 'error' });
    } finally {
        shiftLoading.value.delete(machineId);
    }
};


// End Shift Form
const isEndShiftDialogOpen = ref(false);
const endShiftLoading = ref(false);
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

const endShiftForm = useForm({
    machine_id: null as number | null,
    good_count: null as number | null,
    reject_count: null as number | null,
    comment: '',
    early_exit_reason_id: ''
});


// Computed property to check if endShiftForm is valid
const isEndShiftFormValid = computed(() => {
    if (hasChangeovers.value) {
        // All products must have valid counts
        return productRuns.value.every(p => 
            p.good_count != null && p.reject_count != null &&
            p.good_count >= 0 && p.reject_count >= 0
        );
    }
    const goodCount = endShiftForm.good_count;
    const rejectCount = endShiftForm.reject_count;
    // Check that both are valid numbers (not null or undefined)
    return goodCount != null && rejectCount != null && 
           goodCount >= 0 && rejectCount >= 0;
});

const openEndShiftDialog = async (machineId: number) => {
    endShiftForm.reset();
    endShiftForm.machine_id = machineId;
    productRuns.value = [];
    hasChangeovers.value = false;
    isEndShiftDialogOpen.value = true;
    
    // Fetch product runs to check for changeovers
    try {
        const response = await fetch(`/admin/production-shifts/${machineId}/product-runs`);
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
};

const isEarlyExit = ref(false);
const earlyExitDuration = ref(0);

watch(isEndShiftDialogOpen, (isOpen) => {
    if (isOpen) {
        const shift = getActiveShift(selectedContext.value.machineId);
        if (shift && shift.scheduledEndAt) {
            const scheduledEnd = new Date(shift.scheduledEndAt).getTime();
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
    } else {
        isEarlyExit.value = false;
    }
});

const confirmEndShift = async () => {
    if (!endShiftForm.machine_id) return;
    const machineId = selectedContext.value.machineId;
    if (!machineId) return;

    if (isEarlyExit.value && !endShiftForm.early_exit_reason_id) {
        addToast({ title: 'Validation Required', message: 'Please select a reason for ending the shift early.', type: 'error' });
        return;
    }
    
    endShiftLoading.value = true;
    shiftLoading.value.add(machineId);
    
    try {
        const response = await fetch(`/admin/production-shifts/${machineId}/end`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                good_count: endShiftForm.good_count,
                reject_count: endShiftForm.reject_count,
                comment: endShiftForm.comment,
                product_counts: hasChangeovers.value ? productRuns.value : undefined,
                early_exit_reason_id: isEarlyExit.value && endShiftForm.early_exit_reason_id ? parseInt(endShiftForm.early_exit_reason_id) : undefined
            }),
        });
        
        const data = await response.json();
        
        if (data.success) {
            activeShifts.value.delete(machineId);
            isEndShiftDialogOpen.value = false;
            clearInterval(shiftTimerInterval);
            currentShiftDuration.value = '';
            
            // Refresh shifts in ShiftReport component
            shiftReportRef.value?.refresh?.();
            
            addToast({ title: 'Shift Ended', message: 'Production shift ended successfully.', type: 'success' });
        } else {
            addToast({ title: 'Error', message: data.error || 'Failed to end shift.', type: 'error' });
        }
    } catch (error) {
        addToast({ title: 'Error', message: 'Failed to end production shift.', type: 'error' });
    } finally {
        endShiftLoading.value = false;
        shiftLoading.value.delete(machineId);
    }
};

// Change Product Dialog
const isChangeProductDialogOpen = ref(false);
const changeProductForm = useForm({
    to_product_id: '',
    batch_number: '',
    notes: '',
});
const changeProductLoading = ref(false);

const confirmChangeProduct = async () => {
    const shift = getActiveShift(selectedContext.value.machineId);
    if (!shift || !changeProductForm.to_product_id) return;
    
    changeProductLoading.value = true;
    
    try {
        const response = await fetch(`/admin/production-shifts/shift/${shift.id}/changeover`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                to_product_id: changeProductForm.to_product_id,
                batch_number: changeProductForm.batch_number,
                notes: changeProductForm.notes,
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update the active shift's product and batch number
            if (selectedContext.value.machineId) {
                const product = machineProducts.value.find((p: any) => p.id == changeProductForm.to_product_id);
                const currentShift = activeShifts.value.get(selectedContext.value.machineId);
                if (currentShift && product) {
                    currentShift.productId = product.id;
                    currentShift.productName = product.name;
                    // Update batch number if provided
                    if (data.changeover.batch_number) {
                        currentShift.batchNumber = data.changeover.batch_number;
                    }
                }
            }
            
            isChangeProductDialogOpen.value = false;
            changeProductForm.reset();
            
            addToast({ title: 'Product Changed', message: `Changed to ${data.changeover.to_product.name}`, type: 'success' });
            
            // Refresh activity feed
            if (shift) {
                fetchShiftActivity(shift.id);
            }
        } else {
            addToast({ title: 'Error', message: data.error || 'Failed to change product.', type: 'error' });
        }
    } catch (error) {
        console.error('Change product error:', error);
        addToast({ title: 'Error', message: 'Failed to change product.', type: 'error' });
    } finally {
        changeProductLoading.value = false;
    }
};


// Load active shift when machine context changes
watch(() => selectedContext.value.machineId, (newMachineId) => {
    if (newMachineId && !activeShifts.value.has(newMachineId)) {
        loadActiveShift(newMachineId);
    }
}, { immediate: true });

const formatTime = (date: Date) => {
    return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

const formatDate = (date: Date) => {
    return date.toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
};

// --- FORMS ---

// Plant Form
const isPlantDialogOpen = ref(false);
const plantForm = useForm({
    id: null,
    organization_id: props.organization.id,
    name: '',
    location: '',
});
const openPlantDialog = (plantOrConfig: any = null) => {
    plantForm.reset();
    plantForm.organization_id = props.organization.id; // Default
    
    if (plantOrConfig) {
        // Check if it's a configuration object (has only organization_id) vs a full plant
        if (plantOrConfig.organization_id && !plantOrConfig.name) {
            // It's a config object to set the parent org
            plantForm.organization_id = plantOrConfig.organization_id;
        } else {
            // It's a plant object to edit
            plantForm.id = plantOrConfig.id;
            plantForm.name = plantOrConfig.name;
            plantForm.location = plantOrConfig.location;
            plantForm.organization_id = plantOrConfig.organization_id || props.organization.id;
        }
    }
    isPlantDialogOpen.value = true;
};
const submitPlant = () => {
    if (plantForm.id) plantForm.put(`/admin/plants/${plantForm.id}`, { onSuccess: () => isPlantDialogOpen.value = false });
    else plantForm.post('/admin/plants', { onSuccess: () => isPlantDialogOpen.value = false });
};
const deletePlant = (id: number) => {
    openConfirmDialog(
        `Delete ${plantTerm.value}?`,
        `Are you sure you want to delete this ${plantTerm.value.toLowerCase()}? This action cannot be undone and will delete all associated ${linesTerm.value.toLowerCase()}, ${machinesTerm.value.toLowerCase()}, and data.`,
        () => useForm({}).delete(`/admin/plants/${id}`)
    );
};

// Line Form
const isLineDialogOpen = ref(false);
const lineForm = useForm({
    id: null,
    plant_id: null,
    name: '',
});
const openLineDialog = (plantId: any, line: any = null) => {
    lineForm.reset();
    lineForm.plant_id = plantId;
    if (line) {
        lineForm.id = line.id;
        lineForm.name = line.name;
    }
    isLineDialogOpen.value = true;
};
const submitLine = () => {
    if (lineForm.id) lineForm.put(`/admin/lines/${lineForm.id}`, { onSuccess: () => isLineDialogOpen.value = false });
    else lineForm.post('/admin/lines', { onSuccess: () => isLineDialogOpen.value = false });
};
const deleteLine = (id: number) => {
    openConfirmDialog(
        `Delete ${lineTerm.value}?`,
        `Are you sure you want to delete this ${lineTerm.value.toLowerCase()}? All associated ${machinesTerm.value.toLowerCase()} and data will be permanently removed.`,
        () => useForm({}).delete(`/admin/lines/${id}`)
    );
};

// Machine Form
const isMachineDialogOpen = ref(false);
const machineForm = useForm({
    id: null,
    line_id: null,
    name: '',
    default_ideal_rate: 0,
    status: 'idle'
});
const openMachineDialog = (lineId: any, machine: any = null) => {
    machineForm.reset();
    machineForm.line_id = lineId;
    machineForm.default_ideal_rate = 0;
    if (machine) {
        machineForm.id = machine.id;
        machineForm.name = machine.name;
        machineForm.default_ideal_rate = machine.default_ideal_rate;
        machineForm.status = machine.status;
    }
    isMachineDialogOpen.value = true;
};
const submitMachine = () => {
    if (machineForm.id) machineForm.put(`/admin/machines/${machineForm.id}`, { onSuccess: () => isMachineDialogOpen.value = false });
    else machineForm.post('/admin/machines', { onSuccess: () => isMachineDialogOpen.value = false });
};
const deleteMachine = (id: number) => {
    openConfirmDialog(
        `Delete ${machineTerm.value}?`,
        `Are you sure you want to delete this ${machineTerm.value.toLowerCase()}? All historical data and logs will be lost.`,
        () => useForm({}).delete(`/admin/machines/${id}`)
    );
};

// Product Form
const isProductDialogOpen = ref(false);
const productForm = useForm({
    id: null,
    name: '',
    sku: '',
    unit_of_measure: '',
    reference_weight: null as number | null,
    assign_to_machine_id: null as number | null,
    finished_unit: null as string | null,
    fill_volume: null as number | null,
    fill_volume_unit: null as string | null,
});
const openProductDialog = (product: any = null, machineId: number | null = null) => {
    productForm.reset();
    productForm.assign_to_machine_id = machineId;
    if (product) {
        productForm.id = product.id;
        productForm.name = product.name;
        productForm.sku = product.sku;
        productForm.unit_of_measure = product.unit_of_measure || '';
        productForm.reference_weight = product.reference_weight;
        productForm.finished_unit = product.finished_unit || null;
        productForm.fill_volume = product.fill_volume || null;
        productForm.fill_volume_unit = product.fill_volume_unit || null;
    }
    isProductDialogOpen.value = true;
};
const submitProduct = () => {
    if (productForm.id) productForm.put(`/admin/products/${productForm.id}`, { onSuccess: () => isProductDialogOpen.value = false });
    else productForm.post('/admin/products', { onSuccess: () => isProductDialogOpen.value = false });
};
const deleteProduct = (id: number) => {
    openConfirmDialog(
        'Delete Product?',
        'Are you sure you want to delete this global product? It will be removed from all assigned machines.',
        () => useForm({}).delete(`/admin/products/${id}`)
    );
};

// Shift Form (New)
const isShiftDialogOpen = ref(false);
const shiftForm = useForm({
    id: null,
    plant_id: null,
    name: '',
    type: 'day',
    start_time: '',
    end_time: ''
});

const openShiftDialog = (plantId: any, shift: any = null) => {
    shiftForm.reset();
    shiftForm.plant_id = plantId;
    
    if (shift) {
        shiftForm.id = shift.id;
        shiftForm.name = shift.name;
        shiftForm.type = shift.type || 'day';
        // Ensure time format HH:MM
        shiftForm.start_time = shift.start_time ? shift.start_time.substring(0, 5) : '';
        shiftForm.end_time = shift.end_time ? shift.end_time.substring(0, 5) : '';
    }
    isShiftDialogOpen.value = true;
};

const submitShift = () => {
    if (shiftForm.id) shiftForm.put(`/admin/shifts/${shiftForm.id}`, { onSuccess: () => isShiftDialogOpen.value = false });
    else shiftForm.post('/admin/shifts', { onSuccess: () => isShiftDialogOpen.value = false });
};

const deleteShift = (id: number) => {
    openConfirmDialog(
        'Delete Shift?',
        'Are you sure you want to delete this shift schedule?',
        () => useForm({}).delete(`/admin/shifts/${id}`)
    );
};

// Machine Shift Assignment
const isAttachShiftDialogOpen = ref(false);
const attachShiftForm = useForm({
    machine_id: null,
    shift_id: ''
});
const openAttachShiftDialog = (machineId: any) => {
    attachShiftForm.reset();
    attachShiftForm.machine_id = machineId;
    isAttachShiftDialogOpen.value = true;
};
const submitAttachShift = () => {
   if (!attachShiftForm.machine_id) return;
   attachShiftForm.post(`/admin/machines/${attachShiftForm.machine_id}/shifts`, {
       onSuccess: () => isAttachShiftDialogOpen.value = false
   });
};
const detachShift = (machineId: number, shiftId: number) => {
    openConfirmDialog(
        'Remove Assignment?',
        'Are you sure you want to remove this shift assignment from the machine?',
        () => useForm({}).delete(`/admin/machines/${machineId}/shifts/${shiftId}`)
    );
};

// Machine Product Assignment
const isAssignProductDialogOpen = ref(false);
const assignProductForm = useForm({
    machine_id: null,
    product_id: '',
    ideal_rate: 0
});
const openAssignProductDialog = (machineId: any) => {
    assignProductForm.reset();
    assignProductForm.machine_id = machineId;
    // Set default cycle time from machine if possible
    const machine = props.plants.flatMap((p: any) => p.lines).flatMap((l: any) => l.machines).find((m: any) => m.id === machineId);
    if (machine) {
        assignProductForm.ideal_rate = machine.default_ideal_rate || 0;
    }
    isAssignProductDialogOpen.value = true;
};
const submitAssignProduct = () => {
    if (!assignProductForm.machine_id) return;
    assignProductForm.post(`/admin/machines/${assignProductForm.machine_id}/products`, {
        onSuccess: () => isAssignProductDialogOpen.value = false
    });
};
const detachProduct = (machineId: number, productId: number) => {
    openConfirmDialog(
        'Remove Assignment?',
        'Are you sure you want to remove this product configuration from the machine?',
        () => useForm({}).delete(`/admin/machines/${machineId}/products/${productId}`)
    );
};

// Machine Reason Assignment
const isAssignReasonDialogOpen = ref(false);
const assignReasonForm = useForm({
    machine_id: null,
    reason_code_id: ''
});
const openAssignReasonDialog = (machineId: any) => {
    assignReasonForm.reset();
    assignReasonForm.machine_id = machineId;
    isAssignReasonDialogOpen.value = true;
};
const submitAssignReason = () => {
    if (!assignReasonForm.machine_id) return;
    assignReasonForm.post(`/admin/machines/${assignReasonForm.machine_id}/reasons`, {
        onSuccess: () => isAssignReasonDialogOpen.value = false
    });
};
const detachReason = (machineId: number, reasonCodeId: number) => {
    openConfirmDialog(
        'Remove Assignment?',
        'Are you sure you want to remove this reason code from the machine?',
        () => useForm({}).delete(`/admin/machines/${machineId}/reasons/${reasonCodeId}`)
    );
};

// Reason Code Form
const findDowntimeTypeIdByCode = (code: string) => {
    if (!props.downtimeTypes) return null;
    const type = props.downtimeTypes.find(t => t.code === code);
    return type ? type.id : null;
};

// Reason Code Form
const isReasonCodeDialogOpen = ref(false);
const reasonCodeForm = useForm({
    id: null,
    organization_id: props.organization.id,
    code: '',
    description: '',
    downtime_type_id: null as number | null,
    category: 'unplanned',
    assign_to_machine_id: null as number | null, // Deprecated: Always global now
});
const openReasonCodeDialog = (rc: any = null, machineId: number | null = null) => {
    reasonCodeForm.reset();
    reasonCodeForm.organization_id = props.organization.id;
    // We ignore machineId now to enforce global reason codes
    reasonCodeForm.assign_to_machine_id = null;
    if (rc) {
        reasonCodeForm.id = rc.id;
        reasonCodeForm.code = rc.code;
        reasonCodeForm.description = rc.description;
        reasonCodeForm.downtime_type_id = rc.downtime_type_id || (rc.category ? findDowntimeTypeIdByCode(rc.category) : null);
        reasonCodeForm.category = rc.category || 'unplanned';
    }
    isReasonCodeDialogOpen.value = true;
};
const submitReasonCode = () => {
    if (reasonCodeForm.id) reasonCodeForm.put(`/admin/reason-codes/${reasonCodeForm.id}`, { onSuccess: () => isReasonCodeDialogOpen.value = false });
    else reasonCodeForm.post('/admin/reason-codes', { onSuccess: () => isReasonCodeDialogOpen.value = false });
};
const deleteReasonCode = (id: number) => {
    openConfirmDialog(
        'Delete Reason Code?',
        'Are you sure you want to delete this global reason code? This will affect all machines using it.',
        () => useForm({}).delete(`/admin/reason-codes/${id}`, { onSuccess: () => isReasonCodeDialogOpen.value = false })
    );
};

// Confirmation Dialog State
const isConfirmDialogOpen = ref(false);
const confirmOptions = ref({
    title: '',
    message: '',
    action: () => {},
    processing: false
});

const openConfirmDialog = (title: string, message: string, action: Function) => {
    confirmOptions.value = {
        title,
        message,
        action: async () => {
            confirmOptions.value.processing = true;
            try {
                await action();
            } finally {
                confirmOptions.value.processing = false;
                isConfirmDialogOpen.value = false;
            }
        },
        processing: false
    };
    isConfirmDialogOpen.value = true;
};

// Log Downtime Form
const isDowntimeDialogOpen = ref(false);
const downtimeEntries = ref<any[]>([]);
const downtimeForm = useForm({
    reason_code_id: '',
    start_time: '',
    end_time: '',
    comment: ''
});

const openDowntimeDialog = () => {
    downtimeForm.reset();
    downtimeEntries.value = [];
    // Set default times to now
    const now = new Date();
    const fiveMinutesAgo = new Date(now.getTime() - 5 * 60000);
    downtimeForm.start_time = fiveMinutesAgo.toISOString().slice(0, 16);
    downtimeForm.end_time = now.toISOString().slice(0, 16);
    isDowntimeDialogOpen.value = true;
};

const addDowntimeEntry = () => {
    // Validate Current Form
    if (!downtimeForm.reason_code_id || !downtimeForm.start_time || !downtimeForm.end_time) {
        addToast({ title: 'Validation Error', message: 'Please complete the form to add an entry.', type: 'error' });
        return;
    }
    const start = new Date(downtimeForm.start_time);
    const end = new Date(downtimeForm.end_time);
    if (end <= start) {
        addToast({ title: 'Validation Error', message: 'End time must be after start time.', type: 'error' });
        return;
    }
    
    // Add to list
    downtimeEntries.value.push({
        reason_code_id: downtimeForm.reason_code_id,
        start_time: downtimeForm.start_time,
        end_time: downtimeForm.end_time,
        comment: downtimeForm.comment
    });
    
    // Reset Form for next entry (default next start = previous end)
    downtimeForm.start_time = downtimeForm.end_time;
    const newEnd = new Date(new Date(downtimeForm.end_time).getTime() + 5 * 60000); // +5 min default
    downtimeForm.end_time = newEnd.toISOString().slice(0, 16);
    downtimeForm.reason_code_id = '';
    downtimeForm.comment = '';
    
    addToast({ title: 'Added', message: 'Entry added to list.', type: 'success' });
};

const removeDowntimeEntry = (index: number) => {
    downtimeEntries.value.splice(index, 1);
};


const submitDowntime = async () => {
    if (!selectedContext.value.machineId) return;
    
    const hasEntries = downtimeEntries.value.length > 0;
    const hasValidForm = downtimeForm.reason_code_id && downtimeForm.start_time && downtimeForm.end_time;

    if (!hasEntries && !hasValidForm) {
        addToast({ title: 'Validation Error', message: 'Please add at least one downtime entry.', type: 'error' });
        return;
    }
    
    let payload: any = {};
    
    // Determine payload
    if (hasEntries) {
        const events = [...downtimeEntries.value];
        // If form is also filled, include it
        if (hasValidForm) {
            events.push({
                reason_code_id: downtimeForm.reason_code_id,
                start_time: downtimeForm.start_time,
                end_time: downtimeForm.end_time,
                comment: downtimeForm.comment
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
        // Single Entry Logic
        const start = new Date(downtimeForm.start_time);
        const end = new Date(downtimeForm.end_time);
        if (end <= start) {
            addToast({ title: 'Validation Error', message: 'End time must be after start time.', type: 'error' });
            return;
        }
        
        payload = {
            reason_code_id: downtimeForm.reason_code_id,
            start_time: start.toISOString(),
            end_time: end.toISOString(),
            comment: downtimeForm.comment
        };
    }

    try {
        const response = await fetch(`/admin/production-shifts/${selectedContext.value.machineId}/downtime`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify(payload)
        });
        
        const data = await response.json();
        
        if (data.success) {
            isDowntimeDialogOpen.value = false;
            downtimeEntries.value = [];
            addToast({ title: 'Downtime Logged', message: data.message || 'Downtime event logged successfully.', type: 'success' });
            
            // Refresh activity feed
            const currentShift = getActiveShift(selectedContext.value.machineId);
            if (currentShift) {
                fetchShiftActivity(currentShift.id);
            }
            // Refresh shift report to show new downtime
            if (shiftReportRef.value) {
                shiftReportRef.value.refresh();
            }
        } else {
            addToast({ title: 'Error', message: data.error || data.message || 'Failed to log downtime.', type: 'error' });
        }
    } catch (e: any) {
        console.error('Downtime submission error:', e);
        addToast({ title: 'Error', message: e.message || 'An error occurred while logging downtime.', type: 'error' });
    }
};

// ===== TARGET MANAGEMENT =====
const targets = ref<any[]>(props.targets || []);
watch(() => props.targets, (newTargets) => {
    if (newTargets) {
        targets.value = newTargets;
    }
}, { immediate: true });
const isTargetDialogOpen = ref(false);
const editingTarget = ref<any>(null);
const targetLevel = ref<'line' | 'machine'>('machine'); // Track which level is selected

const targetForm = useForm({
    id: null as number | null,
    line_id: null as number | null,
    machine_id: null as number | null,
    shift_id: null as number | null,
    effective_from: new Date().toISOString().split('T')[0],
    effective_to: '',
    target_oee: 85,
    target_availability: 90,
    target_performance: 95,
    target_quality: 99,
    target_units: undefined as number | undefined,
    target_good_units: undefined as number | undefined,
    notes: '',
});

// Auto-calculate OEE based on generic availability, performance, quality targets
watch(() => [targetForm.target_availability, targetForm.target_performance, targetForm.target_quality], ([a, p, q]) => {
    // Ensure all values are present
    if (a !== undefined && p !== undefined && q !== undefined) {
        // Formula: (A * P * Q) / 10000 -> Percentage
        // Example: 90 * 95 * 99 / 10000 = 84.645 -> 85
        const calculated = Math.round((Number(a) * Number(p) * Number(q)) / 10000);
        targetForm.target_oee = Math.min(100, Math.max(0, calculated));
    }
});

const openTargetDialog = (target: any = null, lineId: number | null = null, machineId: number | null = null) => {
    targetForm.reset();
    if (target) {
        editingTarget.value = target;
        targetForm.id = target.id;
        targetForm.line_id = target.line_id;
        targetForm.machine_id = target.machine_id;
        targetForm.shift_id = target.shift_id;
        targetForm.effective_from = target.effective_from;
        targetForm.effective_to = target.effective_to || '';
        targetForm.target_oee = target.target_oee || 85;
        targetForm.target_availability = target.target_availability || 90;
        targetForm.target_performance = target.target_performance || 95;
        targetForm.target_quality = target.target_quality || 99;
        targetForm.target_units = target.target_units || undefined;
        targetForm.target_good_units = target.target_good_units || undefined;
        targetForm.notes = target.notes || '';
        // Set target level based on what's defined
        targetLevel.value = target.line_id ? 'line' : 'machine';
    } else {
        // Pre-fill line or machine if provided
        targetForm.line_id = lineId;
        targetForm.machine_id = machineId;
        editingTarget.value = null;
        // Set target level based on what was pre-filled
        targetLevel.value = lineId ? 'line' : 'machine';
    }
    isTargetDialogOpen.value = true;
};

const submitTarget = () => {
    if (targetForm.id) {
        targetForm.put(`/admin/targets/${targetForm.id}`, {
            onSuccess: () => {
                isTargetDialogOpen.value = false;
            }
        });
    } else {
        targetForm.post('/admin/targets', {
            onSuccess: () => {
                isTargetDialogOpen.value = false;
            }
        });
    }
};

const deleteTarget = (id: number) => {
    openConfirmDialog(
        'Delete Target?',
        'Are you sure you want to delete this production target?',
        () => useForm({}).delete(`/admin/targets/${id}`)
    );
};

// ===== CATEGORY MANAGEMENT =====

// Loss Category Dialog
const isLossCategoryDialogOpen = ref(false);
const editingLossCategory = ref<any>(null);
const lossCategoryForm = useForm({
    id: null as number | null,
    name: '',
    code: '',
    description: '',
    loss_type_id: null as number | null,
    loss_type: 'other', // Legacy fallback
    affects_oee: false,
    requires_reason: false,
    color: '#ef4444',
    active: true,
});

const findLossTypeIdByCode = (code: string) => {
    if (!props.lossTypes) return null;
    const type = props.lossTypes.find(t => t.code === code);
    return type ? type.id : null;
};

const openLossCategoryDialog = (category: any = null) => {
    lossCategoryForm.reset();
    if (category) {
        editingLossCategory.value = category;
        lossCategoryForm.id = category.id;
        lossCategoryForm.name = category.name;
        lossCategoryForm.code = category.code;
        lossCategoryForm.description = category.description || '';
        lossCategoryForm.loss_type_id = category.loss_type_id || (category.loss_type ? findLossTypeIdByCode(category.loss_type) : null);
        lossCategoryForm.loss_type = category.loss_type || 'other';
        lossCategoryForm.affects_oee = category.affects_oee || false;
        lossCategoryForm.requires_reason = category.requires_reason || false;
        lossCategoryForm.color = category.color || '#ef4444';
        lossCategoryForm.active = category.active !== false;
    } else {
        editingLossCategory.value = null;
    }
    isLossCategoryDialogOpen.value = true;
};

const submitLossCategory = () => {
    if (lossCategoryForm.id) {
        lossCategoryForm.put(`/admin/material-loss-categories/${lossCategoryForm.id}`, {
            onSuccess: () => {
                isLossCategoryDialogOpen.value = false;
                window.location.reload();
            }
        });
    } else {
        lossCategoryForm.post('/admin/material-loss-categories', {
            onSuccess: () => {
                isLossCategoryDialogOpen.value = false;
                window.location.reload();
            }
        });
    }
};

const deleteLossCategory = (id: number) => {
    openConfirmDialog(
        'Delete Loss Category?',
        'Are you sure you want to delete this loss category? This may affect existing records.',
        () => {
            useForm({}).delete(`/admin/material-loss-categories/${id}`, {
                onSuccess: () => window.location.reload()
            });
        }
    );
};

// Downtime Type Dialog
const isDowntimeTypeDialogOpen = ref(false);
const editingDowntimeType = ref<any>(null);
const downtimeTypeForm = useForm({
    id: null as number | null,
    organization_id: props.organization?.id,
    name: '',
    code: '',
    description: '',
    color: '#6b7280',
    affects_availability: true,
    active: true,
    sort_order: 0,
});

const openDowntimeTypeDialog = (type: any = null) => {
    downtimeTypeForm.reset();
    downtimeTypeForm.organization_id = props.organization?.id;
    if (type) {
        editingDowntimeType.value = type;
        downtimeTypeForm.id = type.id;
        downtimeTypeForm.name = type.name;
        downtimeTypeForm.code = type.code;
        downtimeTypeForm.description = type.description || '';
        downtimeTypeForm.color = type.color || '#6b7280';
        downtimeTypeForm.affects_availability = type.affects_availability !== false;
        downtimeTypeForm.active = type.active !== false;
        downtimeTypeForm.sort_order = type.sort_order || 0;
    } else {
        editingDowntimeType.value = null;
    }
    isDowntimeTypeDialogOpen.value = true;
};

const submitDowntimeType = () => {
    if (downtimeTypeForm.id) {
        downtimeTypeForm.put(`/admin/downtime-types/${downtimeTypeForm.id}`, {
            onSuccess: () => {
                isDowntimeTypeDialogOpen.value = false;
                window.location.reload();
            }
        });
    } else {
        downtimeTypeForm.post('/admin/downtime-types', {
            onSuccess: () => {
                isDowntimeTypeDialogOpen.value = false;
                window.location.reload();
            }
        });
    }
};

const deleteDowntimeType = (id: number) => {
    openConfirmDialog(
        'Delete Downtime Type?',
        'Are you sure you want to delete this downtime type? Reason codes using this type will be unlinked.',
        () => {
            useForm({}).delete(`/admin/downtime-types/${id}`, {
                onSuccess: () => window.location.reload()
            });
        }
    );
};

// Loss Type Dialog
const isLossTypeDialogOpen = ref(false);
const editingLossType = ref<any>(null);
const lossTypeForm = useForm({
    id: null as number | null,
    organization_id: props.organization?.id,
    name: '',
    code: '',
    description: '',
    color: '#ef4444',
    affects_oee: false,
    active: true,
    sort_order: 0,
});

const openLossTypeDialog = (type: any = null) => {
    lossTypeForm.reset();
    lossTypeForm.organization_id = props.organization?.id;
    if (type) {
        editingLossType.value = type;
        lossTypeForm.id = type.id;
        lossTypeForm.name = type.name;
        lossTypeForm.code = type.code;
        lossTypeForm.description = type.description || '';
        lossTypeForm.color = type.color || '#ef4444';
        lossTypeForm.affects_oee = type.affects_oee || false;
        lossTypeForm.active = type.active !== false;
        lossTypeForm.sort_order = type.sort_order || 0;
    } else {
        editingLossType.value = null;
    }
    isLossTypeDialogOpen.value = true;
};

const submitLossType = () => {
    if (lossTypeForm.id) {
        lossTypeForm.put(`/admin/loss-types/${lossTypeForm.id}`, {
            onSuccess: () => {
                isLossTypeDialogOpen.value = false;
                window.location.reload();
            }
        });
    } else {
        lossTypeForm.post('/admin/loss-types', {
            onSuccess: () => {
                isLossTypeDialogOpen.value = false;
                window.location.reload();
            }
        });
    }
};

const deleteLossType = (id: number) => {
    openConfirmDialog(
        'Delete Loss Type?',
        'Are you sure you want to delete this loss type? Categories using this type will be unlinked.',
        () => {
            useForm({}).delete(`/admin/loss-types/${id}`, {
                onSuccess: () => window.location.reload()
            });
        }
    );
};

const categoryColors = [
    '#ef4444', '#f97316', '#f59e0b', '#eab308', '#84cc16',
    '#22c55e', '#10b981', '#14b8a6', '#06b6d4', '#0ea5e9',
    '#3b82f6', '#6366f1', '#8b5cf6', '#a855f7', '#d946ef',
    '#ec4899', '#f43f5e'
];

const allShifts = computed(() => {
    // If shifts are provided directly in props, use them
    if (props.shifts && props.shifts.length > 0) {
        return props.shifts;
    }
    
    // Otherwise, extract from machines (fallback for backwards compatibility)
    const shiftsSet = new Map<number, any>();
    props.plants.forEach((plant: any) => {
        plant.lines?.forEach((line: any) => {
            line.machines?.forEach((machine: any) => {
                machine.shifts?.forEach((shift: any) => {
                    shiftsSet.set(shift.id, shift);
                });
            });
        });
    });
    return Array.from(shiftsSet.values());
});

const allMachines = computed(() => {
    const machines: any[] = [];
    props.plants.forEach((plant: any) => {
        plant.lines?.forEach((line: any) => {
            line.machines?.forEach((machine: any) => {
                machines.push({
                    ...machine,
                    plant_name: plant.name,
                    line_name: line.name,
                    display_name: `${plant.name} › ${line.name} › ${machine.name}`
                });
            });
        });
    });
    return machines;
});


onMounted(() => {
    // 1. Restore state from URL
    const params = new URLSearchParams(window.location.search);
    const plantId = params.get('plantId') ? Number(params.get('plantId')) : null;
    const lineId = params.get('lineId') ? Number(params.get('lineId')) : null;
    const machineId = params.get('machineId') ? Number(params.get('machineId')) : null;
    const tab = params.get('tab');

    // Restore Context
    if (plantId || lineId || machineId) {
        selectedContext.value = { plantId, lineId, machineId };
        
        // Expand nodes if collapsible is active (or just expand them to show selection)
        if (plantId) expandedNodes.value.add(`plant_${plantId}`);
        if (lineId) expandedNodes.value.add(`line_${lineId}`);
        // If we are deep in structure, ensuring collapsible logic respects it
        if (isCollapsible.value) {
            // Logic handled by isExpanded check
        }
    }

    // Restore Tab from URL if different (prevents infinite loops)
    if (tab && activeTab.value !== tab) {
        activeTab.value = tab;
    }

    console.log('User Permissions:', props.userPermissions);
    if (props.targets) {
        targets.value = props.targets;
    }
    // Fetch users for operator selection
    fetchUsers();
});

// 2. Persist state to URL without reloading
watch([() => selectedContext.value, activeTab], ([newCtx, newTab]) => {
    const params = new URLSearchParams(window.location.search);
    
    if (newCtx.plantId) params.set('plantId', String(newCtx.plantId)); else params.delete('plantId');
    if (newCtx.lineId) params.set('lineId', String(newCtx.lineId)); else params.delete('lineId');
    if (newCtx.machineId) params.set('machineId', String(newCtx.machineId)); else params.delete('machineId');
    
    if (newTab) params.set('tab', newTab); else params.delete('tab');

    const newUrl = `${window.location.pathname}?${params.toString()}`;
    
    // Only update if URL is actually different to prevent infinite loops
    if (window.location.href !== window.location.origin + newUrl) {
        window.history.replaceState({ path: newUrl }, '', newUrl);
    }
}, { deep: true, flush: 'post' });
</script>

<template>
    <Head title="Assets" />

    <AppLayout :breadcrumbs="breadcrumbs" :fluid="true">
        <!-- Ocean Theme Layout -->
        <OceanLayout v-if="isOcean">
            <template #default>
                <div class="h-[calc(100vh-65px)] p-4 flex flex-col gap-6 overflow-y-auto">
                    <!-- Ocean View: Card Grid -->
                    <ConfigurationOceanView 
                        :plants="props.plants"
                        :canManage="canManage"
                        :onSelectContext="selectContext"
                        :onOpenPlantDialog="openPlantDialog"
                        :onOpenLineDialog="openLineDialog"
                        :onOpenMachineDialog="openMachineDialog"
                        :selectedContext="selectedContext"
                    />

                    <!-- Dashboard Area (Appears below grid when context active) -->
                    <div v-if="selectedContext.plantId" class="border-t border-blue-200 dark:border-blue-900 pt-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
                        <!-- Single Tabs Component wrapping both TabsList and TabsContent -->
                        <Tabs v-model="activeTab" class="w-full">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-xl font-bold flex items-center gap-2">
                                    <Monitor class="h-5 w-5 text-blue-500" />
                                    Performance Dashboard
                                </h2>
                                <TabsList>
                                    <TabsTrigger value="dashboard">Dashboard</TabsTrigger>
                                    <TabsTrigger v-if="selectedContext.machineId" value="machine_shifts">Shifts</TabsTrigger>
                                    <TabsTrigger v-if="selectedContext.machineId" value="shift_report">Shift Report</TabsTrigger>
                                    <TabsTrigger v-if="selectedContext.machineId" value="products">Products</TabsTrigger>
                                    <TabsTrigger v-if="selectedContext.machineId" value="machine_health">Health</TabsTrigger>
                                    <TabsTrigger v-if="selectedContext.machineId" value="machine_settings">Settings</TabsTrigger>
                                    <TabsTrigger value="targets">Targets</TabsTrigger>
                                </TabsList>
                            </div>

                        <TabsContent value="dashboard" class="mt-0">
                            <div class="rounded-xl min-h-[500px]">
                                <OceanDashboard 
                                    :plantId="selectedContext.plantId"
                                    :lineId="selectedContext.lineId"
                                    :machineId="selectedContext.machineId"
                                />
                            </div>
                        </TabsContent>
                        <TabsContent value="machine_shifts" v-if="selectedContext.machineId">
                            <div class="bg-white/50 dark:bg-slate-900/50 rounded-xl p-4 backdrop-blur-sm border border-blue-100 dark:border-blue-900 shadow-sm min-h-[500px]">
                                  <!-- Active Shift Status Card for Ocean -->
                                  <div v-if="getActiveShift(selectedContext.machineId)" class="mb-4 p-4 rounded-lg bg-green-50 dark:bg-green-950/30 border border-green-200 dark:border-green-800 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 rounded-full bg-green-500 flex items-center justify-center text-white">
                                                <Play class="h-5 w-5" />
                                            </div>
                                            <div>
                                                <div class="font-bold text-green-700 dark:text-green-400">Shift Active</div>
                                                <div class="text-sm opacity-80">{{ getActiveShift(selectedContext.machineId)?.shiftName }} • {{ currentShiftDuration }}</div>
                                            </div>
                                        </div>
                                        <Badge variant="outline" class="bg-white text-green-700">{{ getActiveShift(selectedContext.machineId)?.productName }}</Badge>
                                  </div>
                                  <ShiftReport 
                                      ref="shiftReportRef" 
                                      :machineId="selectedContext.machineId" 
                                      :products="props.products"
                                      :users="users"
                                      :shifts="props.shifts"
                                  />
                            </div>
                        </TabsContent>
                        <TabsContent value="products" v-if="selectedContext.machineId">
                            <div class="bg-white/50 dark:bg-slate-900/50 rounded-xl p-4 backdrop-blur-sm border border-blue-100 dark:border-blue-900 shadow-sm min-h-[500px]">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                        <Package class="h-5 w-5" />
                                        Products
                                    </h3>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div v-for="product in props.products || []" :key="product.id" 
                                         class="bg-white dark:bg-slate-800 border border-blue-200 dark:border-blue-800 rounded-lg p-4 flex items-center justify-between hover:shadow-md transition-shadow">
                                        <div>
                                            <div class="font-semibold text-gray-900 dark:text-gray-100">{{ product.name }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">SKU: {{ product.sku }}</div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Ideal Rate</div>
                                            <div class="font-semibold text-blue-600 dark:text-blue-400">{{ product.ideal_rate || '—' }} <span class="text-xs text-gray-400">UPH</span></div>
                                        </div>
                                    </div>
                                    <div v-if="!props.products?.length" class="col-span-2 p-8 text-center text-gray-500 border border-dashed border-gray-300 dark:border-gray-700 rounded-lg">
                                        No products in catalog.
                                    </div>
                                </div>
                            </div>
                        </TabsContent>
                        <TabsContent value="shift_report" v-if="selectedContext.machineId">
                            <div class="bg-white/50 dark:bg-slate-900/50 rounded-xl p-4 backdrop-blur-sm border border-blue-100 dark:border-blue-900 shadow-sm min-h-[500px]">
                                <ShiftReport 
                                    :machineId="selectedContext.machineId" 
                                    :products="props.products"
                                    :users="users"
                                    :shifts="props.shifts"
                                />
                            </div>
                        </TabsContent>
                        <TabsContent value="machine_health" v-if="selectedContext.machineId">
                            <div class="bg-white/50 dark:bg-slate-900/50 rounded-xl p-4 backdrop-blur-sm border border-blue-100 dark:border-blue-900 shadow-sm min-h-[500px]">
                                <MachineHealth :machineId="selectedContext.machineId" />
                            </div>
                        </TabsContent>
                        <TabsContent value="machine_settings" v-if="selectedContext.machineId">
                            <div class="bg-white/50 dark:bg-slate-900/50 rounded-xl p-4 backdrop-blur-sm border border-blue-100 dark:border-blue-900 shadow-sm min-h-[500px] space-y-6">
                                <!-- Machine Specific Settings -->
                                <div>
                                    <h3 class="text-lg font-bold">General Settings</h3>
                                    <p class="text-sm text-muted-foreground mb-4">Basic properties for {{ plants.find((p: any) => p.id === selectedContext.plantId)?.lines.find((l: any) => l.id === selectedContext.lineId)?.machines.find((m: any) => m.id === selectedContext.machineId)?.name }}</p>
                                    
                                    <div class="grid gap-6">
                                        <Card>
                                            <CardHeader>
                                                <CardTitle class="text-base">Machine Details</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div class="grid gap-4">
                                                    <div class="grid gap-2">
                                                        <Label>Machine Name</Label>
                                                        <div class="p-2 border rounded bg-muted/50">{{ plants.find((p: any) => p.id === selectedContext.plantId)?.lines.find((l: any) => l.id === selectedContext.lineId)?.machines.find((m: any) => m.id === selectedContext.machineId)?.name }}</div>
                                                    </div>
                                                    <div class="grid gap-2">
                                                        <Label>Default Ideal Rate</Label>
                                                        <div class="p-2 border rounded bg-muted/50">{{ plants.find((p: any) => p.id === selectedContext.plantId)?.lines.find((l: any) => l.id === selectedContext.lineId)?.machines.find((m: any) => m.id === selectedContext.machineId)?.default_ideal_rate }} u/h</div>
                                                    </div>
                                                    <div class="flex justify-end">
                                                        <Button variant="outline" size="sm" @click="openMachineDialog(selectedContext.lineId, plants.find((p: any) => p.id === selectedContext.plantId)?.lines.find((l: any) => l.id === selectedContext.lineId)?.machines.find((m: any) => m.id === selectedContext.machineId))">Edit Properties</Button>
                                                    </div>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>
                                </div>
                                
                                <!-- Shift Schedule Configuration -->
                                <div class="border-t pt-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <div>
                                            <h3 class="text-lg font-bold">Shift Schedule Configuration</h3>
                                            <p class="text-sm text-muted-foreground">Manage shifts available for this plant (all machines in plant share shifts).</p>
                                        </div>
                                        <div class="flex gap-2" v-if="canManage(selectedContext.plantId!) && can('shifts.create')">
                                            <Button size="sm" variant="outline" @click="openShiftDialog(selectedContext.plantId)">Manage Plant Shifts</Button>
                                        </div>
                                    </div>
                                    <Card>
                                        <CardContent class="p-0">
                                            <div class="rounded-md border">
                                                <table class="w-full text-sm text-left">
                                                    <thead class="bg-muted text-muted-foreground">
                                                        <tr>
                                                            <th class="p-3 font-medium">Name</th>
                                                            <th class="p-3 font-medium">Start Time</th>
                                                            <th class="p-3 font-medium">End Time</th>
                                                            <th class="p-3 font-medium text-right">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y">
                                                        <tr v-for="shift in allShifts" :key="shift.id">
                                                            <td class="p-3 flex items-center gap-2">
                                                                <CheckCircle class="h-4 w-4 text-green-600" />
                                                                {{ shift.name }}
                                                                <Badge variant="outline" class="text-[10px] ml-2">{{ shift.type }}</Badge>
                                                                <Badge variant="secondary" class="text-[9px] ml-1 text-muted-foreground">{{ shift.plant_name || 'Global' }}</Badge>
                                                            </td>
                                                            <td class="p-3 font-mono">{{ shift.start_time?.substring(0,5) }}</td>
                                                            <td class="p-3 font-mono">{{ shift.end_time?.substring(0,5) }}</td>
                                                            <td class="p-3 text-right">
                                                                <Button variant="ghost" size="icon" class="h-8 w-8" @click="openShiftDialog(selectedContext.plantId, shift)" v-if="canManage(selectedContext.plantId!) && can('shifts.edit')"><Pencil class="h-4 w-4"/></Button>
                                                                <Button variant="ghost" size="icon" class="h-8 w-8 text-red-500" @click="deleteShift(shift.id)" v-if="canManage(selectedContext.plantId!) && can('shifts.delete')"><Trash2 class="h-4 w-4"/></Button>
                                                            </td>
                                                        </tr>
                                                        <tr v-if="allShifts.length === 0">
                                                            <td colspan="4" class="p-8 text-center text-muted-foreground italic">
                                                                <div class="flex flex-col items-center gap-2">
                                                                    <Clock class="h-8 w-8 text-muted-foreground/50" />
                                                                    <span>No shifts defined in the organization.</span>
                                                                    <span class="text-xs">Create a shift to enable production tracking across all plants.</span>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>
                            </div>
                        </TabsContent>
                        <TabsContent value="targets">
                            <div class="bg-white/50 dark:bg-slate-900/50 rounded-xl p-4 backdrop-blur-sm border border-blue-100 dark:border-blue-900 shadow-sm min-h-[500px]">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                        <Target class="h-5 w-5" />
                                        OEE Targets
                                    </h3>
                                    <Button v-if="can('targets.create')" @click="openTargetDialog(null)" size="sm" class="gap-2">
                                        <Plus class="h-4 w-4" />
                                        Add Target
                                    </Button>
                                </div>
                                <div class="bg-white dark:bg-slate-800 rounded-lg border border-blue-200 dark:border-blue-800 overflow-hidden">
                                    <table class="w-full">
                                        <thead class="bg-blue-50 dark:bg-slate-900">
                                            <tr class="border-b border-blue-200 dark:border-blue-800">
                                                <th class="text-left p-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Scope</th>
                                                <th class="text-left p-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Effective Period</th>
                                                <th class="text-right p-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Target OEE</th>
                                                <th class="text-right p-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="target in props.targets || []" :key="target.id" 
                                                class="border-b border-gray-100 dark:border-gray-700 hover:bg-blue-50/30 dark:hover:bg-slate-700/30">
                                                <td class="p-3">
                                                    <div class="flex flex-col">
                                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ target.targetable_type?.replace('App\\Models\\', '') }}</span>
                                                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ target.targetable?.name || 'ID: ' + target.targetable_id }}</span>
                                                    </div>
                                                </td>
                                                <td class="p-3">
                                                    <div class="flex flex-col">
                                                        <span class="text-sm text-green-600 dark:text-green-400">FROM: {{ target.effective_from }}</span>
                                                        <span class="text-sm text-gray-500 dark:text-gray-400">TO: {{ target.effective_to || 'Indefinite' }}</span>
                                                    </div>
                                                </td>
                                                <td class="p-3 text-right font-bold text-lg text-blue-600 dark:text-blue-400">
                                                    {{ target.target_oee }}%
                                                </td>
                                                <td class="p-3 text-right">
                                                    <Button v-if="can('targets.edit')" variant="ghost" size="icon" class="h-8 w-8" @click="openTargetDialog(target)">
                                                        <Pencil class="h-4 w-4" />
                                                    </Button>
                                                </td>
                                            </tr>
                                            <tr v-if="!props.targets?.length">
                                                <td colspan="4" class="p-8 text-center text-gray-500 dark:text-gray-400 italic">No targets defined for this scope.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </TabsContent>
                        </Tabs>
                    </div>
                </div>
            </template>
        </OceanLayout>

        <!-- Industrial Theme Layout -->
        <IndustrialLayout v-else-if="isIndustrial">
            <template #default>
                 <div class="h-[calc(100vh-65px)] flex overflow-hidden bg-zinc-950">
                    <!-- Left Pane: Asset Schematic (Sidebar) -->
                    <div 
                        class="border-r border-orange-500/20 bg-black/50 flex flex-col transition-all duration-300 ease-in-out"
                        :class="isDashboardCollapsed ? 'w-0 opacity-0 overflow-hidden' : 'w-[400px] shrink-0'"
                    >
                         <div class="p-3 border-b border-orange-500/20 bg-orange-500/5 flex items-center justify-between sticky top-0 backdrop-blur-md z-10">
                            <div class="flex items-center gap-2 text-orange-500">
                                <Settings2 class="h-4 w-4" />
                                <span class="font-mono font-bold text-sm tracking-wider">SYSTEM_ASSETS</span>
                            </div>
                         </div>
                         <div class="flex-1 overflow-y-auto custom-scrollbar">
                             <ConfigurationIndustrialView 
                                :plants="props.plants"
                                :selectedContext="selectedContext"
                                :canManage="canManage"
                                :onSelectContext="selectContext"
                                :onOpenPlantDialog="openPlantDialog"
                                :onOpenLineDialog="openLineDialog"
                                :onOpenMachineDialog="openMachineDialog"
                                :onDeletePlant="deletePlant"
                                :onDeleteLine="deleteLine"
                                :onDeleteMachine="deleteMachine"
                            />
                         </div>
                    </div>
                    
                    <!-- Right Pane: Main Console (Dashboard) -->
                    <div class="flex-1 flex flex-col bg-zinc-900/50 relative overflow-hidden">
                        <!-- Console Header -->
                        <div class="bg-zinc-900/80 p-3 flex items-center justify-between border-b border-orange-500/30 backdrop-blur z-20">
                             <div class="flex items-center gap-3">
                                 <Button 
                                    variant="ghost" 
                                    size="icon" 
                                    class="h-8 w-8 text-orange-500 hover:bg-orange-500/10 hover:text-orange-400"
                                    @click="isDashboardCollapsed = !isDashboardCollapsed"
                                    :title="isDashboardCollapsed ? 'Show Assets' : 'Hide Assets'"
                                >
                                    <component :is="isDashboardCollapsed ? ChevronRight : ChevronDown" class="h-5 w-5 rotate-90" :class="{'rotate-[-90deg]': isDashboardCollapsed}" />
                                </Button>
                                <div class="font-mono font-bold text-orange-500/80 flex items-center gap-2">
                                    <Monitor class="h-4 w-4" />
                                    <span>MAIN_CONSOLE</span>
                                    <span v-if="selectedContext.plantId" class="text-xs text-orange-500/50">
                                        > {{ selectedContext.machineId ? `${machineTerm.toUpperCase()}_VIEW` : (selectedContext.lineId ? `${lineTerm.toUpperCase()}_VIEW` : `${plantTerm.toUpperCase()}_VIEW`) }}
                                    </span>
                                </div>
                             </div>

                              <Tabs :modelValue="activeTab" @update:modelValue="(val: any) => activeTab = val" class="ml-auto">
                                    <TabsList class="h-8 bg-black/40 border border-orange-500/20">
                                        <TabsTrigger value="dashboard" class="text-xs font-mono data-[state=active]:bg-orange-500 data-[state=active]:text-black hover:text-orange-400">METRICS</TabsTrigger>
                                        <TabsTrigger v-if="selectedContext.machineId" value="machine_shifts" class="text-xs font-mono data-[state=active]:bg-orange-500 data-[state=active]:text-black hover:text-orange-400">SHIFTS</TabsTrigger>
                                        <TabsTrigger value="targets" class="text-xs font-mono data-[state=active]:bg-orange-500 data-[state=active]:text-black hover:text-orange-400">TARGETS</TabsTrigger>
                                        <TabsTrigger value="products" class="text-xs font-mono data-[state=active]:bg-orange-500 data-[state=active]:text-black hover:text-orange-400">PRODUCTS</TabsTrigger>
                                    </TabsList>
                              </Tabs>
                        </div>

                        <!-- Console Content -->
                        <div class="flex-1 overflow-y-auto p-4 relative">
                             <!-- Grid Background Effect -->
                             <div class="absolute inset-0 pointer-events-none opacity-[0.03]" 
                                  style="background-image: linear-gradient(#f97316 1px, transparent 1px), linear-gradient(90deg, #f97316 1px, transparent 1px); background-size: 40px 40px;">
                             </div>

                             <div v-if="selectedContext.plantId" class="relative z-10 h-full flex flex-col">
                                 <!-- Dashboard View -->
                                 <div v-show="activeTab === 'dashboard'" class="flex-1 mt-0 h-full">
                                    <OeeDashboard :initialContext="selectedContext" :embedded="true" :initialOptions="props.plants" @update:context="handleContextUpdate" />
                                 </div>
                                 
                                 <!-- Shifts View -->
                                 <div v-show="activeTab === 'machine_shifts'" class="flex-1 mt-0 h-full overflow-y-auto p-4">
                                     <!-- Active Shift Control Panel -->
                                     <div v-if="getActiveShift(selectedContext.machineId)" class="bg-emerald-950/30 border border-emerald-500/30 rounded-lg p-5 mb-6 animate-in slide-in-from-top-2">
                                         <div class="flex items-center justify-between">
                                             <div class="flex items-center gap-4">
                                                 <div class="bg-emerald-500/20 p-3 rounded-full animate-pulse">
                                                     <Activity class="h-6 w-6 text-emerald-500" />
                                                 </div>
                                                 <div>
                                                     <h3 class="text-emerald-400 font-bold text-lg tracking-wide uppercase">Shift In Progress</h3>
                                                     <div class="text-zinc-400 text-sm font-mono flex items-center gap-3">
                                                         <span>{{ getActiveShift(selectedContext.machineId)?.shiftName }}</span>
                                                         <span class="w-1.5 h-1.5 rounded-full bg-zinc-600"></span>
                                                         <span class="text-emerald-300 font-bold">{{ currentShiftDuration }}</span>
                                                     </div>
                                                 </div>
                                             </div>
                                             <div class="flex items-center gap-3">
                                                 <Button @click="isDowntimeDialogOpen = true" class="bg-orange-600 hover:bg-orange-500 text-black border border-orange-400 font-bold">
                                                     LOG DOWNTIME
                                                 </Button>
                                                 <Button @click="isChangeProductDialogOpen = true" class="bg-blue-600 hover:bg-blue-500 text-white border border-blue-400 font-bold">
                                                     CHANGE PRODUCT
                                                 </Button>
                                                 <Button @click="isEndShiftDialogOpen = true" variant="destructive" class="bg-red-900/50 hover:bg-red-900 border border-red-500/50 text-red-200">
                                                     END SHIFT
                                                 </Button>
                                             </div>
                                         </div>
                                         
                                         <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6 pt-4 border-t border-emerald-500/10">
                                              <div>
                                                  <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Operator</div>
                                                  <div class="text-zinc-200 font-mono text-sm flex items-center gap-2">
                                                      <User class="h-3 w-3" />
                                                      {{ getActiveShift(selectedContext.machineId)?.startedBy?.name }}
                                                  </div>
                                              </div>
                                              <div>
                                                  <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Product</div>
                                                  <div class="text-zinc-200 font-mono text-sm flex items-center gap-2">
                                                      <Package class="h-3 w-3" />
                                                      {{ getActiveShift(selectedContext.machineId)?.productName || 'No Product' }}
                                                  </div>
                                              </div>
                                               <div>
                                                  <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Started At</div>
                                                  <div class="text-zinc-200 font-mono text-sm">
                                                      {{ new Date(getActiveShift(selectedContext.machineId)?.startedAt || new Date()).toLocaleTimeString() }}
                                                  </div>
                                              </div>
                                         </div>
                                     </div>

                                     <!-- Start Shift Banner (If no active shift) -->
                                     <div v-else class="bg-zinc-900/50 border border-zinc-800 rounded-lg p-8 mb-6 text-center">
                                         <div class="inline-flex bg-zinc-800 p-4 rounded-full mb-4">
                                             <Power class="h-8 w-8 text-zinc-500" />
                                         </div>
                                         <h3 class="text-zinc-300 font-bold text-lg mb-2">Machine Offline</h3>
                                         <p class="text-zinc-500 text-sm mb-6 max-w-md mx-auto">No production shift is currently active on this machine. Start a shift to begin tracking OEE and production data.</p>
                                         <Button @click="isStartShiftDialogOpen = true" size="lg" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-8">
                                             START SHIFT
                                         </Button>
                                     </div>

                                     <ShiftReport 
                                         ref="shiftReportRef" 
                                         :machineId="selectedContext.machineId" 
                                         v-if="selectedContext.machineId" 
                                         :products="props.products"
                                         :users="users"
                                         :shifts="props.shifts"
                                     />
                                 </div>

                                 <!-- Targets View -->
                                 <div v-show="activeTab === 'targets'" class="flex-1 mt-0 p-4 font-mono h-full overflow-y-auto">
                                     <div class="flex justify-between items-center mb-4">
                                         <h3 class="text-lg font-bold text-orange-500 flex items-center gap-2">
                                             <Target class="h-5 w-5" />
                                             PRODUCTION TARGETS
                                         </h3>
                                         <Button v-if="can('targets.create')" @click="openTargetDialog()" size="sm" class="bg-orange-600 hover:bg-orange-500 text-black border border-orange-400 gap-1 rounded-sm h-8">
                                             <Plus class="h-4 w-4" /> ADD TARGET
                                         </Button>
                                     </div>

                                     <div class="border border-orange-500/20 rounded-sm overflow-hidden">
                                         <table class="w-full text-xs text-left">
                                             <thead class="bg-orange-500/10 text-orange-500 font-bold uppercase border-b border-orange-500/20">
                                                 <tr>
                                                     <th class="p-2">Level</th>
                                                     <th class="p-2">Effectivity</th>
                                                     <th class="p-2 text-right">OEE Goal</th>
                                                     <th class="p-2 text-right">Actions</th>
                                                 </tr>
                                             </thead>
                                             <tbody class="divide-y divide-orange-500/10 bg-black/40 text-zinc-300">
                                                 <tr v-for="target in props.targets || []" :key="target.id" class="hover:bg-orange-500/5">
                                                     <td class="p-2">
                                                         <div class="flex flex-col">
                                                             <span class="font-bold text-white">{{ getAssetLabel(target.targetable_type?.split('\\').pop() || 'Unknown') }}</span>
                                                             <span class="text-zinc-500">{{ target.targetable?.name || 'ID: ' + target.targetable_id }}</span>
                                                         </div>
                                                     </td>
                                                     <td class="p-2">
                                                         <div class="flex flex-col">
                                                             <span class="text-green-500">FROM: {{ target.effective_from }}</span>
                                                             <span class="text-zinc-500">TO: {{ target.effective_to || 'Indefinite' }}</span>
                                                         </div>
                                                     </td>
                                                     <td class="p-2 text-right font-bold text-lg text-orange-400">
                                                         {{ target.target_oee }}%
                                                     </td>
                                                     <td class="p-2 text-right">
                                                         <Button v-if="can('targets.edit')" variant="ghost" size="icon" class="h-6 w-6 text-zinc-500 hover:text-blue-400" @click="openTargetDialog(target)">
                                                             <Pencil class="h-3 w-3" />
                                                         </Button>
                                                     </td>
                                                 </tr>
                                                 <tr v-if="!props.targets?.length">
                                                     <td colspan="4" class="p-8 text-center text-zinc-600 italic">No targets defined for this scope.</td>
                                                 </tr>
                                             </tbody>
                                         </table>
                                     </div>
                                 </div>

                                 <!-- Products View -->
                                 <div v-show="activeTab === 'products'" class="flex-1 mt-0 p-4 font-mono h-full overflow-y-auto">
                                     <div class="flex justify-between items-center mb-4">
                                        <h3 class="text-lg font-bold text-orange-500 flex items-center gap-2">
                                             <Package class="h-5 w-5" />
                                             GLOBAL PRODUCTS
                                         </h3>
                                     </div>

                                     <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                         <div v-for="product in props.products || []" :key="product.id" class="bg-zinc-900 border border-zinc-800 p-3 flex items-center justify-between hover:border-orange-500/30">
                                             <div>
                                                 <div class="font-bold text-zinc-200">{{ product.name }}</div>
                                                 <div class="text-[10px] text-zinc-500">SKU: {{ product.sku }}</div>
                                             </div>
                                             <div class="text-right">
                                                 <div class="text-xs text-zinc-400">Ideal Rate</div>
                                                 <div class="font-bold text-orange-400">{{ product.ideal_rate || '—' }} <span class="text-[9px] text-zinc-600">UPH</span></div>
                                             </div>
                                         </div>
                                         <div v-if="!props.products?.length" class="col-span-2 p-8 text-center text-zinc-600 border border-dashed border-zinc-800">
                                             No products in catalog.
                                         </div>
                                     </div>
                                 </div>
                             </div>
                             <div v-else class="h-full flex flex-col items-center justify-center text-orange-500/30">
                                <Target class="h-24 w-24 mb-6 opacity-20" />
                                <h3 class="font-mono text-xl tracking-widest">SYSTEM STANDBY</h3>
                                <p class="text-sm font-mono mt-2">SELECT ASSET FROM SCHEMATIC</p>
                             </div>
                        </div>
                    </div>
                 </div>
            </template>
        </IndustrialLayout>

        <!-- Minimal Theme Layout -->
        <MinimalLayout v-else-if="isMinimal">
            <template #default>
                <div class="h-[calc(100vh-65px)] flex">
                    <!-- Sidebar: Accordion View (Fixed Width) -->
                    <div class="w-[350px] border-r border-gray-100 dark:border-gray-800 h-full overflow-y-auto bg-gray-50/50 dark:bg-gray-900/50">
                        <ConfigurationMinimalView 
                            :plants="props.plants"
                            :canManage="canManage"
                            :onSelectContext="selectContext"
                            :onOpenPlantDialog="openPlantDialog"
                            :onOpenLineDialog="openLineDialog"
                            :onOpenMachineDialog="openMachineDialog"
                        />
                    </div>
                    
                    <!-- Main Content: Dashboard -->
                    <div class="flex-1 h-full overflow-y-auto p-8 bg-white dark:bg-gray-950">
                         <div v-if="!selectedContext.plantId" class="h-full flex flex-col items-center justify-center text-muted-foreground opacity-50">
                            <Monitor class="h-16 w-16 mb-4 stroke-1" />
                            <p class="text-lg font-light">Select an asset to view details</p>
                        </div>
                        <div v-else class="max-w-6xl mx-auto space-y-8">
                            <div class="flex items-center justify-between pb-6 border-b border-gray-100">
                                <h2 class="text-3xl font-light tracking-tight">Overview</h2>
                                 <Tabs :modelValue="activeTab" @update:modelValue="(val: any) => activeTab = val">
                                    <TabsList class="bg-transparent border border-gray-200">
                                        <TabsTrigger value="dashboard">Dashboard</TabsTrigger>
                                        <TabsTrigger v-if="selectedContext.machineId" value="machine_shifts">Shifts</TabsTrigger>
                                    </TabsList>
                                 </Tabs>
                            </div>
                            
                            <Tabs :modelValue="activeTab">
                                <TabsContent value="dashboard" class="mt-0">
                                    <OeeDashboard 
                                        :initial-context="{
                                            plantId: selectedContext.plantId,
                                            lineId: selectedContext.lineId,
                                            machineId: selectedContext.machineId
                                        }"
                                        :plants="props.plants"
                                    />
                                </TabsContent>
                                 <TabsContent value="machine_shifts" class="mt-0">
                                     <ShiftReport 
                                         ref="shiftReportRef" 
                                         :machineId="selectedContext.machineId" 
                                         v-if="selectedContext.machineId" 
                                         :products="props.products"
                                         :users="users"
                                         :shifts="props.shifts"
                                     />
                                 </TabsContent>
                            </Tabs>
                        </div>
                    </div>
                </div>
            </template>
        </MinimalLayout>

        <!-- Default Layout for other themes -->
        <div v-else class="h-[calc(100vh-65px)] p-2 flex flex-col gap-2">
                <!-- Hierarchy View (Always Visible) -->
                <div class="h-full mt-2 min-h-0 flex-1">
                    <div class="flex gap-2 items-start h-full">
                        <!-- Tree View Container (Left) -->
                        <div 
                            class="border-2 border-gray-300 dark:border-gray-700 rounded-xl bg-card p-3 shadow-md h-full overflow-y-auto text-sm transition-all duration-300 ease-in-out flex-shrink-0"
                            :class="isDashboardCollapsed ? 'w-0 p-0 opacity-0 overflow-hidden border-0' : 'w-full lg:w-[33.333%] xl:w-[25%]'"
                        >
                            <div class="p-4 border-b flex items-center justify-between sticky top-0 bg-background z-20">
                                <div class="flex items-center gap-2">
                                    <Button 
                                        variant="ghost" 
                                        size="icon" 
                                        class="h-7 w-7 lg:hidden"
                                        @click="isDashboardCollapsed = !isDashboardCollapsed"
                                        title="Show Dashboard"
                                    >
                                        <component :is="isDashboardCollapsed ? ChevronRight : ChevronDown" class="h-4 w-4 -rotate-90" :class="{'rotate-90': isDashboardCollapsed}" />
                                    </Button>
                                    <h2 class="font-semibold">Assets</h2>
                                </div>
                                <div class="flex items-center gap-1">
                                    <Button variant="ghost" size="icon" class="h-6 w-6" :class="isCollapsible ? 'text-primary bg-primary/10' : 'text-muted-foreground'" @click="isCollapsible = !isCollapsible" title="Toggle Collapsible View">
                                        <Settings2 class="h-3.5 w-3.5" />
                                    </Button>

                                    <Badge v-if="props.userPermissions?.managedPlantIds?.length" variant="outline" class="text-[10px] bg-green-50 text-green-700 border-green-200">
                                        {{ props.userPermissions.managedPlantIds.length }} Managed
                                    </Badge>
                                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="openPlantDialog()" v-if="isAdmin">
                                        <Plus class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                             <!-- ... (Tree Content Same as before) ... -->
                            <!-- Tree Content -->
                           <div v-for="org in (props.organizations || [props.organization])" :key="org.id" class="mb-4">
                               <!-- Root: Organization -->
                               <div class="group flex items-center justify-between mb-2 cursor-pointer p-2 hover:bg-accent/50 rounded-lg border border-transparent hover:border-border transition-all" 
                                    @click="selectContext(null, null, null)"
                                    :class="{'bg-accent border-primary/50 shadow-sm': !selectedContext.plantId}"
                               >
                                   <div class="flex items-center gap-2 overflow-hidden">
                                       <div class="p-1.5 bg-primary/10 rounded-md ring-1 ring-primary/20 shrink-0">
                                           <Package class="h-4 w-4 text-primary" />
                                       </div>
                                       <div class="overflow-hidden">
                                           <h3 class="font-bold text-sm leading-tight truncate">{{ org.name }}</h3>
                                           <p class="text-[9px] text-muted-foreground font-medium truncate">Organization Root</p>
                                       </div>
                                   </div>
                                   <div class="flex gap-1">
                                       <button v-if="isAdmin" class="opacity-0 group-hover:opacity-100 transition-opacity p-1 hover:bg-background rounded shadow-sm border border-transparent hover:border-border" title="Rename Organization" @click.stop="openOrgDialog(org)">
                                           <Pencil class="h-3.5 w-3.5 text-muted-foreground"/>
                                       </button>
                                       <button v-if="isAdmin" class="opacity-0 group-hover:opacity-100 transition-opacity p-1 hover:bg-background rounded shadow-sm border border-transparent hover:border-border" :title="`Add ${plantTerm}`" @click.stop="openPlantDialog({ organization_id: org.id })">
                                           <Plus class="h-3.5 w-3.5 text-primary"/>
                                       </button>
                                   </div>
                               </div>
    
                            <!-- Level 1: Plants -->
                            <div class="pl-2 relative">
                                <div v-if="!org.plants || org.plants.length === 0" class="text-[10px] text-muted-foreground italic pl-2">No {{ plantsTerm.toLowerCase() }}.</div>
                                
                                <div v-for="plant in org.plants" :key="plant.id" class="relative pl-6 pb-4">
                                    <!-- Vertical Line (Parent to Sibling) - Thicker (4px) - Blue (Plant Level) -->
                                    <div class="absolute left-[11px] top-0 w-[4px] bg-blue-300 dark:bg-blue-800/60"
                                         :class="plant === org.plants[org.plants.length - 1] ? 'h-[28px]' : 'h-full'"></div>
                                    
                                    <!-- Horizontal Connector - Thicker (4px) -->
                                    <div class="absolute left-[11px] top-7 w-[14px] h-[4px] bg-blue-300 dark:bg-blue-800/60 rounded-bl-sm"></div>
    
                                    <!-- Plant Node -->
                                    <div 
                                        class="group flex flex-col p-3 rounded-lg border-[3px] border-blue-200 dark:border-blue-900 bg-card hover:shadow-md transition-all cursor-pointer relative z-10"
                                        :class="selectedContext.plantId === plant.id && !selectedContext.lineId ? 'border-primary ring-1 ring-primary/20 shadow-md scale-[1.01]' : 'hover:border-blue-300'"
                                        @click.stop="selectContext(plant.id, null, null)"
                                    >
                                        <div class="flex items-center gap-2">
                                            <!-- Chevron for Collapsible -->
                                            <div v-if="isCollapsible" class="absolute left-1 top-1/2 -translate-y-1/2">
                                                <ChevronDown v-if="isExpanded(`plant_${plant.id}`)" class="h-4 w-4 text-blue-500" />
                                                <ChevronRight v-else class="h-4 w-4 text-gray-400" />
                                            </div>

                                            <div class="flex-shrink-0 flex items-center justify-center h-7 w-7 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400" :class="{'ml-4': isCollapsible}">
                                                <Factory class="h-4 w-4" />
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="font-bold text-base truncate" :title="plant.name">{{ plant.name }}</div>
                                            </div>
                                        </div>
                                    <div class="flex justify-end gap-0.5 mt-1 opacity-0 group-hover:opacity-100 transition-opacity" v-if="canManage(plant.id)">
                                            <button v-if="isAdmin" class="p-1 hover:bg-muted rounded" :title="`Add ${lineTerm}`" @click.stop="openLineDialog(plant.id)"><Plus class="h-3 w-3 text-muted-foreground hover:text-foreground"/></button>
                                            <button class="p-1 hover:bg-muted rounded" @click.stop="openPlantDialog(plant)"><Pencil class="h-3 w-3 text-muted-foreground hover:text-foreground"/></button>
                                            <button class="p-1 hover:bg-muted rounded" @click.stop="deletePlant(plant.id)"><Trash2 class="h-3 w-3 text-destructive/70 hover:text-destructive"/></button>
                                        </div>
                                    </div>
    
                                    <!-- Level 2: Lines -->
                                    <div class="mt-2 text-sm relative" v-if="isExpanded(`plant_${plant.id}`)">
                                        <div v-if="plant.lines.length === 0" class="text-[10px] text-muted-foreground italic pl-1 py-0.5">No {{ linesTerm.toLowerCase() }}.</div>
    
                                        <div v-for="line in plant.lines" :key="line.id" class="relative pl-6 pb-2">
                                            <!-- Vertical Line - Medium (3px) - Orange (Line Level) -->
                                            <div class="absolute left-[14px] top-0 w-[3px] bg-orange-300 dark:bg-orange-800/60"
                                                :class="line === plant.lines[plant.lines.length - 1] ? 'h-[22px]' : 'h-full'"></div>
                                            
                                            <!-- Horizontal Connector - Medium (3px) -->
                                            <div class="absolute left-[14px] top-5 w-[12px] h-[3px] bg-orange-300 dark:bg-orange-800/60 rounded-bl-sm"></div>
                                            
                                            <!-- Line Node -->
                                            <div 
                                                class="group flex flex-col p-2.5 rounded-md border-2 border-dashed border-orange-200 dark:border-orange-800/50 bg-muted/10 hover:bg-muted/30 transition-all cursor-pointer"
                                                :class="selectedContext.lineId === line.id && !selectedContext.machineId ? 'border-orange-500 border-solid bg-orange-50/50 dark:bg-orange-950/20 shadow-md' : 'hover:border-orange-300'"
                                                @click.stop="selectContext(plant.id, line.id, null)"
                                            >
                                                 <div class="flex items-center gap-2">
                                                    <!-- Chevron for Collapsible -->
                                                    <div v-if="isCollapsible" class="absolute left-1 top-1/2 -translate-y-1/2">
                                                        <ChevronDown v-if="isExpanded(`line_${line.id}`)" class="h-3 w-3 text-orange-500" />
                                                        <ChevronRight v-else class="h-3 w-3 text-gray-400" />
                                                    </div>

                                                    <div class="flex-shrink-0 flex items-center justify-center h-5 w-5 rounded bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400" :class="{'ml-3': isCollapsible}">
                                                        <GitCommit class="h-3.5 w-3.5" />
                                                    </div>
                                                    <span class="font-semibold text-sm truncate" :title="line.name">{{ line.name }}</span>
                                                </div>
                                                <div class="flex justify-end gap-0.5 mt-0.5 opacity-0 group-hover:opacity-100 transition-opacity" v-if="canManage(plant.id)">
                                                    <button class="p-0.5 hover:bg-muted rounded" title="View" @click.stop="selectContext(plant.id, line.id, null)"><Eye class="h-2.5 w-2.5 text-muted-foreground hover:text-foreground"/></button>
                                                    <button v-if="isAdmin" class="p-0.5 hover:bg-muted rounded" :title="`Add ${machineTerm}`" @click.stop="openMachineDialog(line.id)"><Plus class="h-2.5 w-2.5 text-muted-foreground hover:text-foreground"/></button>
                                                    <button class="p-0.5 hover:bg-muted rounded" @click.stop="openLineDialog(plant.id, line)"><Pencil class="h-2.5 w-2.5 text-muted-foreground hover:text-foreground"/></button>
                                                    <button class="p-0.5 hover:bg-muted rounded" @click.stop="deleteLine(line.id)"><Trash2 class="h-2.5 w-2.5 text-destructive/70 hover:text-destructive"/></button>
                                                </div>
                                                <div class="flex justify-end gap-0.5 mt-0.5 opacity-0 group-hover:opacity-100 transition-opacity" v-else>
                                                    <button class="p-0.5 hover:bg-muted rounded" title="View" @click.stop="selectContext(plant.id, line.id, null)"><Eye class="h-2.5 w-2.5 text-muted-foreground hover:text-foreground"/></button>
                                                </div>
                                            </div>
    
                                            <!-- Level 3: Machines -->
                                            <div class="mt-1 relative" v-if="isExpanded(`line_${line.id}`)">
                                                  <div 
                                                    v-for="machine in line.machines" 
                                                    :key="machine.id" 
                                                    class="relative pl-5 py-0.5"
                                                  >
                                                     <!-- Vertical Line - Thinner (2px) - Emerald (Machine Level) -->
                                                     <div class="absolute left-[10px] top-0 w-[2px] bg-emerald-300 dark:bg-emerald-800/60"
                                                          :class="machine === line.machines[line.machines.length - 1] ? 'h-[20px]' : 'h-full'"></div>
                                                     
                                                     <!-- Horizontal Connector - Thinner (2px) -->
                                                     <div class="absolute left-[10px] top-[20px] w-[10px] h-[2px] bg-emerald-300 dark:bg-emerald-800/60"></div>

                                                     <div 
                                                        class="group/machine flex items-center justify-between py-1.5 px-2 rounded-md hover:bg-muted/60 cursor-pointer transition-colors border border-emerald-100 dark:border-emerald-900/40 hover:border-emerald-300"
                                                        :class="selectedContext.machineId === machine.id ? 'bg-muted font-bold text-primary ring-1 ring-inset ring-primary/10 border-primary/20 shadow-sm' : ''"
                                                        @click.stop="selectContext(plant.id, line.id, machine.id)"
                                                     >
                                                          <div class="flex items-center gap-2 min-w-0 flex-1">
                                                              <Monitor class="flex-shrink-0 h-3.5 w-3.5 text-emerald-600" />
                                                              <span class="text-xs font-medium truncate text-foreground" :title="machine.name">{{ machine.name }}</span>
                                                          </div>
                                                          <div class="flex gap-0.5 opacity-0 group-hover/machine:opacity-100 transition-opacity" v-if="canManage(plant.id)">
                                                              <button class="p-0.5 hover:bg-muted rounded" title="Edit" @click.stop="openMachineDialog(line.id, machine)"><Pencil class="h-2.5 w-2.5 text-muted-foreground hover:text-foreground"/></button>
                                                              <button class="p-0.5 hover:bg-muted rounded" title="Delete" @click.stop="deleteMachine(machine.id)"><Trash2 class="h-2.5 w-2.5 text-destructive/70 hover:text-destructive"/></button>
                                                          </div>
                                                      </div>
                                                  </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                                    </div>
                                </div>

                        <!-- Real-time Dashboard Panel (Right) -->
                        <div 
                            class="border rounded-lg bg-card shadow-sm h-full overflow-y-auto transition-all duration-300 ease-in-out flex-1"
                        >
                            <Tabs :modelValue="activeTab" @update:modelValue="(val: any) => activeTab = val" class="h-full flex flex-col">
                                <div class="px-2 py-1 border-b flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <Button 
                                            variant="ghost" 
                                            size="icon" 
                                            class="h-7 w-7"
                                            @click="isDashboardCollapsed = !isDashboardCollapsed"
                                            :title="isDashboardCollapsed ? 'Show Assets Panel' : 'Hide Assets Panel'"
                                        >
                                            <component :is="isDashboardCollapsed ? ChevronRight : ChevronDown" class="h-4 w-4 rotate-90" :class="{'rotate-[-90deg]': isDashboardCollapsed}" />
                                        </Button>
                                        <TabsList class="h-7 w-auto">
                                        <TabsTrigger v-if="selectedContext.plantId" value="dashboard" class="text-xs h-5 px-3">Dashboard</TabsTrigger>
                                        <TabsTrigger v-if="selectedContext.machineId" value="machine_shifts" class="text-xs h-5 px-3">Shifts</TabsTrigger>
                                        <TabsTrigger v-if="selectedContext.machineId" value="shift_report" class="text-xs h-5 px-3">Shift Report</TabsTrigger>
                                        <TabsTrigger v-if="selectedContext.machineId" value="machine_products" class="text-xs h-5 px-3">Products</TabsTrigger>
                                        <TabsTrigger v-if="selectedContext.machineId" value="machine_health" class="text-xs h-5 px-3">Health</TabsTrigger>

                                        <TabsTrigger v-if="selectedContext.machineId" value="machine_settings" class="text-xs h-5 px-3">Settings</TabsTrigger>
                                        <TabsTrigger value="targets" class="text-xs h-5 px-3">Targets</TabsTrigger>
                                        <TabsTrigger v-if="selectedContext.plantId && !selectedContext.lineId && !selectedContext.machineId" value="plant_categories" class="text-xs h-5 px-3">Categories</TabsTrigger>
                                        
                                        <!-- Global Config -->
                                        <TabsTrigger v-if="!selectedContext.plantId" value="global_products" class="text-xs h-5 px-3">Global Products</TabsTrigger>


                                    </TabsList>
                                    </div>
                                </div>
                                
                                <TabsContent v-if="activeTab === 'dashboard'" value="dashboard" class="flex-1 p-2 h-full overflow-y-auto mt-0">
                                    <OeeDashboard :initialContext="selectedContext" :embedded="true" :initialOptions="props.plants" @update:context="handleContextUpdate" />
                                </TabsContent>

                                <TabsContent v-if="activeTab === 'shift_report'" value="shift_report" class="flex-1 p-2 h-full overflow-y-auto mt-0">
                                    <ShiftReport 
                                        :machineId="selectedContext.machineId ?? 0" 
                                        v-if="selectedContext.machineId" 
                                        :products="props.products"
                                        :users="users"
                                        :shifts="props.shifts"
                                        :reasonCodes="props.reasonCodes"
                                    />
                                </TabsContent>

                                <!-- Machine: Shifts Tab (Report + Config) -->
                                <TabsContent v-if="activeTab === 'machine_shifts' && selectedContext.machineId" value="machine_shifts" class="flex-1 p-4 h-full overflow-y-auto mt-0 space-y-6">
                                    
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h3 class="text-lg font-bold">Shift Timeline</h3>
                                            <p class="text-sm text-muted-foreground">Manage ongoing and past production shifts.</p>
                                        </div>
                                        <Button variant="outline" size="sm" 
                                            @click="isManualShiftDialogOpen = true"
                                            :disabled="currentMachinePlantId ? !canManage(currentMachinePlantId) : false">
                                            <HistoryIcon class="h-4 w-4 mr-2" /> Log Past Shift
                                        </Button>
                                    </div>

                                    <ShiftCreateDialog 
                                        v-if="selectedContext.machineId"
                                        v-model:open="isManualShiftDialogOpen"
                                        :machineId="selectedContext.machineId"
                                        :shifts="props.shifts"
                                        :products="products"
                                        :users="users"
                                        :reasonCodes="props.reasonCodes"
                                    />

                                    <!-- Active Shift Status Card -->
                                    <Card v-if="getActiveShift(selectedContext.machineId)" class="border-green-500 bg-green-50 dark:bg-green-950/20">
                                        <CardHeader class="pb-3">
                                            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="h-10 w-10 rounded-full bg-green-600 flex items-center justify-center shrink-0">
                                                        <Play class="h-5 w-5 text-white" />
                                                    </div>
                                                    <div>
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <CardTitle class="text-green-700 dark:text-green-400">Shift In Progress <span class="text-green-600/70 font-normal ml-2 text-sm">{{ formatDate(getActiveShift(selectedContext.machineId)!.startedAt) }}</span></CardTitle>
                                                            <Badge variant="outline" class="font-mono text-xs font-bold text-green-700 bg-green-100 border-green-200 animate-pulse">
                                                                {{ currentShiftDuration }}
                                                            </Badge>
                                                        </div>
                                                        <div class="flex items-center gap-2 mt-1">
                                                            <div class="text-sm font-medium">{{ getActiveShift(selectedContext.machineId)?.machineName }}</div>
                                                            <Badge v-if="getActiveShift(selectedContext.machineId)?.productName" variant="outline" class="bg-white/50 border-green-200 text-green-800 text-[10px] h-5">
                                                                {{ getActiveShift(selectedContext.machineId)?.productName }}
                                                            </Badge>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <!-- Permission Warning Banner (Mini) -->
                                                    <div v-if="currentMachinePlantId && !canManage(currentMachinePlantId)" class="flex items-center px-3 py-1 bg-amber-100 text-amber-800 rounded-md text-xs font-medium mr-2 border border-amber-200 w-full sm:w-auto justify-center sm:justify-start">
                                                        <AlertTriangle class="h-3.5 w-3.5 mr-1.5" />
                                                        View Only Mode
                                                    </div>

                                                    <Button size="sm" variant="secondary" 
                                                        class="bg-amber-500 hover:bg-amber-600 text-white border-none disabled:opacity-50 flex-1 sm:flex-none" 
                                                        @click="openDowntimeDialog()"
                                                        :disabled="currentMachinePlantId ? !canManage(currentMachinePlantId) : false">
                                                        <PauseCircle class="h-4 w-4 mr-1" /> Log Downtime
                                                    </Button>
                                                    <Button size="sm" variant="secondary" 
                                                        class="bg-blue-500 hover:bg-blue-600 text-white border-none disabled:opacity-50 flex-1 sm:flex-none" 
                                                        @click="openMaterialLossDialog()"
                                                        :disabled="currentMachinePlantId ? !canManage(currentMachinePlantId) : false">
                                                        <Scale class="h-4 w-4 mr-1" /> Log Loss
                                                    </Button>
                                                    <Button size="sm" variant="secondary" 
                                                        class="bg-purple-500 hover:bg-purple-600 text-white border-none disabled:opacity-50 flex-1 sm:flex-none" 
                                                        @click="isChangeProductDialogOpen = true"
                                                        :disabled="currentMachinePlantId ? !canManage(currentMachinePlantId) : false">
                                                        <Package class="h-4 w-4 mr-1" /> Change Product
                                                    </Button>
                                                    <Button size="sm" variant="destructive" 
                                                        class="flex-1 sm:flex-none"
                                                        @click="openEndShiftDialog(selectedContext.machineId!)"
                                                        :disabled="currentMachinePlantId ? !canManage(currentMachinePlantId) : false">
                                                        <Square class="h-4 w-4 mr-1" /> End Shift
                                                    </Button>
                                                </div>
                                            </div>
                                        </CardHeader>
                                        <CardContent class="pt-0">
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                <div class="flex items-start gap-3 p-3 bg-white dark:bg-gray-900/50 rounded-lg border">
                                                    <User class="h-5 w-5 text-muted-foreground mt-0.5" />
                                                    <div>
                                                        <p class="text-xs text-muted-foreground uppercase tracking-wide">Started By</p>
                                                        <p class="font-semibold">{{ getActiveShift(selectedContext.machineId)?.startedBy.name }}</p>
                                                        <p class="text-xs text-muted-foreground">{{ getActiveShift(selectedContext.machineId)?.startedBy.email }}</p>
                                                    </div>
                                                </div>
                                                <div class="flex items-start gap-3 p-3 bg-white dark:bg-gray-900/50 rounded-lg border">
                                                    <div>
                                                        <p class="text-xs text-muted-foreground uppercase tracking-wide">User Group</p>
                                                        <p class="font-semibold" v-if="getActiveShift(selectedContext.machineId)?.userGroup">
                                                            {{ getActiveShift(selectedContext.machineId)?.userGroup }}
                                                        </p>
                                                        <p class="text-xs text-muted-foreground italic" v-else>No primary group</p>
                                                        <div class="flex flex-wrap gap-1 mt-1" v-if="getActiveShift(selectedContext.machineId)?.startedBy.groups?.length">
                                                            <Badge v-for="group in getActiveShift(selectedContext.machineId)?.startedBy.groups" :key="group" 
                                                                :variant="group === getActiveShift(selectedContext.machineId)?.userGroup ? 'default' : 'secondary'" 
                                                                class="text-xs">
                                                                {{ group }}
                                                            </Badge>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex items-start gap-3 p-3 bg-white dark:bg-gray-900/50 rounded-lg border">
                                                    <Clock class="h-5 w-5 text-muted-foreground mt-0.5" />
                                                    <div>
                                                        <p class="text-xs text-muted-foreground uppercase tracking-wide">Started At</p>
                                                        <p class="font-semibold font-mono">{{ formatTime(getActiveShift(selectedContext.machineId)!.startedAt || new Date()) }}</p>
                                                        <p class="text-xs text-muted-foreground">{{ formatDate(getActiveShift(selectedContext.machineId)!.startedAt || new Date()) }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                    
                                    <!-- Shift Activity Feed - 3 Column Layout -->
                                    <div v-if="getActiveShift(selectedContext.machineId)" class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                                        <!-- Downtime Column -->
                                        <Card class="overflow-hidden">
                                            <CardHeader class="py-2 px-3 border-b bg-amber-50 dark:bg-amber-900/20">
                                                <CardTitle class="text-xs font-semibold flex items-center gap-2 text-amber-700 dark:text-amber-400">
                                                    <PauseCircle class="h-3.5 w-3.5" /> Downtime Events
                                                </CardTitle>
                                            </CardHeader>
                                            <CardContent class="p-0 overflow-y-auto max-h-[200px]">
                                                <div v-if="shiftActivity.filter(a => a.type === 'downtime').length === 0" class="flex flex-col items-center justify-center py-6 text-muted-foreground">
                                                    <PauseCircle class="h-5 w-5 mb-1 opacity-30" />
                                                    <p class="text-xs">No downtime logged</p>
                                                </div>
                                                <div v-else class="divide-y">
                                                    <div v-for="item in shiftActivity.filter(a => a.type === 'downtime')" :key="item.id" class="p-2 hover:bg-amber-50/50 dark:hover:bg-amber-900/10">
                                                        <div class="flex items-start justify-between gap-2">
                                                            <div class="flex-1 min-w-0">
                                                                <p class="text-sm font-medium text-amber-800 dark:text-amber-300 truncate">{{ item.title }}</p>
                                                                <p class="text-xs text-amber-600 dark:text-amber-500 font-mono">{{ item.description }}</p>
                                                            </div>
                                                            <span class="text-[10px] text-muted-foreground">
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
                                            <CardContent class="p-0 overflow-y-auto max-h-[200px]">
                                                <div v-if="shiftActivity.filter(a => a.type === 'loss').length === 0" class="flex flex-col items-center justify-center py-6 text-muted-foreground">
                                                    <Scale class="h-5 w-5 mb-1 opacity-30" />
                                                    <p class="text-xs">No losses logged</p>
                                                </div>
                                                <div v-else class="divide-y">
                                                    <div v-for="item in shiftActivity.filter(a => a.type === 'loss')" :key="item.id" class="p-2 hover:bg-purple-50/50 dark:hover:bg-purple-900/10">
                                                        <div class="flex items-start justify-between gap-2">
                                                            <div class="flex-1 min-w-0">
                                                                <p class="text-sm font-medium text-purple-800 dark:text-purple-300 truncate">{{ item.title }}</p>
                                                                <p class="text-xs text-purple-600 dark:text-purple-500 font-mono">{{ item.description }}</p>
                                                            </div>
                                                            <span class="text-[10px] text-muted-foreground">
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
                                            <CardContent class="p-0 overflow-y-auto max-h-[200px]">
                                                <div v-if="shiftActivity.filter(a => a.type === 'changeover').length === 0" class="flex flex-col items-center justify-center py-6 text-muted-foreground">
                                                    <Package class="h-5 w-5 mb-1 opacity-30" />
                                                    <p class="text-xs">No changeovers</p>
                                                </div>
                                                <div v-else class="divide-y">
                                                    <div v-for="item in shiftActivity.filter(a => a.type === 'changeover')" :key="item.id" class="p-2 hover:bg-blue-50/50 dark:hover:bg-blue-900/10">
                                                        <div class="flex items-start justify-between gap-2">
                                                            <div class="flex-1 min-w-0">
                                                                <p class="text-sm font-medium text-blue-800 dark:text-blue-300 truncate">{{ item.title }}</p>
                                                                <p class="text-xs text-blue-600 dark:text-blue-500">{{ item.description }}</p>
                                                            </div>
                                                            <span class="text-[10px] text-muted-foreground">
                                                                {{ item.timestamp.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>
                                    
                                    <!-- Start Shift Action (Visible when no shift is active) -->
                                    <div v-if="!getActiveShift(selectedContext.machineId)" class="py-6 flex flex-col items-center justify-center p-8 border border-dashed rounded-lg bg-muted/20">
                                        <div class="mb-4 text-center">
                                            <h3 class="text-lg font-bold">No Active Shift <span class="text-muted-foreground font-normal ml-2 text-base">{{ formatDate(new Date()) }}</span></h3>
                                            <p class="text-sm text-muted-foreground">Start a new production shift to begin logging data.</p>
                                        </div>
                                        <Button 
                                            size="lg" 
                                            variant="default" 
                                            class="bg-green-600 hover:bg-green-700 shadow-md"
                                            :disabled="isShiftLoading(selectedContext.machineId) || !canManage(selectedContext.plantId!)"
                                            @click="openStartShiftDialog(selectedContext.machineId!)"
                                        >
                                            <Play class="h-5 w-5 mr-2" /> 
                                            {{ isShiftLoading(selectedContext.machineId) ? 'Starting...' : 'Start Production Shift' }}
                                        </Button>
                                    </div>


                                </TabsContent>

                                <!-- Machine: Products Tab -->
                                <TabsContent v-if="activeTab === 'machine_products' && selectedContext.machineId" value="machine_products" class="flex-1 p-4 h-full overflow-y-auto mt-0">
                                     <div class="flex items-center justify-between mb-4">
                                         <div>
                                            <h3 class="text-lg font-bold">Product Configuration</h3>
                                            <p class="text-sm text-muted-foreground">Ideal cycle times for {{ plants.find((p: any) => p.id === selectedContext.plantId)?.lines.find((l: any) => l.id === selectedContext.lineId)?.machines.find((m: any) => m.id === selectedContext.machineId)?.name }}</p>
                                         </div>
                                         <div class="flex gap-2">
                                            <Button v-if="can('products.create') && canManage(selectedContext.plantId!)" size="sm" variant="outline" @click="openProductDialog(null, selectedContext.machineId)"><Plus class="mr-2 h-4 w-4"/> Create New</Button>
                                            <Button v-if="canManage(selectedContext.plantId!) && can('products.edit')" size="sm" @click="openAssignProductDialog(selectedContext.machineId)">Assign Existing</Button>
                                         </div>
                                    </div>
                                    <Card>
                                        <CardContent class="p-0">
                                            <div class="rounded-md border">
                                                <table class="w-full text-sm text-left">
                                                    <thead class="bg-muted text-muted-foreground">
                                                        <tr>
                                                            <th class="p-3 font-medium">Product</th>
                                                            <th class="p-3 font-medium">SKU</th>
                                                            <th class="p-3 font-medium text-right">Ideal Rate (UPH)</th>
                                                            <th class="p-3 font-medium text-right">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y">
                                                        <tr v-for="config in plants.find((p: any) => p.id === selectedContext.plantId)?.lines.find((l: any) => l.id === selectedContext.lineId)?.machines.find((m: any) => m.id === selectedContext.machineId)?.machine_product_configs || []" :key="config.id">
                                                            <td class="p-3 font-medium">{{ config.product.name }}</td>
                                                            <td class="p-3 text-xs text-muted-foreground">{{ config.product.sku }}</td>
                                                            <td class="p-3 text-right font-mono">{{ config.ideal_rate }} u/h</td>
                                                            <td class="p-3 text-right">
                                                                 <Button v-if="canManage(selectedContext.plantId!) && can('products.edit')" variant="ghost" size="icon" class="h-8 w-8 text-red-500" @click="detachProduct(selectedContext.machineId, config.product.id)"><Trash2 class="h-4 w-4"/></Button>
                                                            </td>
                                                        </tr>
                                                        <tr v-if="!(plants.find((p: any) => p.id === selectedContext.plantId)?.lines.find((l: any) => l.id === selectedContext.lineId)?.machines.find((m: any) => m.id === selectedContext.machineId)?.machine_product_configs?.length)">
                                                             <td colspan="4" class="p-8 text-center text-muted-foreground italic">
                                                                <div class="flex flex-col items-center gap-2">
                                                                    <Package class="h-8 w-8 text-muted-foreground/50" />
                                                                    <span>No products assigned.</span>
                                                                    <span class="text-xs">Machine uses default ideal rate.</span>
                                                                </div>
                                                             </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </TabsContent>

                                <!-- Machine: Health Tab -->
                                <TabsContent v-if="activeTab === 'machine_health' && selectedContext.machineId" value="machine_health" class="flex-1 p-4 h-full overflow-y-auto mt-0">
                                    <MachineHealth :machineId="selectedContext.machineId" />
                                </TabsContent>

                                <!-- Machine: Settings Tab -->
                                <TabsContent v-if="activeTab === 'machine_settings' && selectedContext.machineId" value="machine_settings" class="flex-1 p-4 h-full overflow-y-auto mt-0 space-y-6">
                                     <!-- Machine Specific Settings -->
                                     <div>
                                         <h3 class="text-lg font-bold">General Settings</h3>
                                         <p class="text-sm text-muted-foreground mb-4">Basic properties for {{ plants.find((p: any) => p.id === selectedContext.plantId)?.lines.find((l: any) => l.id === selectedContext.lineId)?.machines.find((m: any) => m.id === selectedContext.machineId)?.name }}</p>

                                         <div class="grid gap-6">
                                             <Card>
                                                 <CardHeader>
                                                     <CardTitle class="text-base">Machine Details</CardTitle>
                                                 </CardHeader>
                                                 <CardContent>
                                                     <div class="grid gap-4">
                                                         <div class="grid gap-2">
                                                             <Label>Machine Name</Label>
                                                             <div class="p-2 border rounded bg-muted/50">{{ plants.find((p: any) => p.id === selectedContext.plantId)?.lines.find((l: any) => l.id === selectedContext.lineId)?.machines.find((m: any) => m.id === selectedContext.machineId)?.name }}</div>
                                                         </div>
                                                         <div class="grid gap-2">
                                                             <Label>Default Ideal Rate</Label>
                                                             <div class="p-2 border rounded bg-muted/50">{{ plants.find((p: any) => p.id === selectedContext.plantId)?.lines.find((l: any) => l.id === selectedContext.lineId)?.machines.find((m: any) => m.id === selectedContext.machineId)?.default_ideal_rate }} u/h</div>
                                                         </div>
                                                          <div class="flex justify-end">
                                                             <Button variant="outline" size="sm" @click="openMachineDialog(selectedContext.lineId, plants.find((p: any) => p.id === selectedContext.plantId)?.lines.find((l: any) => l.id === selectedContext.lineId)?.machines.find((m: any) => m.id === selectedContext.machineId))">Edit Properties</Button>
                                                          </div>
                                                     </div>
                                                 </CardContent>
                                             </Card>
                                         </div>
                                     </div>

                                     <!-- Shift Schedule Configuration (Moved) -->
                                     <div class="border-t pt-6">
                                        <div class="flex items-center justify-between mb-4">
                                            <div>
                                                <h3 class="text-lg font-bold">Shift Schedule Configuration</h3>
                                                <p class="text-sm text-muted-foreground">Manage shifts available for this plant (all machines in plant share shifts).</p>
                                            </div>
                                            <div class="flex gap-2" v-if="canManage(selectedContext.plantId!) && can('shifts.create')">
                                                <Button size="sm" variant="outline" @click="openShiftDialog(selectedContext.plantId)">Manage Plant Shifts</Button>
                                            </div>
                                        </div>
                                        
                                        <Card>
                                            <CardContent class="p-0">
                                                <div class="rounded-md border">
                                                    <table class="w-full text-sm text-left">
                                                        <thead class="bg-muted text-muted-foreground">
                                                            <tr>
                                                                <th class="p-3 font-medium">Name</th>
                                                                <th class="p-3 font-medium">Start Time</th>
                                                                <th class="p-3 font-medium">End Time</th>
                                                                <th class="p-3 font-medium text-right">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y">
                                                            <tr v-for="shift in allShifts" :key="shift.id">
                                                                <td class="p-3 flex items-center gap-2">
                                                                    <CheckCircle class="h-4 w-4 text-green-600" />
                                                                    {{ shift.name }}
                                                                    <Badge variant="outline" class="text-[10px] ml-2">{{ shift.type }}</Badge>
                                                                    <Badge variant="secondary" class="text-[9px] ml-1 text-muted-foreground">{{ shift.plant_name || 'Global' }}</Badge>
                                                                </td>
                                                                <td class="p-3 font-mono">{{ shift.start_time?.substring(0,5) }}</td>
                                                                <td class="p-3 font-mono">{{ shift.end_time?.substring(0,5) }}</td>
                                                                <td class="p-3 text-right">
                                                                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="openShiftDialog(selectedContext.plantId, shift)" v-if="canManage(selectedContext.plantId!) && can('shifts.edit')"><Pencil class="h-4 w-4"/></Button>
                                                                    <Button variant="ghost" size="icon" class="h-8 w-8 text-red-500" @click="deleteShift(shift.id)" v-if="canManage(selectedContext.plantId!) && can('shifts.delete')"><Trash2 class="h-4 w-4"/></Button>
                                                                </td>
                                                            </tr>
                                                            <tr v-if="allShifts.length === 0">
                                                                <td colspan="4" class="p-8 text-center text-muted-foreground italic">
                                                                    <div class="flex flex-col items-center gap-2">
                                                                        <Clock class="h-8 w-8 text-muted-foreground/50" />
                                                                        <span>No shifts defined in the organization.</span>
                                                                        <span class="text-xs">Create a shift to enable production tracking across all plants.</span>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>

                                    <!-- Dreaded Reason Code Configuration (Moved) -->
                                    <div class="border-t pt-6">
                                        <div class="flex items-center justify-between mb-4">
                                            <div>
                                                <h3 class="text-lg font-bold">Downtime Reason Codes</h3>
                                                <p class="text-sm text-muted-foreground">Global reason codes available for all machines.</p>
                                            </div>
                                            <div class="flex gap-2" v-if="canManage(selectedContext.plantId!)">
                                                <Button size="sm" variant="outline" @click="openReasonCodeDialog(null, null)"><Plus class="mr-2 h-4 w-4"/> Create New</Button>
                                            </div>
                                        </div>
                                        <Card>
                                            <CardContent class="p-0">
                                                <div class="rounded-md border">
                                                    <table class="w-full text-sm text-left">
                                                        <thead class="bg-muted text-muted-foreground">
                                                            <tr>
                                                                <th class="p-3 font-medium">Code</th>
                                                                <th class="p-3 font-medium">Description</th>
                                                                <th class="p-3 font-medium">Category</th>
                                                                <th class="p-3 font-medium text-right">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y">
                                                            <tr v-for="rc in props.reasonCodes" :key="rc.id">
                                                                <td class="p-3 font-mono font-bold">{{ rc.code }}</td>
                                                                <td class="p-3">{{ rc.description }}</td>
                                                                <td class="p-3">
                                                                    <Badge variant="outline" class="capitalize" :class="({
                                                                        'planned': 'bg-blue-50 text-blue-700 border-blue-200',
                                                                        'unplanned': 'bg-orange-50 text-orange-700 border-orange-200',
                                                                        'performance': 'bg-purple-50 text-purple-700 border-purple-200',
                                                                        'quality': 'bg-red-50 text-red-700 border-red-200'
                                                                    } as any)[rc.category] || 'bg-gray-100 text-gray-700'">{{ rc.category }}</Badge>
                                                                </td>
                                                                <td class="p-3 text-right">
                                                                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="openReasonCodeDialog(rc)" v-if="canManage(selectedContext.plantId!)"><Pencil class="h-4 w-4"/></Button>
                                                                    <Button variant="ghost" size="icon" class="h-8 w-8 text-red-500" @click="deleteReasonCode(rc.id)" v-if="canManage(selectedContext.plantId!)"><Trash2 class="h-4 w-4"/></Button>
                                                                </td>
                                                            </tr>
                                                            <tr v-if="props.reasonCodes.length === 0">
                                                                <td colspan="4" class="p-8 text-center text-muted-foreground italic">
                                                                    <div class="flex flex-col items-center gap-2">
                                                                        <Square class="h-8 w-8 text-muted-foreground/50" />
                                                                        <span>No global reason codes defined.</span>
                                                                        <span class="text-xs">Create new codes to track downtime.</span>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>

                                    <!-- Material Loss Tracking Section -->
                                    <div class="border-t pt-6" v-if="materialLossCategories && materialLossCategories.length > 0">
                                        <div class="flex items-center justify-between mb-4">
                                            <div>
                                                <h3 class="text-lg font-bold flex items-center gap-2">
                                                    <PackageX class="h-5 w-5" />
                                                    Material Loss Tracking
                                                </h3>
                                                <p class="text-sm text-muted-foreground">Track package waste, spillage, and other material losses during production.</p>
                                            </div>
                                            <Button 
                                                size="sm" 
                                                variant="default" 
                                                @click="isMaterialLossDialogOpen = true"
                                                :disabled="!activeShifts.get(selectedContext.machineId || 0)"
                                                v-if="canManage(selectedContext.plantId!)"
                                            >
                                                <Plus class="mr-2 h-4 w-4"/> Log Material Loss
                                            </Button>
                                        </div>
                                        
                                        <Card v-if="!activeShifts.get(selectedContext.machineId || 0)" class="bg-muted/30">
                                            <CardContent class="p-6 text-center text-muted-foreground">
                                                <PackageX class="h-12 w-12 mx-auto mb-3 opacity-50" />
                                                <p class="font-medium">No Active Shift</p>
                                                <p class="text-sm">Material losses can only be logged during an active production shift.</p>
                                            </CardContent>
                                        </Card>
                                        
                                        <Card v-else>
                                            <CardContent class="p-4">
                                                <div class="grid grid-cols-3 gap-4">
                                                    <div class="text-center p-4 bg-muted/50 rounded-lg">
                                                        <div class="text-2xl font-bold text-orange-600">12</div>
                                                        <div class="text-xs text-muted-foreground mt-1">Losses Today</div>
                                                    </div>
                                                    <div class="text-center p-4 bg-muted/50 rounded-lg">
                                                        <div class="text-2xl font-bold text-blue-600">245 kg</div>
                                                        <div class="text-xs text-muted-foreground mt-1">Total Quantity</div>
                                                    </div>
                                                    <div class="text-center p-4 bg-muted/50 rounded-lg">
                                                        <div class="text-2xl font-bold text-red-600">$1,240</div>
                                                        <div class="text-xs text-muted-foreground mt-1">Estimated Cost</div>
                                                    </div>
                                                </div>
                                                <p class="text-xs text-muted-foreground text-center mt-3">
                                                    <Info class="inline h-3 w-3 mr-1" />
                                                    Material losses marked as "affects OEE" will impact quality score calculations.
                                                </p>
                                            </CardContent>
                                        </Card>
                                    </div>
                                </TabsContent>
                                
                                <TabsContent v-if="!selectedContext.plantId" value="global_products" class="flex-1 p-2 h-full overflow-y-auto mt-0">
                                     <!-- Global Products List -->
                                    <Card>
                                        <CardHeader class="flex flex-row items-center justify-between py-3">
                                            <CardTitle class="text-lg">Global Product Catalog</CardTitle>
                                            <Button v-if="can('products.create')" size="sm" @click="openProductDialog(null)">
                                                <Plus class="mr-2 h-4 w-4"/> Add Product
                                            </Button>
                                        </CardHeader>
                                        <CardContent>
                                            <div class="rounded-md border">
                                                <table class="w-full text-sm text-left">
                                                    <thead class="bg-muted text-muted-foreground">
                                                        <tr>
                                                            <th class="p-3 font-medium">Name</th>
                                                            <th class="p-3 font-medium">SKU</th>
                                                            <th class="p-3 font-medium">Unit</th>
                                                            <th class="p-3 font-medium text-right">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y">
                                                        <tr v-for="product in products" :key="product.id">
                                                            <td class="p-3">{{ product.name }}</td>
                                                            <td class="p-3 font-mono">{{ product.sku }}</td>
                                                            <td class="p-3 text-sm text-muted-foreground">{{ product.unit_of_measure || '-' }}</td>
                                                            <td class="p-3 text-right">
                                                                 <Button v-if="can('products.edit')" variant="ghost" size="icon" class="h-8 w-8" @click="openProductDialog(product)"><Pencil class="h-4 w-4"/></Button>
                                                                 <Button v-if="can('products.delete')" variant="ghost" size="icon" class="h-8 w-8 text-red-500" @click="deleteProduct(product.id)"><Trash2 class="h-4 w-4"/></Button>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                             </div>
                                        </CardContent>
                                     </Card>
                                </TabsContent>

                                <!-- Targets Tab -->
                                <TabsContent v-if="activeTab === 'targets'" value="targets" class="flex-1 p-4 h-full overflow-y-auto mt-0">
                                    <div class="flex items-center justify-between mb-4">
                                        <div>
                                            <h3 class="text-lg font-bold">Production Targets</h3>
                                            <p class="text-sm text-muted-foreground">Manage OEE performance goals for machines and shifts</p>
                                        </div>
                                        <Button v-if="can('targets.create')" size="sm" @click="openTargetDialog()">
                                            <Target class="mr-2 h-4 w-4" /> Set New Target
                                        </Button>
                                    </div>

                                    <Card>
                                        <CardContent class="p-0">
                                            <div class="rounded-md border">
                                                <table class="w-full text-sm text-left">
                                                    <thead class="bg-muted text-muted-foreground">
                                                        <tr>
                                                            <th class="p-3 font-medium">Machine</th>
                                                            <th class="p-3 font-medium">Shift</th>
                                                            <th class="p-3 font-medium text-center">OEE</th>
                                                            <th class="p-3 font-medium text-center">A/P/Q</th>
                                                            <th class="p-3 font-medium">Period</th>
                                                            <th class="p-3 font-medium">Status</th>
                                                            <th class="p-3 font-medium text-right">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y">
                                                        <tr v-if="targets.length === 0">
                                                            <td colspan="7" class="p-8 text-center text-muted-foreground">
                                                                <div class="flex flex-col items-center gap-2">
                                                                    <Target class="h-8 w-8 text-muted-foreground/50" />
                                                                    <span>No targets defined yet</span>
                                                                    <span class="text-xs">Click "Set New Target" to create one</span>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <tr v-for="target in targets" :key="target.id">
                                                            <td class="p-3">
                                                                <div class="flex flex-col">
                                                                    <span class="font-medium text-xs">{{ target.machine_name }}</span>
                                                                    <span class="text-[10px] text-muted-foreground">{{ target.plant_name }} › {{ target.line_name }}</span>
                                                                </div>
                                                            </td>
                                                            <td class="p-3">
                                                                <Badge v-if="target.shift_name !== 'All Shifts'" variant="secondary" class="text-xs">{{ target.shift_name }}</Badge>
                                                                <span v-else class="text-xs text-muted-foreground">All Shifts</span>
                                                            </td>
                                                            <td class="p-3 text-center">
                                                                <span class="font-semibold" :class="target.target_oee >= 90 ? 'text-green-600' : target.target_oee >= 80 ? 'text-yellow-600' : 'text-orange-600'">
                                                                    {{ target.target_oee }}%
                                                                </span>
                                                            </td>
                                                            <td class="p-3 text-center text-xs text-muted-foreground">
                                                                {{ target.target_availability }}/{{ target.target_performance }}/{{ target.target_quality }}
                                                            </td>
                                                            <td class="p-3 text-xs">
                                                                <div class="flex items-center gap-1">
                                                                    <Calendar class="h-3 w-3" />
                                                                    <span>{{ new Date(target.effective_from).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }}</span>
                                                                </div>
                                                                <span class="text-muted-foreground">to {{ target.effective_to ? new Date(target.effective_to).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : 'Ongoing' }}</span>
                                                            </td>
                                                            <td class="p-3">
                                                                <Badge :variant="target.is_active ? 'default' : 'outline'" class="text-xs">{{ target.is_active ? 'Active' : 'Inactive' }}</Badge>
                                                            </td>
                                                            <td class="p-3 text-right">
                                                                <Button v-if="can('targets.edit')" variant="ghost" size="icon" class="h-7 w-7" @click="openTargetDialog(target)"><Pencil class="h-3 w-3"/></Button>
                                                                <Button v-if="can('targets.delete')" variant="ghost" size="icon" class="h-7 w-7 text-red-500" @click="deleteTarget(target.id)"><Trash2 class="h-3 w-3"/></Button>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </TabsContent>

                                <!-- Plant: Categories Tab -->
                                <TabsContent v-if="activeTab === 'plant_categories' && selectedContext.plantId && !selectedContext.lineId && !selectedContext.machineId" value="plant_categories" class="flex-1 p-4 h-full overflow-y-auto mt-0 space-y-6">
                                    <!-- Downtime Types Section -->
                                    <div>
                                        <div class="flex items-center justify-between mb-4">
                                            <div>
                                                <h3 class="text-lg font-bold">Downtime Types</h3>
                                                <p class="text-sm text-muted-foreground">Define types for categorizing downtime (e.g., Planned, Unplanned, Breakdown).</p>
                                            </div>
                                            <Button size="sm" @click="openDowntimeTypeDialog()"><Plus class="mr-2 h-4 w-4"/>Add Type</Button>
                                        </div>
                                        <Card>
                                            <CardContent class="p-0">
                                                <div class="rounded-md border">
                                                    <table class="w-full text-sm text-left">
                                                        <thead class="bg-muted text-muted-foreground">
                                                            <tr>
                                                                <th class="p-3 font-medium w-20">Color</th>
                                                                <th class="p-3 font-medium w-28">Code</th>
                                                                <th class="p-3 font-medium">Name</th>
                                                                <th class="p-3 font-medium w-32 text-center">Affects Availability</th>
                                                                <th class="p-3 font-medium w-20 text-center">Active</th>
                                                                <th class="p-3 font-medium text-right w-24">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y">
                                                            <tr v-for="dt in props.downtimeTypes" :key="dt.id" class="hover:bg-muted/50">
                                                                <td class="p-3">
                                                                    <div class="w-6 h-6 rounded-full border" :style="{ backgroundColor: dt.color || '#6b7280' }"></div>
                                                                </td>
                                                                <td class="p-3 font-mono font-bold">{{ dt.code }}</td>
                                                                <td class="p-3">
                                                                    <div>{{ dt.name }}</div>
                                                                    <div class="text-xs text-muted-foreground" v-if="dt.description">{{ dt.description }}</div>
                                                                </td>
                                                                <td class="p-3 text-center">
                                                                    <Badge v-if="dt.affects_availability" variant="destructive" class="text-xs">Yes</Badge>
                                                                    <span v-else class="text-xs text-muted-foreground">No</span>
                                                                </td>
                                                                <td class="p-3 text-center">
                                                                    <Badge :variant="dt.active ? 'default' : 'outline'" class="text-xs">{{ dt.active ? 'Yes' : 'No' }}</Badge>
                                                                </td>
                                                                <td class="p-3 text-right">
                                                                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="openDowntimeTypeDialog(dt)"><Pencil class="h-4 w-4"/></Button>
                                                                    <Button variant="ghost" size="icon" class="h-8 w-8 text-red-500" @click="deleteDowntimeType(dt.id)"><Trash2 class="h-4 w-4"/></Button>
                                                                </td>
                                                            </tr>
                                                            <tr v-if="!props.downtimeTypes || props.downtimeTypes.length === 0">
                                                                <td colspan="6" class="p-8 text-center text-muted-foreground italic">
                                                                    <div class="flex flex-col items-center gap-2">
                                                                        <Clock class="h-8 w-8 text-muted-foreground/50" />
                                                                        <span>No downtime types defined.</span>
                                                                        <span class="text-xs">Create types like "Planned", "Unplanned", "Breakdown" to organize reason codes.</span>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>

                                    <!-- Downtime Categories Section -->
                                    <div class="border-t pt-6">
                                        <div class="flex items-center justify-between mb-4">
                                            <div>
                                                <h3 class="text-lg font-bold">Downtime Categories (Reason Codes)</h3>
                                                <p class="text-sm text-muted-foreground">Define specific reason codes for tracking production stoppages.</p>
                                            </div>
                                            <Button size="sm" @click="openReasonCodeDialog()"><Plus class="mr-2 h-4 w-4"/>Add Category</Button>
                                        </div>
                                        <Card>
                                            <CardContent class="p-0">
                                                <div class="rounded-md border">
                                                    <table class="w-full text-sm text-left">
                                                        <thead class="bg-muted text-muted-foreground">
                                                            <tr>
                                                                <th class="p-3 font-medium w-32">Code</th>
                                                                <th class="p-3 font-medium">Description</th>
                                                                <th class="p-3 font-medium w-32">Type</th>
                                                                <th class="p-3 font-medium text-right w-24">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y">
                                                            <tr v-for="rc in props.reasonCodes" :key="rc.id" class="hover:bg-muted/50">
                                                                <td class="p-3 font-mono font-bold">{{ rc.code }}</td>
                                                                <td class="p-3">{{ rc.description }}</td>
                                                                <td class="p-3">
                                                                    <Badge variant="outline" class="capitalize" :style="rc.downtime_type_id && props.downtimeTypes?.find(dt => dt.id === rc.downtime_type_id) ? {
                                                                        backgroundColor: props.downtimeTypes.find(dt => dt.id === rc.downtime_type_id)?.color + '20',
                                                                        color: props.downtimeTypes.find(dt => dt.id === rc.downtime_type_id)?.color,
                                                                        borderColor: props.downtimeTypes.find(dt => dt.id === rc.downtime_type_id)?.color + '40'
                                                                    } : {}" :class="!rc.downtime_type_id ? ({
                                                                        'planned': 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950 dark:text-blue-300',
                                                                        'unplanned': 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-950 dark:text-orange-300',
                                                                        'performance': 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950 dark:text-purple-300',
                                                                        'quality': 'bg-red-50 text-red-700 border-red-200 dark:bg-red-950 dark:text-red-300'
                                                                    } as any)[rc.category] || 'bg-gray-100 text-gray-700' : ''">
                                                                        {{ 
                                                                            rc.downtime_type_id && props.downtimeTypes?.find(dt => dt.id === rc.downtime_type_id) 
                                                                            ? props.downtimeTypes.find(dt => dt.id === rc.downtime_type_id)?.name 
                                                                            : rc.category 
                                                                        }}
                                                                    </Badge>
                                                                </td>
                                                                <td class="p-3 text-right">
                                                                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="openReasonCodeDialog(rc)"><Pencil class="h-4 w-4"/></Button>
                                                                    <Button variant="ghost" size="icon" class="h-8 w-8 text-red-500" @click="deleteReasonCode(rc.id)"><Trash2 class="h-4 w-4"/></Button>
                                                                </td>
                                                            </tr>
                                                            <tr v-if="!props.reasonCodes || props.reasonCodes.length === 0">
                                                                <td colspan="4" class="p-8 text-center text-muted-foreground italic">
                                                                    <div class="flex flex-col items-center gap-2">
                                                                        <Clock class="h-8 w-8 text-muted-foreground/50" />
                                                                        <span>No downtime categories defined.</span>
                                                                        <span class="text-xs">Create categories to track planned and unplanned stoppages.</span>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>

                                    <!-- Loss Types Section -->
                                    <div class="border-t pt-6">
                                        <div class="flex items-center justify-between mb-4">
                                            <div>
                                                <h3 class="text-lg font-bold">Loss Types</h3>
                                                <p class="text-sm text-muted-foreground">Define types for categorizing material losses (e.g., Raw Material, Packaging).</p>
                                            </div>
                                            <Button size="sm" @click="openLossTypeDialog()"><Plus class="mr-2 h-4 w-4"/>Add Type</Button>
                                        </div>
                                        <Card>
                                            <CardContent class="p-0">
                                                <div class="rounded-md border">
                                                    <table class="w-full text-sm text-left">
                                                        <thead class="bg-muted text-muted-foreground">
                                                            <tr>
                                                                <th class="p-3 font-medium w-20">Color</th>
                                                                <th class="p-3 font-medium w-28">Code</th>
                                                                <th class="p-3 font-medium">Name</th>
                                                                <th class="p-3 font-medium w-24 text-center">Affects OEE</th>
                                                                <th class="p-3 font-medium w-20 text-center">Active</th>
                                                                <th class="p-3 font-medium text-right w-24">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y">
                                                            <tr v-for="lt in props.lossTypes" :key="lt.id" class="hover:bg-muted/50">
                                                                <td class="p-3">
                                                                    <div class="w-6 h-6 rounded-full border" :style="{ backgroundColor: lt.color || '#ef4444' }"></div>
                                                                </td>
                                                                <td class="p-3 font-mono font-bold">{{ lt.code }}</td>
                                                                <td class="p-3">
                                                                    <div>{{ lt.name }}</div>
                                                                    <div class="text-xs text-muted-foreground" v-if="lt.description">{{ lt.description }}</div>
                                                                </td>
                                                                <td class="p-3 text-center">
                                                                    <Badge v-if="lt.affects_oee" variant="destructive" class="text-xs">Yes</Badge>
                                                                    <span v-else class="text-xs text-muted-foreground">No</span>
                                                                </td>
                                                                <td class="p-3 text-center">
                                                                    <Badge :variant="lt.active ? 'default' : 'outline'" class="text-xs">{{ lt.active ? 'Yes' : 'No' }}</Badge>
                                                                </td>
                                                                <td class="p-3 text-right">
                                                                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="openLossTypeDialog(lt)"><Pencil class="h-4 w-4"/></Button>
                                                                    <Button variant="ghost" size="icon" class="h-8 w-8 text-red-500" @click="deleteLossType(lt.id)"><Trash2 class="h-4 w-4"/></Button>
                                                                </td>
                                                            </tr>
                                                            <tr v-if="!props.lossTypes || props.lossTypes.length === 0">
                                                                <td colspan="6" class="p-8 text-center text-muted-foreground italic">
                                                                    <div class="flex flex-col items-center gap-2">
                                                                        <PackageX class="h-8 w-8 text-muted-foreground/50" />
                                                                        <span>No loss types defined.</span>
                                                                        <span class="text-xs">Create types like "Raw Material", "Packaging", "Other" to organize loss categories.</span>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>

                                    <!-- Loss Categories Section -->
                                    <div class="border-t pt-6">
                                        <div class="flex items-center justify-between mb-4">
                                            <div>
                                                <h3 class="text-lg font-bold">Loss Categories</h3>
                                                <p class="text-sm text-muted-foreground">Define specific categories for tracking material losses.</p>
                                            </div>
                                            <Button size="sm" @click="openLossCategoryDialog()"><Plus class="mr-2 h-4 w-4"/>Add Category</Button>
                                        </div>
                                        <Card>
                                            <CardContent class="p-0">
                                                <div class="rounded-md border">
                                                    <table class="w-full text-sm text-left">
                                                        <thead class="bg-muted text-muted-foreground">
                                                            <tr>
                                                                <th class="p-3 font-medium w-20">Color</th>
                                                                <th class="p-3 font-medium w-24">Code</th>
                                                                <th class="p-3 font-medium">Name</th>
                                                                <th class="p-3 font-medium w-28">Type</th>
                                                                <th class="p-3 font-medium w-24 text-center">Affects OEE</th>
                                                                <th class="p-3 font-medium w-20 text-center">Active</th>
                                                                <th class="p-3 font-medium text-right w-24">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y">
                                                            <tr v-for="cat in props.materialLossCategories" :key="cat.id" class="hover:bg-muted/50">
                                                                <td class="p-3">
                                                                    <div class="w-6 h-6 rounded-full border" :style="{ backgroundColor: cat.color || '#ef4444' }"></div>
                                                                </td>
                                                                <td class="p-3 font-mono font-bold">{{ cat.code }}</td>
                                                                <td class="p-3">
                                                                    <div>{{ cat.name }}</div>
                                                                    <div class="text-xs text-muted-foreground" v-if="cat.description">{{ cat.description }}</div>
                                                                </td>
                                                                <td class="p-3">
                                                                    <Badge variant="outline" class="capitalize text-xs" :style="cat.loss_type_id && props.lossTypes?.find(lt => lt.id === cat.loss_type_id) ? {
                                                                        backgroundColor: props.lossTypes.find(lt => lt.id === cat.loss_type_id)?.color + '20',
                                                                        color: props.lossTypes.find(lt => lt.id === cat.loss_type_id)?.color,
                                                                        borderColor: props.lossTypes.find(lt => lt.id === cat.loss_type_id)?.color + '40'
                                                                    } : {}" :class="!cat.loss_type_id ? ({
                                                                        'raw_material': 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950 dark:text-amber-300',
                                                                        'packaging': 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950 dark:text-blue-300',
                                                                        'other': 'bg-gray-50 text-gray-700 border-gray-200 dark:bg-gray-950 dark:text-gray-300'
                                                                    } as any)[cat.loss_type] || '' : ''">
                                                                        {{ 
                                                                            cat.loss_type_id && props.lossTypes?.find(lt => lt.id === cat.loss_type_id) 
                                                                            ? props.lossTypes.find(lt => lt.id === cat.loss_type_id)?.name 
                                                                            : (cat.loss_type || 'other').replace('_', ' ') 
                                                                        }}
                                                                    </Badge>
                                                                </td>
                                                                <td class="p-3 text-center">
                                                                    <Badge v-if="cat.affects_oee" variant="destructive" class="text-xs">Yes</Badge>
                                                                    <span v-else class="text-xs text-muted-foreground">No</span>
                                                                </td>
                                                                <td class="p-3 text-center">
                                                                    <Badge :variant="cat.active ? 'default' : 'outline'" class="text-xs">{{ cat.active ? 'Yes' : 'No' }}</Badge>
                                                                </td>
                                                                <td class="p-3 text-right">
                                                                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="openLossCategoryDialog(cat)"><Pencil class="h-4 w-4"/></Button>
                                                                    <Button variant="ghost" size="icon" class="h-8 w-8 text-red-500" @click="deleteLossCategory(cat.id)"><Trash2 class="h-4 w-4"/></Button>
                                                                </td>
                                                            </tr>
                                                            <tr v-if="!props.materialLossCategories || props.materialLossCategories.length === 0">
                                                                <td colspan="7" class="p-8 text-center text-muted-foreground italic">
                                                                    <div class="flex flex-col items-center gap-2">
                                                                        <PackageX class="h-8 w-8 text-muted-foreground/50" />
                                                                        <span>No loss categories defined.</span>
                                                                        <span class="text-xs">Create categories to track material wastage.</span>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>
                                </TabsContent>



                            </Tabs>
                        </div>
                    </div>
                </div>

            <!-- dialogs -->
            <!-- Plant Dialog -->
            <Dialog v-model:open="isPlantDialogOpen">
                <DialogContent class="sm:max-w-[500px] flex flex-col max-h-[90vh]">
                    <DialogHeader><DialogTitle>{{ plantForm.id ? 'Edit' : 'Add' }} Plant</DialogTitle></DialogHeader>
                    <div class="space-y-4 py-4 flex-1 overflow-y-auto min-h-0">
                        <div class="space-y-2"><Label>Name</Label><Input v-model="plantForm.name" /></div>
                        <div class="space-y-2"><Label>Location</Label><Input v-model="plantForm.location" /></div>
                    </div>
                    <DialogFooter><Button @click="submitPlant">Save</Button></DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Line Dialog -->
             <Dialog v-model:open="isLineDialogOpen">
                <DialogContent class="sm:max-w-[500px] flex flex-col max-h-[90vh]">
                    <DialogHeader><DialogTitle>{{ lineForm.id ? 'Edit' : 'Add' }} Line</DialogTitle></DialogHeader>
                    <div class="space-y-4 py-4 flex-1 overflow-y-auto min-h-0">
                        <div class="space-y-2"><Label>Name</Label><Input v-model="lineForm.name" /></div>
                    </div>
                    <DialogFooter><Button @click="submitLine">Save</Button></DialogFooter>
                </DialogContent>
            </Dialog>

             <!-- Machine Dialog -->
             <Dialog v-model:open="isMachineDialogOpen">
                <DialogContent class="sm:max-w-[500px] flex flex-col max-h-[90vh]">
                    <DialogHeader><DialogTitle>{{ machineForm.id ? 'Edit' : 'Add' }} Machine</DialogTitle></DialogHeader>
                    <div class="space-y-4 py-4 flex-1 overflow-y-auto min-h-0">
                        <div class="space-y-2"><Label>Name</Label><Input v-model="machineForm.name" /></div>
                        <div class="space-y-2"><Label>Ideal Rate (Units/Hour)</Label><Input type="number" step="0.0001" v-model="machineForm.default_ideal_rate" /></div>
                    </div>
                    <DialogFooter><Button @click="submitMachine">Save</Button></DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Product Dialog -->
             <Dialog v-model:open="isProductDialogOpen">
                <DialogContent class="sm:max-w-3xl max-h-[90vh] flex flex-col">
                    <DialogHeader><DialogTitle>{{ productForm.id ? 'Edit' : 'Add' }} Product</DialogTitle></DialogHeader>
                    <div class="space-y-4 py-4 flex-1 overflow-y-auto min-h-0">
                        <!-- Basic Info in 2 columns -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2"><Label>Name</Label><Input v-model="productForm.name" /></div>
                            <div class="space-y-2"><Label>SKU <span class="text-muted-foreground font-normal">(optional)</span></Label><Input v-model="productForm.sku" /></div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label>Unit of Measure</Label>
                                <select v-model="productForm.unit_of_measure" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                    <option value="">Select unit...</option>
                                    <option value="kg">Kilogram (kg)</option>
                                    <option value="g">Gram (g)</option>
                                    <option value="l">Liter (l)</option>
                                    <option value="ml">Milliliter (ml)</option>
                                    <option value="pcs">Pieces (pcs)</option>
                                    <option value="units">Units</option>
                                    <option value="boxes">Boxes</option>
                                    <option value="bags">Bags</option>
                                    <option value="bottles">Bottles</option>
                                    <option value="cans">Cans</option>
                                    <option value="cartons">Cartons</option>
                                    <option value="packets">Packets</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label>Reference Weight (per unit)</Label>
                                <Input type="number" step="0.0001" :model-value="productForm.reference_weight ?? ''" @update:model-value="(val) => productForm.reference_weight = val === '' ? null : Number(val)" placeholder="e.g. 0.5" />
                                <p class="text-[10px] text-muted-foreground">Used to convert waste quantity into equivalent product units.</p>
                            </div>
                        </div>
                        
                        <!-- Material Loss Conversion Configuration -->
                        <div class="border-t pt-4 mt-4">
                            <h4 class="text-sm font-semibold mb-3 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                Material Loss Conversion
                            </h4>
                            <p class="text-xs text-muted-foreground mb-3">Configure how material losses convert to finished product units for accurate OEE tracking.</p>
                            
                            <div class="grid gap-3">
                                <div class="space-y-2">
                                    <Label>Finished Unit Type</Label>
                                    <select v-model="productForm.finished_unit" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                        <option value="">Not configured</option>
                                        <option value="bottles">Bottles</option>
                                        <option value="cans">Cans</option>
                                        <option value="boxes">Boxes</option>
                                        <option value="cartons">Cartons</option>
                                        <option value="packets">Packets</option>
                                        <option value="sachets">Sachets</option>
                                        <option value="bags">Bags</option>
                                        <option value="pieces">Pieces</option>
                                        <option value="tablets">Tablets</option>
                                        <option value="capsules">Capsules</option>
                                        <option value="vials">Vials</option>
                                        <option value="units">Units</option>
                                    </select>
                                    <p class="text-[10px] text-muted-foreground">The type of finished product you count (e.g., bottles, tablets)</p>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="space-y-2">
                                        <Label>Fill Volume/Weight</Label>
                                        <Input 
                                            type="number" 
                                            step="0.0001" 
                                            :model-value="productForm.fill_volume ?? ''" 
                                            @update:model-value="(val) => productForm.fill_volume = val === '' ? null : Number(val)" 
                                            placeholder="e.g. 500" 
                                        />
                                        <p class="text-[10px] text-muted-foreground">Amount per unit</p>
                                    </div>
                                    
                                    <div class="space-y-2">
                                        <Label>Fill Unit</Label>
                                        <select v-model="productForm.fill_volume_unit" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                            <option value="">Select unit...</option>
                                            <option value="ml">Milliliters (ml)</option>
                                            <option value="liters">Liters (L)</option>
                                            <option value="grams">Grams (g)</option>
                                            <option value="kg">Kilograms (kg)</option>
                                            <option value="mg">Milligrams (mg)</option>
                                            <option value="oz">Ounces (oz)</option>
                                            <option value="pieces">Pieces</option>
                                        </select>
                                        <p class="text-[10px] text-muted-foreground">Unit of fill amount</p>
                                    </div>
                                </div>
                                
                                <!-- Example Preview -->
                                <div v-if="productForm.finished_unit && productForm.fill_volume && productForm.fill_volume_unit" class="p-3 bg-blue-50 dark:bg-blue-950 rounded-md border border-blue-200 dark:border-blue-800">
                                    <p class="text-xs font-medium text-blue-900 dark:text-blue-100 mb-1">Conversion Example:</p>
                                    <p class="text-xs text-blue-700 dark:text-blue-300">
                                        100 {{ productForm.fill_volume_unit }} of raw material = 
                                        <span class="font-bold">{{ (100 / (productForm.fill_volume || 1)).toFixed(2) }} {{ productForm.finished_unit }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <DialogFooter><Button @click="submitProduct">Save</Button></DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Shift Dialog (New) -->
            <Dialog v-model:open="isShiftDialogOpen">
                 <DialogContent class="sm:max-w-[425px] flex flex-col max-h-[90vh]">
                    <DialogHeader>
                        <DialogTitle>{{ shiftForm.id ? 'Edit Shift' : 'Add Shift' }}</DialogTitle>
                        <DialogDescription>Define shift schedule for the plant.</DialogDescription>
                    </DialogHeader>
                     <div class="grid gap-4 py-4 flex-1 overflow-y-auto min-h-0">
                        <div class="grid gap-2">
                            <Label for="shiftName">Shift Name</Label>
                            <Input id="shiftName" v-model="shiftForm.name" placeholder="e.g. Morning Shift" />
                        </div>
                        <div class="grid gap-2">
                             <Label for="shiftType">Shift Type</Label>
                             <select id="shiftType" v-model="shiftForm.type" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                 <option value="day">Day Shift</option>
                                 <option value="night">Night Shift</option>
                             </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="startTime">Start Time</Label>
                                <Input id="startTime" type="time" v-model="shiftForm.start_time" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="endTime">End Time</Label>
                                <Input id="endTime" type="time" v-model="shiftForm.end_time" />
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button @click="submitShift">Save Shift</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Assign Product Dialog (New) -->
            <Dialog v-model:open="isAssignProductDialogOpen">
                <DialogContent class="sm:max-w-[500px] flex flex-col max-h-[90vh]">
                    <DialogHeader>
                        <DialogTitle>Assign Product to Machine</DialogTitle>
                        <DialogDescription>Configure specific cycle time for this product.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 py-4 flex-1 overflow-y-auto min-h-0">
                        <div class="grid gap-2">
                             <Label for="prodSelect">Product</Label>
                             <select id="prodSelect" v-model="assignProductForm.product_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                 <option value="" disabled>Select a product...</option>
                                 <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}{{ p.sku ? ` (${p.sku})` : '' }}</option>
                             </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="idealTime">Ideal Rate (Units/Hour)</Label>
                            <Input id="idealTime" type="number" step="0.0001" v-model="assignProductForm.ideal_rate" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button @click="submitAssignProduct">Assign</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Assign Reason Dialog -->
            <Dialog :open="isAssignReasonDialogOpen" @update:open="isAssignReasonDialogOpen = $event">
                <DialogContent class="sm:max-w-[425px] flex flex-col max-h-[90vh]">
                    <DialogHeader>
                        <DialogTitle>Assign Reason Code</DialogTitle>
                        <DialogDescription>Select an existing reason code to enable for this machine.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 py-4 flex-1 overflow-y-auto min-h-0">
                        <div class="grid gap-2">
                            <Label>Reason Code</Label>
                            <Select v-model="assignReasonForm.reason_code_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a reason code" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="rc in reasonCodes"
                                        :key="rc.id"
                                        :value="String(rc.id)"
                                    >
                                        {{ rc.code }} - {{ rc.description }}{{ rc.category ? ` (${rc.category})` : '' }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" @click="isAssignReasonDialogOpen = false">Cancel</Button>
                        <Button @click="submitAssignReason" :disabled="assignReasonForm.processing">Assign</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Assign Shift Dialog (New) -->
            <Dialog v-model:open="isAttachShiftDialogOpen">
                <DialogContent class="sm:max-w-[500px] flex flex-col max-h-[90vh]">
                    <DialogHeader>
                         <DialogTitle>Assign Shift to Machine</DialogTitle>
                         <DialogDescription>Select a plant shift to assign to this machine.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 py-4 flex-1 overflow-y-auto min-h-0">
                        <div class="grid gap-2">
                             <Label for="shiftSelect">Shift</Label>
                             <select id="shiftSelect" v-model="attachShiftForm.shift_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                 <option value="" disabled>Select a shift...</option>
                                 <option v-for="s in plants.find(p => p.id === selectedContext.plantId)?.shifts || []" :key="s.id" :value="s.id">{{ s.name }} ({{ s.start_time.substring(0,5) }} - {{ s.end_time.substring(0,5) }})</option>
                             </select>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button @click="submitAttachShift">Assign</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

             <!-- Reason Code Dialog -->
             <Dialog v-model:open="isReasonCodeDialogOpen">
                <DialogContent class="sm:max-w-[500px] flex flex-col max-h-[90vh]">
                    <DialogHeader><DialogTitle>{{ reasonCodeForm.id ? 'Edit' : 'Add' }} Reason Code</DialogTitle></DialogHeader>
                    <div class="grid gap-4 py-4 flex-1 overflow-y-auto min-h-0">
                        <div class="grid gap-2"><Label>Code</Label><Input v-model="reasonCodeForm.code" placeholder="e.g. JAM" /></div>
                        <div class="grid gap-2"><Label>Description</Label><Input v-model="reasonCodeForm.description" placeholder="e.g. Paper Jam" /></div>
                        <div class="grid gap-2">
                            <Label>Category</Label>
                            <select v-model="reasonCodeForm.downtime_type_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                <option :value="null" disabled>Select a type...</option>
                                <option v-for="type in props.downtimeTypes" :key="type.id" :value="type.id">
                                    {{ type.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <DialogFooter class="flex justify-between sm:justify-between">
                        <Button v-if="reasonCodeForm.id" variant="destructive" type="button" @click="deleteReasonCode(reasonCodeForm.id)">Delete Global Reason</Button>
                        <div v-else></div>
                        <Button @click="submitReasonCode">Save</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Loss Category Dialog -->
            <Dialog v-model:open="isLossCategoryDialogOpen">
                <DialogContent class="sm:max-w-md flex flex-col max-h-[90vh]">
                    <DialogHeader>
                        <DialogTitle>{{ lossCategoryForm.id ? 'Edit' : 'Add' }} Loss Category</DialogTitle>
                        <DialogDescription>Define a category for tracking material losses.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 py-4 flex-1 overflow-y-auto min-h-0">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label>Code</Label>
                                <Input v-model="lossCategoryForm.code" placeholder="e.g. SPILL" maxlength="20" />
                            </div>
                            <div class="grid gap-2">
                                <Label>Name</Label>
                                <Input v-model="lossCategoryForm.name" placeholder="e.g. Spillage" />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label>Description</Label>
                            <Input v-model="lossCategoryForm.description" placeholder="Optional description..." />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label>Loss Type</Label>
                                <select v-model="lossCategoryForm.loss_type_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                    <option :value="null" disabled>Select a type...</option>
                                    <option v-for="type in props.lossTypes" :key="type.id" :value="type.id">
                                        {{ type.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="grid gap-2">
                                <Label>Color</Label>
                                <div class="flex items-center gap-2">
                                    <input type="color" v-model="lossCategoryForm.color" class="w-10 h-10 rounded border cursor-pointer" />
                                    <Input v-model="lossCategoryForm.color" class="flex-1" maxlength="7" />
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" id="affects_oee" v-model="lossCategoryForm.affects_oee" class="h-4 w-4 rounded border" />
                                <Label for="affects_oee" class="font-normal cursor-pointer">Affects OEE (Quality)</Label>
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="checkbox" id="active" v-model="lossCategoryForm.active" class="h-4 w-4 rounded border" />
                                <Label for="active" class="font-normal cursor-pointer">Active</Label>
                            </div>
                        </div>
                    </div>
                    <DialogFooter class="flex justify-between sm:justify-between">
                        <Button v-if="lossCategoryForm.id" variant="destructive" type="button" @click="deleteLossCategory(lossCategoryForm.id)">Delete</Button>
                        <div v-else></div>
                        <Button @click="submitLossCategory">Save</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Loss Type Dialog -->
            <Dialog v-model:open="isLossTypeDialogOpen">
                <DialogContent class="sm:max-w-md flex flex-col max-h-[90vh]">
                    <DialogHeader>
                        <DialogTitle>{{ lossTypeForm.id ? 'Edit' : 'Add' }} Loss Type</DialogTitle>
                        <DialogDescription>Define a type for categorizing material losses.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 py-4 flex-1 overflow-y-auto min-h-0">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label>Code</Label>
                                <Input v-model="lossTypeForm.code" placeholder="e.g. raw_material" maxlength="50" />
                            </div>
                            <div class="grid gap-2">
                                <Label>Name</Label>
                                <Input v-model="lossTypeForm.name" placeholder="e.g. Raw Material" />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label>Description</Label>
                            <Input v-model="lossTypeForm.description" placeholder="Optional description..." />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label>Color</Label>
                                <div class="flex items-center gap-2">
                                    <input type="color" v-model="lossTypeForm.color" class="w-10 h-10 rounded border cursor-pointer" />
                                    <Input v-model="lossTypeForm.color" class="flex-1" maxlength="7" />
                                </div>
                            </div>
                            <div class="grid gap-2">
                                <Label>Sort Order</Label>
                                <Input type="number" v-model="lossTypeForm.sort_order" min="0" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" id="lt_affects_oee" v-model="lossTypeForm.affects_oee" class="h-4 w-4 rounded border" />
                                <Label for="lt_affects_oee" class="font-normal cursor-pointer">Affects OEE</Label>
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="checkbox" id="lt_active" v-model="lossTypeForm.active" class="h-4 w-4 rounded border" />
                                <Label for="lt_active" class="font-normal cursor-pointer">Active</Label>
                            </div>
                        </div>
                    </div>
                    <DialogFooter class="flex justify-between sm:justify-between">
                        <Button v-if="lossTypeForm.id" variant="destructive" type="button" @click="deleteLossType(lossTypeForm.id)">Delete</Button>
                        <div v-else></div>
                        <Button @click="submitLossType">Save</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Downtime Type Dialog -->
            <Dialog v-model:open="isDowntimeTypeDialogOpen">
                <DialogContent class="sm:max-w-md flex flex-col max-h-[90vh]">
                    <DialogHeader>
                        <DialogTitle>{{ downtimeTypeForm.id ? 'Edit' : 'Add' }} Downtime Type</DialogTitle>
                        <DialogDescription>Define a type for categorizing downtime events.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 py-4 flex-1 overflow-y-auto min-h-0">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label>Code</Label>
                                <Input v-model="downtimeTypeForm.code" placeholder="e.g. planned" maxlength="50" />
                            </div>
                            <div class="grid gap-2">
                                <Label>Name</Label>
                                <Input v-model="downtimeTypeForm.name" placeholder="e.g. Planned" />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label>Description</Label>
                            <Input v-model="downtimeTypeForm.description" placeholder="Optional description..." />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label>Color</Label>
                                <div class="flex items-center gap-2">
                                    <input type="color" v-model="downtimeTypeForm.color" class="w-10 h-10 rounded border cursor-pointer" />
                                    <Input v-model="downtimeTypeForm.color" class="flex-1" maxlength="7" />
                                </div>
                            </div>
                            <div class="grid gap-2">
                                <Label>Sort Order</Label>
                                <Input type="number" v-model="downtimeTypeForm.sort_order" min="0" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" id="affects_availability" v-model="downtimeTypeForm.affects_availability" class="h-4 w-4 rounded border" />
                                <Label for="affects_availability" class="font-normal cursor-pointer">Affects Availability</Label>
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="checkbox" id="dt_active" v-model="downtimeTypeForm.active" class="h-4 w-4 rounded border" />
                                <Label for="dt_active" class="font-normal cursor-pointer">Active</Label>
                            </div>
                        </div>
                    </div>
                    <DialogFooter class="flex justify-between sm:justify-between">
                        <Button v-if="downtimeTypeForm.id" variant="destructive" type="button" @click="deleteDowntimeType(downtimeTypeForm.id)">Delete</Button>
                        <div v-else></div>
                        <Button @click="submitDowntimeType">Save</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Log Downtime Dialog -->
            <Dialog v-model:open="isDowntimeDialogOpen">
                <DialogContent class="max-w-xl flex flex-col max-h-[90vh]">
                    <DialogHeader>
                        <DialogTitle>Log Downtime Event</DialogTitle>
                        <DialogDescription>Record stoppages for the current active shift. You can add multiple events.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 py-4 flex-1 overflow-y-auto min-h-0">
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
                                            <Badge variant="outline">{{ reasonCodes.find(r => r.id === entry.reason_code_id)?.code }}</Badge>
                                            <span class="text-xs text-muted-foreground truncate max-w-[200px]">{{ entry.comment }}</span>
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
                                <Label>Reason Code</Label>
                                <select v-model="downtimeForm.reason_code_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                    <option value="" disabled>Select Reason...</option>
                                    <option v-for="rc in reasonCodes" :key="rc.id" :value="rc.id">
                                        {{ rc.code }} - {{ rc.description }}
                                    </option>
                                </select>
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
                        <Button @click="submitDowntime" class="w-full sm:w-auto">
                            {{ downtimeEntries.length > 0 ? `Submit ${downtimeEntries.length + (downtimeForm.reason_code_id ? 1 : 0)} Events` : 'Submit Log' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- End Shift Dialog -->
            <Dialog v-model:open="isEndShiftDialogOpen">
                <DialogContent class="sm:max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>End Production Shift</DialogTitle>
                        <DialogDescription>Enter the final production counts for this shift. Both good count and reject count are required.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 py-4">
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
                                    <Select :model-value="endShiftForm.early_exit_reason_id" @update:model-value="(val: any) => endShiftForm.early_exit_reason_id = String(val || '')">
                                    <SelectTrigger class="w-full bg-white dark:bg-slate-900 mb-2">
                                        <SelectValue placeholder="Select Downtime Reason" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="code in props.reasonCodes" :key="code.id" :value="String(code.id)">
                                            {{ code.code }} - {{ code.description }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div v-if="hasChangeovers" class="grid gap-4">
                            <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-md text-sm text-blue-700 dark:text-blue-300 mb-2">
                                Changeovers detected. Please enter production counts for each product run.
                            </div>
                            

                            <div class="max-h-[45vh] overflow-y-auto pr-2 space-y-3">
                                <div v-for="(run, index) in productRuns" :key="index" class="border rounded-md p-3 space-y-3">
                                    <div class="font-medium flex justify-between items-center text-sm">
                                        <div class="flex items-center gap-2">
                                            <Badge variant="outline">{{ run.product_name }}</Badge>
                                            <span class="text-muted-foreground text-xs">
                                                ({{ Math.round(run.duration_minutes) }} min)
                                            </span>
                                        </div>
                                        <div class="text-xs text-muted-foreground" v-if="run.start_time && run.end_time">
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
                                                class="h-8"
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
                                                class="h-8"
                                            />
                                        </div>
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

                        <div v-else class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label class="flex items-center gap-1">
                                    Actual Output (Good)
                                    <span class="text-red-500">*</span>
                                </Label>
                                <Input 
                                    type="number" 
                                    min="0" 
                                    :model-value="endShiftForm.good_count ?? ''"
                                    @update:model-value="(val) => { endShiftForm.good_count = val === '' || val === null ? null : Number(val); }"
                                    required
                                    placeholder="Enter good count"
                                    class="border-2"
                                    :class="!isEndShiftFormValid ? 'border-red-300' : ''"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label class="flex items-center gap-1">
                                    Rejects (Scrap)
                                    <span class="text-red-500">*</span>
                                </Label>
                                <Input 
                                    type="number" 
                                    min="0" 
                                    :model-value="endShiftForm.reject_count ?? ''"
                                    @update:model-value="(val) => { endShiftForm.reject_count = val === '' || val === null ? null : Number(val); }"
                                    required
                                    placeholder="Enter reject count"
                                    class="border-2"
                                    :class="!isEndShiftFormValid ? 'border-red-300' : ''"
                                />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label>Notes (Optional)</Label>
                            <Input v-model="endShiftForm.comment" placeholder="Shift summary..." />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" @click="isEndShiftDialogOpen = false">Cancel</Button>
                        <Button 
                            variant="destructive" 
                            @click="confirmEndShift"
                            :disabled="!isEndShiftFormValid"
                        >
                            End Shift
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Start Shift Dialog (New) -->
            <Dialog v-model:open="isStartShiftDialogOpen">
                <DialogContent class="sm:max-w-[500px] flex flex-col max-h-[90vh]">
                    <DialogHeader>
                        <DialogTitle>Start Production Shift</DialogTitle>
                        <DialogDescription>Select the product to run for this shift.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 py-4 flex-1 overflow-y-auto min-h-0">
                        <div class="grid gap-2">
                            <Label>Operator</Label>
                            <select v-model="startShiftForm.operator_user_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" style="font-size: 15px; font-weight: 600;">
                                <option value="" disabled>Select Operator...</option>
                                <option v-for="user in users" :key="user.id" :value="user.id" style="font-size: 15px; font-weight: 600;">
                                    {{ user.name }}
                                </option>
                            </select>
                            <p class="text-[10px] text-muted-foreground">
                                Choose which operator is starting this shift
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <Label>Product to Run</Label>
                            <select v-model="startShiftForm.product_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                <option value="" disabled>Select Product...</option>
                                <option v-for="p in machineProducts" :key="p.id" :value="p.id">
                                    {{ p.name }}{{ p.sku ? ` (${p.sku})` : '' }} - Ideal Rate: {{ p.ideal_rate || 'N/A' }}
                                </option>
                            </select>
                            <p class="text-[10px] text-muted-foreground" v-if="machineProducts.length === 0">
                                No products assigned to this machine. Please assign products in the "Products" tab first.
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <Label>Select Shift</Label>
                             <select v-model="startShiftForm.shift_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                <option value="" disabled>Select Shift...</option>
                                <option v-for="s in machineShifts" :key="s.id" :value="s.id">
                                    {{ s.name }} ({{ s.start_time.substring(0,5) }} - {{ s.end_time.substring(0,5) }})
                                </option>
                            </select>
                            <p class="text-[10px] text-muted-foreground text-red-500" v-if="machineShifts.length === 0">
                                Warning: No shifts configured for this machine. You cannot start a shift without configuration.
                            </p>
                        </div>
                        <div class="grid gap-2">
                             <Label>Batch Number</Label>
                             <Input v-model="startShiftForm.batch_number" placeholder="Enter Batch Number (Optional)" />
                             <p class="text-[10px] text-muted-foreground">
                                 Optional batch number for this production run
                             </p>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" @click="isStartShiftDialogOpen = false">Cancel</Button>
                        <Button @click="confirmStartShift" :disabled="!startShiftForm.product_id || !startShiftForm.shift_id || !startShiftForm.operator_user_id || machineProducts.length === 0">Start Shift</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Target Dialog -->
            <Dialog v-model:open="isTargetDialogOpen">
                <DialogContent class="max-w-2xl flex flex-col max-h-[90vh]">
                    <DialogHeader>
                        <DialogTitle>{{ editingTarget ? 'Edit' : 'Set New' }} Production Target</DialogTitle>
                        <DialogDescription>Define performance goals for a line or machine</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 py-4 flex-1 overflow-y-auto min-h-0">
                        <!-- Target Level Selection -->
                        <div class="grid gap-2">
                            <Label>Target Level *</Label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input 
                                        type="radio" 
                                        name="targetLevel"
                                        :checked="targetLevel === 'line'" 
                                        @change="() => { targetLevel = 'line'; targetForm.machine_id = null; }" 
                                        class="w-4 h-4" 
                                    />
                                    <span>Line Level</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input 
                                        type="radio" 
                                        name="targetLevel"
                                        :checked="targetLevel === 'machine'" 
                                        @change="() => { targetLevel = 'machine'; targetForm.line_id = null; }" 
                                        class="w-4 h-4" 
                                    />
                                    <span>Machine Level</span>
                                </label>
                            </div>
                        </div>

                        <!-- Line Selection (shown if line level selected) -->
                        <div v-if="targetLevel === 'line'" class="grid gap-2">
                            <Label>Line *</Label>
                            <select v-model="targetForm.line_id" required class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option :value="null" disabled>Select line...</option>
                                <template v-for="plant in props.plants" :key="plant.id">
                                    <option v-for="line in plant.lines" :key="line.id" :value="line.id">
                                        {{ plant.name }} - {{ line.name }}
                                    </option>
                                </template>
                            </select>
                        </div>

                        <!-- Machine Selection (shown if machine level selected) -->
                        <div v-if="targetLevel === 'machine'" class="grid gap-2">
                            <Label>Machine *</Label>
                            <select v-model="targetForm.machine_id" required class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option :value="null" disabled>Select machine...</option>
                                <option v-for="machine in allMachines" :key="machine.id" :value="machine.id">
                                    {{ machine.display_name }}
                                </option>
                            </select>
                        </div>

                        <div class="grid gap-2">
                            <Label>Shift (Optional)</Label>
                            <select v-model="targetForm.shift_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option :value="null">All Shifts</option>
                                <option v-for="shift in allShifts" :key="shift.id" :value="shift.id">
                                    {{ shift.name }} ({{ shift.type }})
                                </option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label>From Date *</Label>
                                <Input type="date" v-model="targetForm.effective_from" required />
                            </div>
                            <div class="grid gap-2">
                                <Label>To Date (Optional)</Label>
                                <Input type="date" v-model="targetForm.effective_to" />
                            </div>
                        </div>

                        <div class="border-t pt-4">
                            <h4 class="font-medium mb-3">OEE Targets (%)</h4>
                            
                            <div class="grid gap-3">
                                <div class="grid gap-2">
                                    <div class="flex justify-between items-center">
                                        <Label>Overall OEE</Label>
                                        <span class="text-sm font-bold text-blue-600">{{ targetForm.target_oee }}%</span>
                                    </div>
                                    <input type="range" v-model.number="targetForm.target_oee" min="0" max="100" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-blue-600" />
                                </div>

                                <div class="grid grid-cols-3 gap-3">
                                    <div class="grid gap-1">
                                        <div class="flex justify-between items-center">
                                            <Label class="text-xs">Availability</Label>
                                            <span class="text-xs font-semibold text-green-600">{{ targetForm.target_availability }}%</span>
                                        </div>
                                        <input type="range" v-model.number="targetForm.target_availability" min="0" max="100" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-green-600" />
                                    </div>

                                    <div class="grid gap-1">
                                        <div class="flex justify-between items-center">
                                            <Label class="text-xs">Performance</Label>
                                            <span class="text-xs font-semibold text-yellow-600">{{ targetForm.target_performance }}%</span>
                                        </div>
                                        <input type="range" v-model.number="targetForm.target_performance" min="0" max="100" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-yellow-600" />
                                    </div>

                                    <div class="grid gap-1">
                                        <div class="flex justify-between items-center">
                                            <Label class="text-xs">Quality</Label>
                                            <span class="text-xs font-semibold text-purple-600">{{ targetForm.target_quality }}%</span>
                                        </div>
                                        <input type="range" v-model.number="targetForm.target_quality" min="0" max="100" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-purple-600" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" @click="isTargetDialogOpen = false">Cancel</Button>
                        <Button @click="submitTarget" :disabled="targetForm.processing || (!targetForm.machine_id && !targetForm.line_id)">
                            {{ editingTarget ? 'Update' : 'Create' }} Target
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Confirmation Dialog -->
            <Dialog v-model:open="isConfirmDialogOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{{ confirmOptions.title }}</DialogTitle>
                        <DialogDescription>{{ confirmOptions.message }}</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" @click="isConfirmDialogOpen = false">Cancel</Button>
                        <Button variant="destructive" @click="confirmOptions.action" :disabled="confirmOptions.processing">
                            {{ confirmOptions.processing ? 'Processing...' : 'Confirm' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>


            <!-- Change Product Dialog -->
            <Dialog v-model:open="isChangeProductDialogOpen">
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
                                    {{ activeShifts.get(selectedContext.machineId!)?.productName || 'Unknown' }}
                                </div>
                            </div>
                            <div class="grid gap-2">
                                <Label>Current Batch Number</Label>
                                <div class="p-3 bg-muted rounded-md text-sm font-medium font-mono">
                                    {{ activeShifts.get(selectedContext.machineId!)?.batchNumber || 'N/A' }}
                                </div>
                            </div>
                        </div>
                        
                        <!-- New Product -->
                        <div class="grid gap-2">
                            <Label>New Product *</Label>
                            <select v-model="changeProductForm.to_product_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option value="">Select product...</option>
                                <option v-for="product in machineProducts" :key="product.id" :value="product.id">
                                    {{ product.name }}{{ product.sku ? ` (${product.sku})` : '' }}
                                </option>
                            </select>
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
                        <Button variant="outline" @click="isChangeProductDialogOpen = false">Cancel</Button>
                        <Button 
                            @click="confirmChangeProduct" 
                            :disabled="!changeProductForm.to_product_id || changeProductLoading"
                        >
                            {{ changeProductLoading ? 'Changing...' : 'Change Product' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Organization Rename Dialog -->
            <Dialog v-model:open="isOrgDialogOpen">
                <DialogContent class="max-w-md flex flex-col max-h-[90vh]">
                    <DialogHeader>
                        <DialogTitle>Rename Organization</DialogTitle>
                        <DialogDescription>Update your organization's name and description.</DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4 py-4 flex-1 overflow-y-auto min-h-0">
                        <div class="space-y-2">
                            <Label>Organization Name</Label>
                            <Input v-model="orgForm.name" placeholder="e.g. Acme Manufacturing" />
                            <p v-if="orgForm.errors.name" class="text-sm text-destructive">{{ orgForm.errors.name }}</p>
                        </div>
                        
                        <div class="space-y-2">
                            <Label>Description (Optional)</Label>
                            <Input v-model="orgForm.description" placeholder="Brief description of your organization" />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" @click="isOrgDialogOpen = false">Cancel</Button>
                        <Button @click="submitOrgForm" :disabled="orgForm.processing">
                            {{ orgForm.processing ? 'Saving...' : 'Save Changes' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Material Loss Quick Entry Dialog -->
            <MaterialLossQuickEntry
                v-model:open="isMaterialLossDialogOpen"
                :shiftId="selectedContext.machineId ? activeShifts.get(selectedContext.machineId)?.id ?? null : null"
                :machineId="selectedContext.machineId ?? 0"
                :product="selectedContext.machineId ? (activeShifts.get(selectedContext.machineId)?.product ?? null) : null"
                :products="materialLossProducts"
                :categories="materialLossCategories || []"
                :lossTypes="props.lossTypes || []"
                @success="() => {
                    const shiftId = selectedContext.machineId ? activeShifts.get(selectedContext.machineId)?.id : null;
                    if (shiftId) fetchShiftActivity(shiftId);
                    if (shiftReportRef) shiftReportRef.refresh();
                }"
            />

        </div>
    </AppLayout>
</template>
