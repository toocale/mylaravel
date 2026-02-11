<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { 
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { 
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { 
    Package, 
    Plus, 
    AlertTriangle, 
    Search, 
    Edit, 
    Trash2,
    TrendingDown,
    History,
    DollarSign
} from 'lucide-vue-next';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps<{
    machineId: number;
}>();

// State
const loading = ref(true);
const parts = ref<any[]>([]);
const categories = ref<string[]>([]);
const dialogOpen = ref(false);
const usageDialogOpen = ref(false);
const editingPart = ref<any>(null);
const searchQuery = ref('');
const filterCategory = ref('all');
const showLowStockOnly = ref(false);
const lowStockCount = ref(0);
const outOfStockCount = ref(0);

// Form state
const form = ref({
    part_number: '',
    name: '',
    description: '',
    category: '',
    manufacturer: '',
    supplier: '',
    quantity_in_stock: 0,
    minimum_stock_level: 1,
    reorder_quantity: 5,
    unit_cost: null as number | null,
    location: '',
});

// Usage form
const usageForm = ref({
    spare_part_id: null as number | null,
    quantity_used: 1,
    notes: '',
});
const selectedPartForUsage = ref<any>(null);

// Permissions
const page = usePage();
const can = (permission: string) => {
    const user = page.props.auth?.user as any;
    return user?.is_admin || user?.permissions?.includes(permission);
};

// Computed
const filteredParts = computed(() => {
    let result = parts.value;
    
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(p => 
            p.name.toLowerCase().includes(query) ||
            p.part_number.toLowerCase().includes(query) ||
            (p.manufacturer?.toLowerCase().includes(query))
        );
    }
    
    if (filterCategory.value && filterCategory.value !== 'all') {
        result = result.filter(p => p.category === filterCategory.value);
    }
    
    if (showLowStockOnly.value) {
        result = result.filter(p => p.stock_status !== 'in_stock');
    }
    
    return result;
});

// Methods
async function fetchParts() {
    loading.value = true;
    try {
        const response = await axios.get(`/api/v1/machines/${props.machineId}/spare-parts`);
        parts.value = response.data.parts;
        lowStockCount.value = response.data.low_stock_count;
        outOfStockCount.value = response.data.out_of_stock_count;
    } catch (error) {
        console.error('Failed to fetch spare parts:', error);
    } finally {
        loading.value = false;
    }
}

async function fetchCategories() {
    try {
        const response = await axios.get('/api/v1/spare-parts/categories');
        categories.value = response.data;
    } catch (error) {
        console.error('Failed to fetch categories:', error);
    }
}

function openDialog() {
    editingPart.value = null;
    form.value = {
        part_number: '',
        name: '',
        description: '',
        category: '',
        manufacturer: '',
        supplier: '',
        quantity_in_stock: 0,
        minimum_stock_level: 1,
        reorder_quantity: 5,
        unit_cost: null,
        location: '',
    };
    dialogOpen.value = true;
}

function openEditDialog(part: any) {
    editingPart.value = part;
    form.value = {
        part_number: part.part_number,
        name: part.name,
        description: part.description || '',
        category: part.category || '',
        manufacturer: part.manufacturer || '',
        supplier: part.supplier || '',
        quantity_in_stock: part.quantity_in_stock,
        minimum_stock_level: part.minimum_stock_level,
        reorder_quantity: part.reorder_quantity || 5,
        unit_cost: part.unit_cost,
        location: part.location || '',
    };
    dialogOpen.value = true;
}

async function savePart() {
    try {
        if (editingPart.value) {
            await axios.put(`/api/v1/spare-parts/${editingPart.value.id}`, form.value);
        } else {
            await axios.post(`/api/v1/machines/${props.machineId}/spare-parts`, form.value);
        }
        dialogOpen.value = false;
        await fetchParts();
        await fetchCategories();
    } catch (error: any) {
        console.error('Failed to save part:', error);
        alert(error.response?.data?.message || 'Failed to save spare part');
    }
}

async function deletePart(partId: number) {
    if (!confirm('Are you sure you want to delete this spare part?')) return;
    
    try {
        await axios.delete(`/api/v1/spare-parts/${partId}`);
        await fetchParts();
    } catch (error) {
        console.error('Failed to delete part:', error);
    }
}

function openUsageDialog(part: any) {
    selectedPartForUsage.value = part;
    usageForm.value = {
        spare_part_id: part.id,
        quantity_used: 1,
        notes: '',
    };
    usageDialogOpen.value = true;
}

async function recordUsage() {
    try {
        await axios.post(`/api/v1/machines/${props.machineId}/spare-parts/usage`, usageForm.value);
        usageDialogOpen.value = false;
        await fetchParts();
    } catch (error: any) {
        console.error('Failed to record usage:', error);
        alert(error.response?.data?.message || 'Failed to record part usage');
    }
}

function getStockStatusColor(status: string) {
    switch (status) {
        case 'out_of_stock': return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
        case 'low_stock': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
        default: return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
    }
}

function getStockStatusLabel(status: string) {
    switch (status) {
        case 'out_of_stock': return 'Out of Stock';
        case 'low_stock': return 'Low Stock';
        default: return 'In Stock';
    }
}

onMounted(() => {
    fetchParts();
    fetchCategories();
});
</script>

<template>
    <div class="space-y-4">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-semibold">Spare Parts Inventory</h3>
                <p class="text-sm text-muted-foreground">Manage spare parts and track stock levels</p>
            </div>
            <Button v-if="can('maintenance.create')" @click="openDialog" size="sm">
                <Plus class="h-4 w-4 mr-2" />
                Add Part
            </Button>
        </div>

        <!-- Alerts -->
        <div v-if="lowStockCount > 0 || outOfStockCount > 0" class="flex gap-4">
            <Card v-if="outOfStockCount > 0" class="flex-1 border-red-300 bg-red-50/50 dark:bg-red-950/20">
                <CardContent class="p-3 flex items-center gap-3">
                    <AlertTriangle class="h-5 w-5 text-red-600" />
                    <div>
                        <p class="text-sm font-medium text-red-800 dark:text-red-400">{{ outOfStockCount }} Out of Stock</p>
                    </div>
                </CardContent>
            </Card>
            <Card v-if="lowStockCount > 0" class="flex-1 border-yellow-300 bg-yellow-50/50 dark:bg-yellow-950/20">
                <CardContent class="p-3 flex items-center gap-3">
                    <TrendingDown class="h-5 w-5 text-yellow-600" />
                    <div>
                        <p class="text-sm font-medium text-yellow-800 dark:text-yellow-400">{{ lowStockCount }} Low Stock</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input 
                    v-model="searchQuery" 
                    placeholder="Search parts..." 
                    class="pl-10"
                />
            </div>
            <Select v-model="filterCategory">
                <SelectTrigger class="w-full sm:w-48">
                    <SelectValue placeholder="All Categories" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Categories</SelectItem>
                    <SelectItem v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</SelectItem>
                </SelectContent>
            </Select>
            <Button 
                variant="outline" 
                size="sm"
                :class="{ 'bg-yellow-100 dark:bg-yellow-900/30': showLowStockOnly }"
                @click="showLowStockOnly = !showLowStockOnly"
            >
                <AlertTriangle class="h-4 w-4 mr-2" />
                Low Stock Only
            </Button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="text-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
        </div>

        <!-- Parts Grid -->
        <div v-else-if="filteredParts.length > 0" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <Card 
                v-for="part in filteredParts" 
                :key="part.id"
                :class="{
                    'border-red-300 bg-red-50/50 dark:bg-red-950/20': part.stock_status === 'out_of_stock',
                    'border-yellow-300 bg-yellow-50/50 dark:bg-yellow-950/20': part.stock_status === 'low_stock'
                }"
            >
                <CardContent class="p-4 space-y-3">
                    <!-- Header -->
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <Package class="h-4 w-4 text-muted-foreground" />
                                <h4 class="font-semibold text-sm">{{ part.name }}</h4>
                            </div>
                            <p class="text-xs text-muted-foreground">{{ part.part_number }}</p>
                        </div>
                        <Badge :class="getStockStatusColor(part.stock_status)" variant="outline" class="text-xs">
                            {{ getStockStatusLabel(part.stock_status) }}
                        </Badge>
                    </div>

                    <!-- Stock Info -->
                    <div class="flex items-center justify-between bg-muted/50 rounded-lg p-2">
                        <div class="text-center">
                            <p class="text-lg font-bold">{{ part.quantity_in_stock }}</p>
                            <p class="text-xs text-muted-foreground">In Stock</p>
                        </div>
                        <div class="text-center">
                            <p class="text-lg font-medium text-muted-foreground">{{ part.minimum_stock_level }}</p>
                            <p class="text-xs text-muted-foreground">Min Level</p>
                        </div>
                        <div v-if="part.unit_cost" class="text-center">
                            <p class="text-lg font-medium text-green-600">${{ part.unit_cost }}</p>
                            <p class="text-xs text-muted-foreground">Unit Cost</p>
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="grid grid-cols-2 gap-2 text-xs border-t pt-2">
                        <div v-if="part.category">
                            <span class="text-muted-foreground block">Category</span>
                            <span class="font-medium">{{ part.category }}</span>
                        </div>
                        <div v-if="part.location">
                            <span class="text-muted-foreground block">Location</span>
                            <span class="font-medium">{{ part.location }}</span>
                        </div>
                        <div v-if="part.manufacturer">
                            <span class="text-muted-foreground block">Manufacturer</span>
                            <span class="font-medium">{{ part.manufacturer }}</span>
                        </div>
                        <div v-if="part.total_usage">
                            <span class="text-muted-foreground block">Total Used</span>
                            <span class="font-medium">{{ part.total_usage }} units</span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2 pt-2 border-t">
                        <Button 
                            v-if="can('maintenance.create')" 
                            variant="outline" 
                            size="sm" 
                            class="flex-1"
                            @click="openUsageDialog(part)"
                        >
                            <History class="h-3 w-3 mr-1" />
                            Use Part
                        </Button>
                        <Button 
                            v-if="can('maintenance.edit')" 
                            variant="ghost" 
                            size="sm"
                            @click="openEditDialog(part)"
                        >
                            <Edit class="h-4 w-4" />
                        </Button>
                        <Button 
                            v-if="can('maintenance.delete')" 
                            variant="ghost" 
                            size="sm"
                            class="text-red-600 hover:text-red-700"
                            @click="deletePart(part.id)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Empty State -->
        <Card v-else>
            <CardContent class="p-8 text-center">
                <Package class="h-12 w-12 mx-auto mb-3 text-muted-foreground opacity-50" />
                <p class="text-muted-foreground mb-4">No spare parts found</p>
                <Button v-if="can('maintenance.create')" @click="openDialog" size="sm">
                    <Plus class="h-4 w-4 mr-2" />
                    Add First Part
                </Button>
            </CardContent>
        </Card>

        <!-- Add/Edit Part Dialog -->
        <Dialog v-model:open="dialogOpen">
            <DialogContent class="max-w-lg max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{{ editingPart ? 'Edit Spare Part' : 'Add Spare Part' }}</DialogTitle>
                    <DialogDescription>
                        {{ editingPart ? 'Update spare part details' : 'Add a new spare part to inventory' }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="part_number">Part Number *</Label>
                            <Input id="part_number" v-model="form.part_number" placeholder="SKU-001" />
                        </div>
                        <div class="space-y-2">
                            <Label for="name">Name *</Label>
                            <Input id="name" v-model="form.name" placeholder="Bearing 6205" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="description">Description</Label>
                        <Textarea id="description" v-model="form.description" placeholder="Optional description" rows="2" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="category">Category</Label>
                            <Input id="category" v-model="form.category" placeholder="Bearings" />
                        </div>
                        <div class="space-y-2">
                            <Label for="location">Storage Location</Label>
                            <Input id="location" v-model="form.location" placeholder="Shelf A-3" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="manufacturer">Manufacturer</Label>
                            <Input id="manufacturer" v-model="form.manufacturer" placeholder="SKF" />
                        </div>
                        <div class="space-y-2">
                            <Label for="supplier">Supplier</Label>
                            <Input id="supplier" v-model="form.supplier" placeholder="Industrial Supply Co" />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="space-y-2">
                            <Label for="quantity">Qty in Stock *</Label>
                            <Input id="quantity" v-model.number="form.quantity_in_stock" type="number" min="0" />
                        </div>
                        <div class="space-y-2">
                            <Label for="min_level">Min Level *</Label>
                            <Input id="min_level" v-model.number="form.minimum_stock_level" type="number" min="0" />
                        </div>
                        <div class="space-y-2">
                            <Label for="unit_cost">Unit Cost ($)</Label>
                            <Input id="unit_cost" v-model.number="form.unit_cost" type="number" min="0" step="0.01" />
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="dialogOpen = false">Cancel</Button>
                    <Button @click="savePart">{{ editingPart ? 'Update' : 'Add' }} Part</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Record Usage Dialog -->
        <Dialog v-model:open="usageDialogOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Record Part Usage</DialogTitle>
                    <DialogDescription v-if="selectedPartForUsage">
                        Recording usage for: {{ selectedPartForUsage.name }}
                        <br />
                        <span class="text-sm">Available: {{ selectedPartForUsage.quantity_in_stock }} units</span>
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-4">
                    <div class="space-y-2">
                        <Label for="quantity_used">Quantity Used *</Label>
                        <Input 
                            id="quantity_used" 
                            v-model.number="usageForm.quantity_used" 
                            type="number" 
                            min="1" 
                            :max="selectedPartForUsage?.quantity_in_stock"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="usage_notes">Notes</Label>
                        <Textarea 
                            id="usage_notes" 
                            v-model="usageForm.notes" 
                            placeholder="Optional notes about usage" 
                            rows="2"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="usageDialogOpen = false">Cancel</Button>
                    <Button @click="recordUsage">Record Usage</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
