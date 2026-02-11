<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { useToast } from '@/composables/useToast';
import { Plus, Pencil, Trash2, Scale, Calculator } from 'lucide-vue-next';
import FormulasTab from './Tabs/Formulas.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface UnitConversion {
    id: number;
    name: string;
    code: string;
    alias: string | null;
    category: 'volume' | 'weight' | 'count';
    to_base_factor: number;
    base_unit_code: string;
    is_base: boolean;
    active: boolean;
}

const props = defineProps<{
    settings: Record<string, any[]>;
}>();

const { addToast } = useToast();

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Site Settings', href: '/admin/settings' },
];

// Prepare form data from props
const getInitialForm = () => {
    const formData: Record<string, any> = {};
    Object.values(props.settings).flat().forEach((setting: any) => {
        formData[setting.key] = setting.value;
    });
    // Add formula keys if missing from settings (safety)
    if (formData.formula_target_time_basis === undefined) formData.formula_target_time_basis = 'planned_production_time';
    if (formData.formula_availability_exclude_breaks === undefined) formData.formula_availability_exclude_breaks = '1';
    if (formData.formula_performance_include_rejects === undefined) formData.formula_performance_include_rejects = '1';

    return formData;
};

const form = useForm(getInitialForm());

watch(() => [form.site_skin, form.default_theme], ([newSkin, newTheme]) => {
    if (typeof document === 'undefined') return;

    const root = document.documentElement;
    
    // Apply Skin
    root.classList.remove('theme-forest', 'theme-elegant', 'theme-cyber');
    if (newSkin && newSkin !== 'default') {
        root.classList.add(`theme-${newSkin}`);
    }

    // Apply Theme Mode
    root.classList.remove('dark');
    if (newTheme === 'dark') {
        root.classList.add('dark');
    } else if (newTheme === 'system') {
        if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            root.classList.add('dark');
        }
    } else if (newTheme === 'light') {
        root.classList.remove('dark');
    }
}, { deep: true });

// Add "units" to groups list
const groups = [...Object.keys(props.settings), 'units'];
const activeGroup = ref(groups[0]);

const submit = () => {
    form.post('/admin/settings', {
        preserveScroll: true,
        onSuccess: () => {
            addToast({
                title: 'Settings Saved',
                message: 'The site configuration has been updated successfully.',
                type: 'success'
            });
        },
        onError: () => {
             addToast({
                title: 'Error',
                message: 'Failed to save settings. Please check your inputs.',
                type: 'error',
            });
        }
    });
};

const previewImages = ref<Record<string, string>>({});

const handleFileChange = (key: string, event: Event) => {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        form[key] = input.files[0];
        // Create preview
        const reader = new FileReader();
        reader.onload = (e) => {
             previewImages.value[key] = e.target?.result as string;
        };
        reader.readAsDataURL(input.files[0]);
    }
};

// ========== UNIT CONVERSIONS ==========
const unitConversions = ref<UnitConversion[]>([]);
const loadingUnits = ref(false);
const isUnitDialogOpen = ref(false);
const editingUnit = ref<UnitConversion | null>(null);

const unitForm = useForm({
    name: '',
    code: '',
    alias: '',
    category: 'count' as 'volume' | 'weight' | 'count',
    to_base_factor: 1,
    base_unit_code: '',
    is_base: false,
    active: true,
});

const fetchUnits = async () => {
    loadingUnits.value = true;
    try {
        const response = await fetch('/admin/unit-conversions');
        const data = await response.json();
        if (data.success) {
            unitConversions.value = data.units;
        }
    } catch (e) {
        console.error('Failed to fetch unit conversions:', e);
    } finally {
        loadingUnits.value = false;
    }
};

onMounted(() => {
    if (activeGroup.value === 'units') {
        fetchUnits();
    }
});

watch(activeGroup, (newGroup) => {
    if (newGroup === 'units' && unitConversions.value.length === 0) {
        fetchUnits();
    }
});

const openUnitDialog = (unit: UnitConversion | null = null) => {
    editingUnit.value = unit;
    if (unit) {
        unitForm.name = unit.name;
        unitForm.code = unit.code;
        unitForm.alias = unit.alias || '';
        unitForm.category = unit.category;
        unitForm.to_base_factor = unit.to_base_factor;
        unitForm.base_unit_code = unit.base_unit_code;
        unitForm.is_base = unit.is_base;
        unitForm.active = unit.active;
    } else {
        unitForm.reset();
        unitForm.category = 'count';
        unitForm.to_base_factor = 1;
        unitForm.active = true;
    }
    isUnitDialogOpen.value = true;
};

