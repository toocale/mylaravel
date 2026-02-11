<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-2xl flex flex-col max-h-[90vh]">
            <DialogHeader>
                <DialogTitle>Log Material Loss</DialogTitle>
                <DialogDescription>
                    Record material loss or package waste for the current shift.
                    <!-- Product Selection (if multiple products) -->
                    <div v-if="availableProducts.length > 1" class="block mt-2 p-2 bg-muted/50 rounded text-sm">
                        <label class="block text-xs font-medium mb-1">Select Product</label>
                        <select 
                            v-model="selectedProductId"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option v-for="p in availableProducts" :key="p.id" :value="p.id">
                                {{ p.name }}
                            </option>
                        </select>
                    </div>
                    <!-- Single Product Display -->
                    <span v-else-if="selectedProduct" class="block mt-2 p-2 bg-muted/50 rounded text-sm">
                        <strong>Product:</strong> {{ selectedProduct.name }}<br/>
                        <span class="text-xs text-muted-foreground">
                            Finished Unit: {{ selectedProduct.finished_unit || 'units' }} 
                            <span v-if="selectedProduct.fill_volume">
                                ({{ selectedProduct.fill_volume }} {{ selectedProduct.fill_volume_unit }} each)
                            </span>
                        </span>
                    </span>
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-4 flex-1 overflow-y-auto min-h-0">
                <!-- List of Added Entries -->
                <div v-if="form.losses.length > 0" class="border rounded-md overflow-hidden">
                    <div class="bg-muted px-3 py-2 text-xs font-semibold uppercase flex justify-between items-center">
                        <span>Entries to Submit ({{ form.losses.length }})</span>
                        <Button type="button" variant="ghost" size="sm" class="h-6 text-xs text-red-500 hover:text-red-600" @click="form.losses = []">
                            Clear All
                        </Button>
                    </div>
                    <div class="max-h-[200px] overflow-y-auto divide-y">
                        <div v-for="(entry, idx) in form.losses" :key="idx" class="p-2 text-sm flex justify-between items-center hover:bg-muted/30">
                            <div>
                                <div class="font-medium">
                                    {{ getCategoryName(entry.loss_category_id) }}
                                    <span class="text-muted-foreground font-normal">
                                        ({{ entry.quantity }} {{ entry.unit }})
                                    </span>
                                </div>
                                <div class="text-xs text-muted-foreground truncate max-w-[300px]" v-if="entry.reason || entry.notes">
                                    {{ entry.reason }} {{ entry.notes ? (entry.reason ? ' - ' : '') + entry.notes : '' }}
                                </div>
                            </div>
                            <Button type="button" variant="ghost" size="icon" class="h-6 w-6 text-muted-foreground hover:text-red-500" @click="removeEntry(idx)">
                                <X class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Entry Form -->
                <div class="border rounded-md p-3 bg-muted/20 space-y-3">
                    <div class="flex justify-between items-center mb-1">
                        <h4 class="text-sm font-semibold">New Entry</h4>
                    </div>
                <!-- Loss Category -->
                <div>
                    <label class="block text-sm font-medium mb-1">Loss Category</label>
                    <select
                        v-model="currentEntry.loss_category_id"
                        @change="onCategoryChange"
                        class="w-full rounded-md border border-input bg-background px-3 py-2"
                    >
                        <option value="">Select category...</option>
                        
                        <!-- Dynamic Loss Types -->
                        <template v-if="lossTypes && lossTypes.length > 0">
                            <optgroup v-for="type in lossTypes" :key="type.id" :label="type.name">
                                <option 
                                    v-for="category in categories.filter(c => c.loss_type_id === type.id)" 
                                    :key="category.id" 
                                    :value="category.id"
                                >
                                    {{ category.code }} - {{ category.name }}
                                </option>
                            </optgroup>
                            <!-- Legacy/Unassigned Categories -->
                            <optgroup label="Other / Legacy" v-if="categories.some(c => !c.loss_type_id)">
                                <option 
                                    v-for="category in categories.filter(c => !c.loss_type_id)" 
                                    :key="category.id" 
                                    :value="category.id"
                                >
                                    {{ category.code }} - {{ category.name }}
                                </option>
                            </optgroup>
                        </template>

                        <!-- Fallback for legacy behavior (no loss types defined) -->
                        <template v-else>
                            <optgroup v-if="rawMaterialCategories.length > 0" label="Raw Material Losses">
                                <option 
                                    v-for="category in rawMaterialCategories" 
                                    :key="category.id" 
                                    :value="category.id"
                                >
                                    {{ category.code }} - {{ category.name }}
                                </option>
                            </optgroup>
                            <optgroup v-if="packagingCategories.length > 0" label="Packaging Losses">
                                <option 
                                    v-for="category in packagingCategories" 
                                    :key="category.id" 
                                    :value="category.id"
                                >
                                    {{ category.code }} - {{ category.name }}
                                </option>
                            </optgroup>
                            <optgroup v-if="otherCategories.length > 0" label="Other">
                                <option 
                                    v-for="category in otherCategories" 
                                    :key="category.id" 
                                    :value="category.id"
                                >
                                    {{ category.code }} - {{ category.name }}
                                </option>
                            </optgroup>
                        </template>
                    </select>
                </div>

                <!-- Quantity and Conversion Preview in 2 columns -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">
                            Quantity
                            <span v-if="inputUnit" class="text-xs text-muted-foreground">(in {{ inputUnit }})</span>
                        </label>
                        <div class="flex gap-2">
                            <input
                                v-model.number="currentEntry.quantity"
                                type="number"
                                step="0.01"
                                min="0"
                                class="flex-1 rounded-md border border-input bg-background px-3 py-2"
                                :placeholder="`Enter quantity`"
                                required
                            />
                            <div class="flex items-center px-3 py-2 bg-muted rounded-md text-sm font-medium min-w-[60px]">
                                {{ inputUnit || 'units' }}
                            </div>
                        </div>
                        <p v-if="!product" class="text-xs text-orange-500 mt-1">
                            ⚠️ No active product selected
                        </p>
                    </div>

                    <!-- Conversion Preview -->
                    <div v-if="convertedUnits !== null && selectedCategory" class="p-3 bg-blue-50 dark:bg-blue-950 rounded-md border border-blue-200 dark:border-blue-800">
                        <div class="text-xs text-muted-foreground mb-1">Finished Units Lost:</div>
                        <div class="text-xl font-bold text-blue-600 dark:text-blue-400">
                            {{ convertedUnits.toFixed(2) }} {{ product?.finished_unit || 'units' }}
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">
                            <span v-if="selectedCategory.loss_type === 'raw_material'">Converted from raw material</span>
                            <span v-else>No conversion needed</span>
                        </p>
                    </div>
                </div>

                <!-- Reason and Notes in 2 columns -->
                <div class="grid grid-cols-2 gap-3">
                    <!-- Reason (conditional) -->
                    <div v-if="selectedCategory?.requires_reason">
                        <label class="block text-sm font-medium mb-1">
                        Reason <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        v-model="currentEntry.reason"
                        class="w-full rounded-md border border-input bg-background px-3 py-2"
                        rows="3"
                        placeholder="Explain the reason for this loss..."
                        required
                    ></textarea>
                </div>

                <!-- Notes (optional) -->
                <div>
                    <label class="block text-sm font-medium mb-1">Notes (Optional)</label>
                    <textarea
                        v-model="currentEntry.notes"
                        class="w-full rounded-md border border-input bg-background px-3 py-2"
                        rows="2"
                        placeholder="Additional notes..."
                    ></textarea>
                </div>

                <!-- Cost Estimate (optional) -->
                <div>
                    <label class="block text-sm font-medium mb-1">Cost Estimate (Optional)</label>
                    <input
                        v-model.number="currentEntry.cost_estimate"
                        type="number"
                        step="0.01"
                        min="0"
                        class="w-full rounded-md border border-input bg-background px-3 py-2"
                            placeholder="Estimated cost"
                        />
                    </div>

                    <!-- OEE Impact Badge -->
                    <div v-if="selectedCategory" class="flex items-center gap-2 p-2 rounded-md bg-muted/50">
                        <AlertCircle v-if="selectedCategory.affects_oee" class="h-4 w-4 text-orange-500" />
                        <Info v-else class="h-4 w-4 text-blue-500" />
                        <span class="text-sm">
                            {{ selectedCategory.affects_oee 
                                ? 'Affects OEE quality' 
                                : 'Tracked only' }}
                        </span>
                    </div>
                </div>

                    <Button type="button" variant="secondary" size="sm" class="w-full mt-2" @click="addEntry" :disabled="!currentEntry.loss_category_id || !currentEntry.quantity">
                        <Plus class="h-4 w-4 mr-2" /> Add to List
                    </Button>
                </div>
            </div>

            <DialogFooter class="flex justify-between sm:justify-start gap-2">
                <Button type="button" variant="ghost" @click="$emit('update:open', false)">
                    Cancel
                </Button>
                <div class="flex-1"></div>
                 <Button type="button" @click="submitLoss" :disabled="form.processing || form.losses.length === 0">
                    <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                    Submit {{ form.losses.length }} Entries
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { AlertCircle, Info, Loader2, Plus, X } from 'lucide-vue-next';
import { useToast } from '@/components/ui/toast/use-toast';


