<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Plus, Package, AlertTriangle, CheckCircle, Pencil } from 'lucide-vue-next';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';

const props = defineProps<{
    machineId: number;
}>();

const emit = defineEmits(['componentAdded']);

// State
const loading = ref(false);
const components = ref<any[]>([]);
const dialogOpen = ref(false);
const saving = ref(false);
const editingId = ref<number | null>(null);

// Form
const form = ref({
    component_name: '',
    component_type: '',
    manufacturer: '',
    model_number: '',
    serial_number: '',
    installed_at: '',
    expected_lifespan_hours: null as number | null,
    current_runtime_hours: 0,
    cost: null as number | null,
});

// Permissions
const page = usePage();
const can = (permission: string) => {
    return (page.props.auth as any).permissions?.includes(permission);
};

// Methods
const fetchComponents = async () => {
    loading.value = true;
    try {
        const response = await axios.get(`/api/v1/machines/${props.machineId}/components`);
        components.value = response.data;
    } catch (error) {
        console.error('Failed to fetch components:', error);
    } finally {
        loading.value = false;
    }
};

const openDialog = () => {
    editingId.value = null;
    form.value = {
        component_name: '',
        component_type: '',
        manufacturer: '',
        model_number: '',
        serial_number: '',
        installed_at: '',
        expected_lifespan_hours: null,
        current_runtime_hours: 0,
        cost: null,
    };
    dialogOpen.value = true;
};

const openEditDialog = (component: any) => {
    editingId.value = component.id;
    form.value = {
        component_name: component.component_name,
        component_type: component.component_type || '',
        manufacturer: component.manufacturer || '',
        model_number: component.model_number || '',
        serial_number: component.serial_number || '',
        installed_at: component.installed_at ? component.installed_at.split(' ')[0] : '',
        expected_lifespan_hours: component.expected_lifespan_hours,
        current_runtime_hours: component.current_runtime_hours,
        cost: component.cost,
    };
    dialogOpen.value = true;
};

const saveComponent = async () => {
    saving.value = true;
    try {
        if (editingId.value) {
            // Update existing component
            await axios.put(`/api/v1/components/${editingId.value}`, form.value);
        } else {
            // Create new component
            await axios.post(`/api/v1/machines/${props.machineId}/components`, form.value);
        }
        dialogOpen.value = false;
        await fetchComponents();
        emit('componentAdded');
    } catch (error: any) {
        console.error('Failed to save component:', error);
        alert(error.response?.data?.message || 'Failed to save component');
    } finally {
        saving.value = false;
    }
};

const deleteComponent = async (componentId: number) => {
    if (!confirm('Are you sure you want to delete this component?')) return;
    
    try {
        await axios.delete(`/api/v1/components/${componentId}`);
        await fetchComponents();
    } catch (error) {
        console.error('Failed to delete component:', error);
        alert('Failed to delete component');
    }
};

const getStatusColor = (status: string) => {
    const colors: any = {
        good: 'bg-green-100 text-green-700 border-green-300',
        warning: 'bg-yellow-100 text-yellow-700 border-yellow-300',
        critical: 'bg-red-100 text-red-700 border-red-300',
        replaced: 'bg-gray-100 text-gray-700 border-gray-300',
    };
    return colors[status] || colors.good;
};

const getStatusIcon = (status: string) => {
    if (status === 'critical') return AlertTriangle;
    if (status === 'warning') return AlertTriangle;
    return CheckCircle;
};

const getProgressColor = (percentage: number | null) => {
    if (percentage === null) return '';
    if (percentage <= 20) return 'bg-red-600';
    if (percentage <= 50) return 'bg-yellow-600';
    return 'bg-green-600';
};

const formatDate = (date: string) => {
    if (!date) return 'Not set';
    return new Date(date).toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric' 
    });
};

onMounted(() => {
    fetchComponents();
});
</script>