const submitUnit = async () => {
    const url = editingUnit.value 
        ? `/admin/unit-conversions/${editingUnit.value.id}` 
        : '/admin/unit-conversions';
    const method = editingUnit.value ? 'PUT' : 'POST';
    
    try {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify(unitForm.data()),
        });
        
        const data = await response.json();
        
        if (data.success) {
            addToast({
                title: 'Success',
                message: data.message,
                type: 'success',
            });
            isUnitDialogOpen.value = false;
            fetchUnits();
        } else {
            addToast({
                title: 'Error',
                message: data.error || 'Failed to save unit.',
                type: 'error',
            });
        }
    } catch (e) {
        addToast({
            title: 'Error',
            message: 'Failed to save unit conversion.',
            type: 'error',
        });
    }
};

const deleteUnit = async (unit: UnitConversion) => {
    if (!confirm(`Delete unit "${unit.name}"? This cannot be undone.`)) return;
    
    try {
        const response = await fetch(`/admin/unit-conversions/${unit.id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
        });
        
        const data = await response.json();
        
        if (data.success) {
            addToast({
                title: 'Deleted',
                message: data.message,
                type: 'success',
            });
            fetchUnits();
        } else {
            addToast({
                title: 'Error',
                message: data.error || 'Failed to delete unit.',
                type: 'error',
            });
        }
    } catch (e) {
        addToast({
            title: 'Error',
            message: 'Failed to delete unit.',
            type: 'error',
        });
    }
};

const getCategoryColor = (category: string) => {
    switch (category) {
        case 'volume': return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
        case 'weight': return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400';
        case 'count': return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
        default: return 'bg-gray-100 text-gray-800';
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Site Configuration" />

        <div class="relative overflow-hidden rounded-3xl min-h-[600px] bg-background/50 border border-border/50">
            <!-- Animated Background Mesh -->
            <div class="absolute inset-0 z-0 opacity-40 dark:opacity-20 pointer-events-none">
                <div class="absolute top-[-20%] left-[-10%] w-[60%] h-[60%] rounded-full bg-blue-500/30 blur-[120px] animate-blob"></div>
                <div class="absolute bottom-[-20%] right-[-10%] w-[60%] h-[60%] rounded-full bg-purple-500/30 blur-[120px] animate-blob animation-delay-2000"></div>
            </div>

            <div class="relative z-10 p-6 md:p-8 backdrop-blur-[2px]">
                <div class="flex items-center justify-between mb-8">
                     <div>
                        <h2 class="text-xl font-semibold tracking-tight">Site Configuration</h2>
                        <p class="text-sm text-muted-foreground">Manage global application settings and preferences.</p>
                     </div>
                     <Button v-if="activeGroup !== 'units'" @click="submit" :disabled="form.processing">
                        <span v-if="form.processing" class="animate-spin mr-2">⏳</span>
                        Save Changes
                     </Button>
                </div>

                <div class="glass-panel rounded-2xl border border-white/20 shadow-xl overflow-hidden backdrop-blur-md">
                    <div class="flex flex-col lg:flex-row min-h-[500px]">
                        <!-- Sidebar -->
                        <aside class="w-full lg:w-64 border-b lg:border-b-0 lg:border-r border-white/10 dark:border-white/5 bg-white/5 p-4 lg:py-6 lg:px-4">
                            <nav class="flex flex-col space-y-2">
                                <Button
                                    v-for="group in groups"
                                    :key="group"
                                    variant="ghost"
                                    :class="[
                                        'w-full justify-start transition-all duration-300 capitalize',
                                        activeGroup === group
                                            ? 'bg-primary/15 text-primary shadow-[0_0_15px_rgba(var(--primary),0.3)] border border-primary/20 backdrop-blur-sm'
                                            : 'hover:bg-white/10 hover:text-foreground'
                                    ]"
                                    @click="activeGroup = group"
                                >
                                    <Scale v-if="group === 'units'" class="h-4 w-4 mr-2" />
                                    <Calculator v-if="group === 'formulas'" class="h-4 w-4 mr-2" />
                                    {{ group }}
                                </Button>
                            </nav>
                        </aside>

                        <!-- Main Content -->
                        <div class="flex-1 p-6 lg:p-10 bg-white/5">
                            <!-- Formulas Tab -->
                            <FormulasTab 
                                v-if="activeGroup === 'formulas'" 
                                :settings="settings['formulas']" 
                                :form="form" 
                            />

                            <!-- Standard Settings Groups -->
                            <div v-else-if="activeGroup !== 'units'" class="max-w-2xl mx-auto space-y-8 animated-content" :key="activeGroup">
                                <div>
                                    <h3 class="text-lg font-medium capitalize">{{ activeGroup }} Settings</h3>
                                    <p class="text-sm text-muted-foreground mb-6">Update the {{ activeGroup }} configuration for the site.</p>
                                    
                                    <div class="space-y-6">
                                        <div v-for="setting in settings[activeGroup]" :key="setting.id" class="grid gap-2">
                                            <Label :for="setting.key">{{ setting.label || setting.key }}</Label>
                                            
                                            <!-- File/Image Input -->
                                            <div v-if="setting.type === 'image'" class="space-y-2">
                                                 <div v-if="previewImages[setting.key] || setting.value" class="border rounded-lg p-2 w-fit bg-muted/20">
                                                     <img :src="previewImages[setting.key] || setting.value" alt="Preview" class="h-16 object-contain" />
                                                 </div>
                                                 <Input 
                                                    :id="setting.key" 
                                                    type="file" 
                                                    accept="image/*"
                                                    @change="(e: Event) => handleFileChange(setting.key, e)" 
                                                />
                                                <p class="text-xs text-muted-foreground">Recommended: PNG or SVG with transparent background.</p>
                                            </div>

                                            <!-- Text Area -->
                                            <Textarea 
                                                v-else-if="setting.type === 'textarea'" 
                                                :id="setting.key" 
                                                v-model="form[setting.key]" 
                                                rows="4" 
                                            />
                                            
                                            <!-- Color Input -->
                                            <div v-else-if="setting.type === 'color'" class="flex gap-2 items-center">
                                                <Input 
                                                    :id="setting.key" 
                                                    type="color" 
                                                    v-model="form[setting.key]" 
                                                    class="w-12 h-12 p-1 cursor-pointer"
                                                />
                                                <Input 
                                                    type="text" 
                                                    v-model="form[setting.key]" 
                                                    class="w-32 font-mono"
                                                />
                                            </div>

                                            <!-- Select Input -->
                                            <div v-else-if="setting.type === 'select'" class="w-full">
                                                <Select v-model="form[setting.key]">
                                                    <SelectTrigger>
                                                        <SelectValue :placeholder="'Select ' + setting.label" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <template v-if="setting.key === 'default_theme'">
                                                            <SelectItem value="system">System</SelectItem>
                                                            <SelectItem value="light">Light</SelectItem>
                                                            <SelectItem value="dark">Dark</SelectItem>
                                                        </template>
                                                        <template v-else-if="setting.key === 'site_skin'">
                                                            <SelectItem value="default">Default</SelectItem>
                                                            <SelectItem value="forest">Forest</SelectItem>
                                                            <SelectItem value="elegant">Elegant</SelectItem>
                                                            <SelectItem value="cyber">Cyber</SelectItem>
                                                        </template>
                                                        <!-- Add generic select support if needed, or rely on other specific templates -->
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <!-- Email Input -->
                                            <Input 
                                                v-else-if="setting.type === 'email'"
                                                :id="setting.key" 
                                                type="email" 
                                                v-model="form[setting.key]" 
                                            />

                                            <!-- Default Text Input -->
                                            <Input 
                                                v-else 
                                                :id="setting.key" 
                                                type="text" 
                                                v-model="form[setting.key]" 
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Unit Conversions Section -->
                            <div v-else class="animated-content">
                                <div class="flex items-center justify-between mb-6">
                                    <div>
                                        <h3 class="text-lg font-medium">Unit Conversions</h3>
                                        <p class="text-sm text-muted-foreground">Manage measurement units and their conversion factors.</p>
                                    </div>
                                    <Button @click="openUnitDialog(null)">
                                        <Plus class="h-4 w-4 mr-2" /> Add Unit
                                    </Button>
                                </div>

                                <!-- Units Table -->
                                <div class="rounded-lg border overflow-hidden">
                                    <table class="w-full text-sm">
                                        <thead class="bg-muted/50">
                                            <tr>
                                                <th class="px-4 py-3 text-left font-medium">Name</th>
                                                <th class="px-4 py-3 text-left font-medium">Code</th>
                                                <th class="px-4 py-3 text-left font-medium">Category</th>
                                                <th class="px-4 py-3 text-right font-medium">Factor</th>
                                                <th class="px-4 py-3 text-center font-medium">Base</th>
                                                <th class="px-4 py-3 text-right font-medium">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                            <tr v-if="loadingUnits">
                                                <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                                    Loading units...
                                                </td>
                                            </tr>
                                            <tr v-else-if="unitConversions.length === 0">
                                                <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                                    No unit conversions found. Add one to get started.
                                                </td>
                                            </tr>
                                            <tr v-for="unit in unitConversions" :key="unit.id" class="hover:bg-muted/30">
                                                <td class="px-4 py-3 font-medium">
                                                    {{ unit.name }}
                                                    <span v-if="unit.alias" class="text-xs text-muted-foreground ml-1">({{ unit.alias }})</span>
                                                </td>
                                                <td class="px-4 py-3 font-mono text-xs">{{ unit.code }}</td>
                                                <td class="px-4 py-3">
                                                    <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', getCategoryColor(unit.category)]">
                                                        {{ unit.category }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-right font-mono">
                                                    {{ unit.to_base_factor }} → {{ unit.base_unit_code }}
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <span v-if="unit.is_base" class="text-green-600 font-bold">✓</span>
                                                    <span v-else class="text-muted-foreground">-</span>
                                                </td>
                                                <td class="px-4 py-3 text-right">
                                                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="openUnitDialog(unit)">
                                                        <Pencil class="h-4 w-4" />
                                                    </Button>
                                                    <Button variant="ghost" size="icon" class="h-8 w-8 text-red-500" @click="deleteUnit(unit)" :disabled="unit.is_base">
                                                        <Trash2 class="h-4 w-4" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Help Text -->
                                <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-950/20 rounded-lg border border-blue-200 dark:border-blue-800 text-sm">
                                    <h4 class="font-medium text-blue-800 dark:text-blue-300 mb-2">How Unit Conversions Work</h4>
                                    <ul class="text-blue-700 dark:text-blue-400 space-y-1 text-xs">
                                        <li>• Each category (volume, weight, count) has a <strong>base unit</strong> marked with ✓</li>
                                        <li>• The <strong>Factor</strong> is the multiplier to convert TO the base unit (e.g., 1 liter = 1000 ml)</li>
                                        <li>• Units are used in material loss calculations to convert raw quantities to finished units</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unit Dialog -->
        <Dialog :open="isUnitDialogOpen" @update:open="(val) => isUnitDialogOpen = val">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>{{ editingUnit ? 'Edit' : 'Add' }} Unit Conversion</DialogTitle>
                    <DialogDescription>
                        {{ editingUnit ? 'Update the unit conversion details.' : 'Create a new unit of measurement.' }}
                    </DialogDescription>
                </DialogHeader>
                
                <div class="grid gap-4 py-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="name">Name *</Label>
                            <Input id="name" v-model="unitForm.name" placeholder="e.g., Liters" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="code">Code *</Label>
                            <Input id="code" v-model="unitForm.code" placeholder="e.g., liters" class="font-mono" />
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="alias">Alias</Label>
                            <Input id="alias" v-model="unitForm.alias" placeholder="e.g., l" class="font-mono" />
                            <p class="text-xs text-muted-foreground">Alternative code (optional)</p>
                        </div>
                        <div class="grid gap-2">
                            <Label for="category">Category *</Label>
                            <Select v-model="unitForm.category">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select category" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="volume">Volume</SelectItem>
                                    <SelectItem value="weight">Weight</SelectItem>
                                    <SelectItem value="count">Count</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="to_base_factor">Conversion Factor *</Label>
                            <Input id="to_base_factor" v-model.number="unitForm.to_base_factor" type="number" step="0.000001" min="0.000001" />
                            <p class="text-xs text-muted-foreground">Multiplier to base unit</p>
                        </div>
                        <div class="grid gap-2">
                            <Label for="base_unit_code">Base Unit Code *</Label>
                            <Input id="base_unit_code" v-model="unitForm.base_unit_code" placeholder="e.g., ml" class="font-mono" />
                            <p class="text-xs text-muted-foreground">The base unit for this category</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="unitForm.is_base" class="rounded border-gray-300" />
                            <span class="text-sm">Is Base Unit</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="unitForm.active" class="rounded border-gray-300" />
                            <span class="text-sm">Active</span>
                        </label>
                    </div>
                </div>
                
                <DialogFooter>
                    <Button variant="ghost" @click="isUnitDialogOpen = false">Cancel</Button>
                    <Button @click="submitUnit" :disabled="!unitForm.name || !unitForm.code || !unitForm.base_unit_code">
                        {{ editingUnit ? 'Update' : 'Create' }} Unit
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
@keyframes blob {
  0% { transform: translate(0px, 0px) scale(1); }
  33% { transform: translate(30px, -50px) scale(1.1); }
  66% { transform: translate(-20px, 20px) scale(0.9); }
  100% { transform: translate(0px, 0px) scale(1); }
}

.animate-blob {
  animation: blob 8s infinite;
}

.animation-delay-2000 {
  animation-delay: 2s;
}

.glass-panel {
    background: rgba(255, 255, 255, 0.4);
}

.dark .glass-panel {
    background: rgba(0, 0, 0, 0.2);
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
}

.animated-content {
    animation: fadeIn 0.4s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