interface Product {
    id: number;
    name: string;
    unit_of_measure: string;
    finished_unit: string | null;  // Can be null if not configured
    fill_volume: number | null;
    fill_volume_unit: string | null;
}

interface LossType {
    id: number;
    name: string;
    code: string;
    description?: string;
    color?: string;
}

interface Category {
    id: number;
    code: string;
    name: string;
    loss_type: 'raw_material' | 'packaging' | 'other';
    loss_type_id?: number | null;
    affects_oee: boolean;
    requires_reason: boolean;
}

const props = defineProps<{
    open: boolean;
    shiftId: number | null;
    machineId: number;
    product: Product | null;  // Primary/current product
    products?: Product[];     // All products used during shift (for selection)
    categories: Category[];
    lossTypes?: LossType[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'success': [];
}>();

const { toast } = useToast();

// Product selection
const selectedProductId = ref<number | null>(props.product?.id ?? null);

const availableProducts = computed(() => {
    if (props.products && props.products.length > 0) {
        return props.products;
    }
    return props.product ? [props.product] : [];
});

const selectedProduct = computed(() => {
    return availableProducts.value.find(p => p.id === selectedProductId.value) || props.product;
});

// Watch for product changes and update selection
watch(() => props.product, (newVal) => {
    if (newVal && !selectedProductId.value) {
        selectedProductId.value = newVal.id;
    }
});

watch(() => props.products, (newVal) => {
    if (newVal && newVal.length > 0 && !selectedProductId.value) {
        selectedProductId.value = newVal[0].id;
    }
}, { immediate: true });

const form = useForm({
    losses: [] as any[],
});

// Helper for current entry being added
const currentEntry = ref({
    loss_category_id: '',
    loss_type: 'raw_material' as 'raw_material' | 'packaging' | 'other',
    quantity: 0,
    unit: '',
    reason: '',
    notes: '',
    cost_estimate: null as number | null,
    shift_id: props.shiftId,
    machine_id: props.machineId,
    product_id: props.product?.id,
});

// Separate categories by  loss type
const rawMaterialCategories = computed(() => 
    props.categories.filter(c => c.loss_type === 'raw_material')
);

const packagingCategories = computed(() => 
    props.categories.filter(c => c.loss_type === 'packaging')
);

const otherCategories = computed(() => 
    props.categories.filter(c => c.loss_type === 'other')
);

const selectedCategory = computed(() => {
    if (!currentEntry.value.loss_category_id) return null;
    return props.categories.find(c => c.id === parseInt(currentEntry.value.loss_category_id as string));
});

// Determine what unit to show for input
const inputUnit = computed(() => {
    if (!selectedCategory.value || !selectedProduct.value) return 'units';
    
    if (selectedCategory.value.loss_type === 'raw_material') {
        return selectedProduct.value.unit_of_measure; // liters, kg, ml
    } else {
        return selectedProduct.value.finished_unit; // bottles, boxes, pieces
    }
});

// Calculate converted units in real-time
const convertedUnits = computed(() => {
    if (!selectedCategory.value || !selectedProduct.value || !currentEntry.value.quantity) return null;
    
    if (selectedCategory.value.loss_type === 'raw_material') {
        // Convert raw to finished
        return convertToFinishedUnits(
            currentEntry.value.quantity,
            selectedProduct.value.unit_of_measure,
            selectedProduct.value.fill_volume ?? 1,
            selectedProduct.value.fill_volume_unit ?? 'units'
        );
    } else {
        // Packaging is already in finished units
        return currentEntry.value.quantity;
    }
});

// Conversion function (matches backend logic)
function convertToFinishedUnits(
    rawQuantity: number,
    rawUnit: string,
    fillVolume: number,
    fillVolumeUnit: string
): number {
    const conversions: Record<string, number> = {
        // Volume
        'liters': 1000, 'l': 1000, 'ml': 1, 'milliliters': 1,
        // Weight
        'kg': 1000, 'kilograms': 1000, 'grams': 1, 'g': 1,
        // Count
        'pieces': 1, 'units': 1, 'bottles': 1, 'boxes': 1, 'cartons': 1, 'sachets': 1,
    };
    
    const rawNormalized = rawQuantity * (conversions[rawUnit.toLowerCase()] || 1);
    const fillNormalized = fillVolume * (conversions[fillVolumeUnit.toLowerCase()] || 1);
    
    if (fillNormalized === 0) return rawQuantity;
    
    return rawNormalized / fillNormalized;
}

const onCategoryChange = () => {
    if (selectedCategory.value) {
        // Update loss_type from category
        currentEntry.value.loss_type = selectedCategory.value.loss_type;
        
        // Update unit based on loss type
        if (selectedProduct.value) {
            if (selectedCategory.value.loss_type === 'raw_material') {
                currentEntry.value.unit = selectedProduct.value.unit_of_measure;
            } else {
                currentEntry.value.unit = selectedProduct.value.finished_unit || 'units';  // Fallback if not configured
            }
        }
        
        // Reset reason if category doesn't require it
        if (!selectedCategory.value.requires_reason) {
            currentEntry.value.reason = '';
        }
    }
};

const getCategoryName = (id: string | number) => {
    return props.categories.find(c => c.id == id)?.name || 'Unknown';
};

const addEntry = () => {
    // Validate
    if (selectedCategory.value?.requires_reason && !currentEntry.value.reason) {
        toast({ title: 'Error', description: 'Reason is required for this category.', variant: 'destructive' });
        return;
    }

    form.losses.push({
        ...currentEntry.value,
        product_id: selectedProductId.value,  // Use selected product
        occurred_at: new Date().toISOString()
    });

    // Reset fields but keep context
    currentEntry.value.quantity = 0;
    currentEntry.value.reason = '';
    currentEntry.value.notes = '';
    currentEntry.value.cost_estimate = null;
    currentEntry.value.loss_category_id = '';
};

const removeEntry = (idx: number) => {
    form.losses.splice(idx, 1);
};

const submitLoss = () => {
    form.post('/api/material-loss', {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            toast({
                title: 'Success',
                description: `${form.losses.length} material loss entries logged.`,
            });
            form.reset();
            emit('success');
            emit('update:open', false);
        },
        onError: (errors) => {
            toast({
                title: 'Error',
                description: Object.values(errors).join(', '),
                variant: 'destructive',
            });
        },
    });
};

// Update form when props change
watch(() => props.shiftId, (newVal) => {
    currentEntry.value.shift_id = newVal;
});

watch(() => props.product, (newVal) => {
    currentEntry.value.product_id = newVal?.id;
});

watch(() => props.machineId, (newVal) => {
    currentEntry.value.machine_id = newVal;
});
</script>