<template>
    <div class="space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-base font-semibold">Machine Components</h3>
                <p class="text-sm text-muted-foreground">Track component lifespan and replacement needs</p>
            </div>
            <Button v-if="can('maintenance.create')" @click="openDialog" size="sm">
                <Plus class="h-4 w-4 mr-2" />
                Add Component
            </Button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="text-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
        </div>

        <!-- Components Grid -->
        <div v-else-if="components.length > 0" class="grid gap-4 md:grid-cols-2">
            <Card v-for="component in components" :key="component.id" 
                  :class="{'border-red-300 bg-red-50/50 dark:bg-red-950/20': component.status === 'critical',
                           'border-yellow-300 bg-yellow-50/50 dark:bg-yellow-950/20': component.status === 'warning'}">
                <CardContent class="p-4">
                    <div class="space-y-3">
                        <!-- Header -->
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <component :is="getStatusIcon(component.status)" 
                                              class="h-4 w-4" 
                                              :class="{
                                                  'text-red-600': component.status === 'critical',
                                                  'text-yellow-600': component.status === 'warning',
                                                  'text-green-600': component.status === 'good'
                                              }" />
                                    <h4 class="font-semibold">{{ component.component_name }}</h4>
                                </div>
                                <p v-if="component.component_type" class="text-sm text-muted-foreground capitalize">
                                    {{ component.component_type }}
                                </p>
                            </div>
                            <Badge :class="getStatusColor(component.status)" variant="outline" class="text-xs">
                                {{ component.status }}
                            </Badge>
                        </div>

                        <!-- Lifespan Progress -->
                        <div v-if="component.remaining_life_percentage !== null" class="space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-muted-foreground">Remaining Life</span>
                                <span class="font-semibold">{{ Math.round(component.remaining_life_percentage) }}%</span>
                            </div>
                            <div class="relative h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div 
                                    class="absolute top-0 left-0 h-full transition-all"
                                    :class="getProgressColor(component.remaining_life_percentage)"
                                    :style="{ width: component.remaining_life_percentage + '%' }"
                                ></div>
                            </div>
                            <div class="flex items-center justify-between text-xs text-muted-foreground">
                                <span>{{ component.current_runtime_hours?.toLocaleString() || 0 }} hrs used</span>
                                <span>{{ component.expected_lifespan_hours?.toLocaleString() || 0 }} hrs total</span>
                            </div>
                        </div>

                        <!-- Details -->
                        <div class="grid grid-cols-2 gap-2 text-xs border-t pt-3">
                            <div v-if="component.manufacturer">
                                <span class="text-muted-foreground block">Manufacturer</span>
                                <span class="font-medium">{{ component.manufacturer }}</span>
                            </div>
                            <div v-if="component.model_number">
                                <span class="text-muted-foreground block">Model</span>
                                <span class="font-medium">{{ component.model_number }}</span>
                            </div>
                            <div v-if="component.installed_at">
                                <span class="text-muted-foreground block">Installed</span>
                                <span class="font-medium">{{ formatDate(component.installed_at) }}</span>
                            </div>
                            <div v-if="component.cost">
                                <span class="text-muted-foreground block">Cost</span>
                                <span class="font-medium">${{ component.cost }}</span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-2 pt-2 border-t">
                            <Button v-if="can('maintenance.edit')" variant="ghost" size="sm" @click="openEditDialog(component)" class="text-blue-600 hover:text-blue-700 text-xs">
                                <Pencil class="h-3 w-3 mr-1" />
                                Edit
                            </Button>
                            <Button v-if="can('maintenance.delete')" variant="ghost" size="sm" @click="deleteComponent(component.id)" class="text-red-600 hover:text-red-700 text-xs">
                                Remove
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Empty State -->
        <Card v-else>
            <CardContent class="p-8 text-center">
                <Package class="h-12 w-12 mx-auto mb-3 text-muted-foreground opacity-50" />
                <p class="text-sm text-muted-foreground mb-3">No components tracked yet</p>
                <Button v-if="can('maintenance.create')" @click="openDialog" size="sm" variant="outline">
                    <Plus class="h-4 w-4 mr-2" />
                    Add First Component
                </Button>
            </CardContent>
        </Card>

        <!-- Add Component Dialog -->
        <Dialog v-model:open="dialogOpen">
            <DialogContent class="sm:max-w-[600px] max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{{ editingId ? 'Edit Machine Component' : 'Add Machine Component' }}</DialogTitle>
                    <DialogDescription>{{ editingId ? 'Update component information and lifespan tracking' : 'Track a critical machine part for maintenance planning' }}</DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <!-- Basic Info -->
                    <div class="space-y-2">
                        <Label>Component Name *</Label>
                        <Input v-model="form.component_name" placeholder="e.g., Main Drive Motor" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label>Type</Label>
                            <select v-model="form.component_type" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option value="">Select type...</option>
                                <option value="motor">Motor</option>
                                <option value="bearing">Bearing</option>
                                <option value="belt">Belt</option>
                                <option value="chain">Chain</option>
                                <option value="pump">Pump</option>
                                <option value="sensor">Sensor</option>
                                <option value="filter">Filter</option>
                                <option value="valve">Valve</option>
                                <option value="fan">Fan</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <Label>Manufacturer</Label>
                            <Input v-model="form.manufacturer" placeholder="e.g., Siemens" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label>Model Number</Label>
                            <Input v-model="form.model_number" placeholder="e.g., 1LA7-133" />
                        </div>

                        <div class="space-y-2">
                            <Label>Serial Number</Label>
                            <Input v-model="form.serial_number" placeholder="e.g., SN-20231001" />
                        </div>
                    </div>

                    <!-- Lifespan Tracking -->
                    <div class="border-t pt-4">
                        <h4 class="text-sm font-semibold mb-3">Lifespan Tracking</h4>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label>Expected Lifespan (Hours)</Label>
                                <Input v-model.number="form.expected_lifespan_hours" type="number" min="0" placeholder="e.g., 10000" :value="form.expected_lifespan_hours ?? undefined" />
                            </div>

                            <div class="space-y-2">
                                <Label>Current Runtime (Hours)</Label>
                                <Input v-model.number="form.current_runtime_hours" type="number" min="0" placeholder="e.g., 2500" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mt-4">
                            <div class="space-y-2">
                                <Label>Installation Date</Label>
                                <Input v-model="form.installed_at" type="date" />
                            </div>

                            <div class="space-y-2">
                                <Label>Cost ($)</Label>
                                <Input v-model.number="form.cost" type="number" min="0" step="0.01" placeholder="e.g., 1250.00" :value="form.cost ?? undefined" />
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="dialogOpen = false">Cancel</Button>
                    <Button @click="saveComponent" :disabled="saving || !form.component_name">
                        {{ saving ? 'Saving...' : (editingId ? 'Update Component' : 'Add Component') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
