<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ArrowLeft, Target as TargetIcon } from 'lucide-vue-next';

const props = defineProps<{
    target: {
        id: number;
        machine_id: number;
        shift_id: number | null;
        effective_from: string;
        effective_to: string | null;
        target_oee: number | null;
        target_availability: number | null;
        target_performance: number | null;
        target_quality: number | null;
        target_units: number | null;
        target_good_units: number | null;
        notes: string | null;
    };
    machines: Array<{
        id: number;
        name: string;
        line_name: string;
        plant_name: string;
        display_name: string;
    }>;
    shifts: Array<{
        id: number;
        name: string;
        type: string;
    }>;
}>();

const form = useForm({
    machine_id: props.target.machine_id,
    shift_id: props.target.shift_id,
    effective_from: props.target.effective_from,
    effective_to: props.target.effective_to || '',
    target_oee: props.target.target_oee || 85,
    target_availability: props.target.target_availability || 90,
    target_performance: props.target.target_performance || 95,
    target_quality: props.target.target_quality || 99,
    target_units: props.target.target_units || undefined,
    target_good_units: props.target.target_good_units || undefined,
    notes: props.target.notes || '',
});

const submit = () => {
    form.put(`/admin/targets/${props.target.id}`, {
        onSuccess: () => {
            // Redirect handled by controller
        },
    });
};

const calculateOEE = computed(() => {
    const a = form.target_availability || 0;
    const p = form.target_performance || 0;
    const q = form.target_quality || 0;
    
    return ((a * p * q) / 10000).toFixed(1);
});

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Production Targets', href: '/admin/targets' },
    { title: 'Edit Target', href: `/admin/targets/${props.target.id}/edit` },
];
</script>

<template>
    <Head title="Edit Production Target" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6 max-w-4xl mx-auto space-y-6">
            <div class="flex items-center gap-4">
                <Button variant="outline" size="icon" as-child>
                    <Link href="/admin/targets">
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </Button>
                <div>
                    <h2 class="text-2xl font-bold tracking-tight">Edit Production Target</h2>
                    <p class="text-muted-foreground">Update performance goals for this machine and shift.</p>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Machine & Shift Selection -->
                <Card>
                    <CardHeader>
                        <CardTitle>Target Scope</CardTitle>
                        <CardDescription>Select which machine and shift this target applies to</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="machine_id">Machine <span class="text-red-500">*</span></Label>
                            <select 
                                id="machine_id"
                                v-model="form.machine_id" 
                                required
                                class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option :value="null" disabled>Select a machine...</option>
                                <option v-for="machine in machines" :key="machine.id" :value="machine.id">
                                    {{ machine.display_name }}
                                </option>
                            </select>
                            <span v-if="form.errors.machine_id" class="text-xs text-red-500">{{ form.errors.machine_id }}</span>
                        </div>

                        <div class="space-y-2">
                            <Label for="shift_id">Shift (Optional)</Label>
                            <select 
                                id="shift_id"
                                v-model="form.shift_id"
                                class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option :value="null">All Shifts</option>
                                <option v-for="shift in shifts" :key="shift.id" :value="shift.id">
                                    {{ shift.name }} ({{ shift.type }})
                                </option>
                            </select>
                            <p class="text-xs text-muted-foreground">Leave blank to apply to all shifts, or select a specific shift for targeted goals.</p>
                            <span v-if="form.errors.shift_id" class="text-xs text-red-500">{{ form.errors.shift_id }}</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Date Range -->
                <Card>
                    <CardHeader>
                        <CardTitle>Effective Period</CardTitle>
                        <CardDescription>Set when this target is active</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="effective_from">From Date <span class="text-red-500">*</span></Label>
                                <Input 
                                    id="effective_from"
                                    type="date" 
                                    v-model="form.effective_from" 
                                    required
                                />
                                <span v-if="form.errors.effective_from" class="text-xs text-red-500">{{ form.errors.effective_from }}</span>
                            </div>

                            <div class="space-y-2">
                                <Label for="effective_to">To Date (Optional)</Label>
                                <Input 
                                    id="effective_to"
                                    type="date" 
                                    v-model="form.effective_to"
                                />
                                <p class="text-xs text-muted-foreground">Leave blank for ongoing target</p>
                                <span v-if="form.errors.effective_to" class="text-xs text-red-500">{{ form.errors.effective_to }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- OEE Targets -->
                <Card>
                    <CardHeader>
                        <CardTitle>OEE Performance Targets</CardTitle>
                        <CardDescription>Set target percentages for each OEE component (0-100%)</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- OEE Target -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <Label for="target_oee">Overall OEE Target</Label>
                                <span class="text-2xl font-bold text-blue-600">{{ form.target_oee }}%</span>
                            </div>
                            <input 
                                id="target_oee"
                                type="range" 
                                v-model.number="form.target_oee" 
                                min="0" 
                                max="100" 
                                step="1"
                                class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-blue-600"
                            />
                            <Input 
                                type="number" 
                                v-model.number="form.target_oee" 
                                min="0" 
                                max="100" 
                                step="0.1"
                                class="w-32"
                            />
                            <span v-if="form.errors.target_oee" class="text-xs text-red-500">{{ form.errors.target_oee }}</span>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <!-- Availability -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <Label for="target_availability" class="text-sm">Availability</Label>
                                    <span class="text-lg font-semibold text-green-600">{{ form.target_availability }}%</span>
                                </div>
                                <input 
                                    id="target_availability"
                                    type="range" 
                                    v-model.number="form.target_availability" 
                                    min="0" 
                                    max="100" 
                                    class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-green-600"
                                />
                                <Input 
                                    type="number" 
                                    v-model.number="form.target_availability" 
                                    min="0" 
                                    max="100" 
                                    step="0.1"
                                    class="w-full"
                                />
                                <span v-if="form.errors.target_availability" class="text-xs text-red-500">{{ form.errors.target_availability }}</span>
                            </div>

                            <!-- Performance -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <Label for="target_performance" class="text-sm">Performance</Label>
                                    <span class="text-lg font-semibold text-yellow-600">{{ form.target_performance }}%</span>
                                </div>
                                <input 
                                    id="target_performance"
                                    type="range" 
                                    v-model.number="form.target_performance" 
                                    min="0" 
                                    max="100" 
                                    class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-yellow-600"
                                />
                                <Input 
                                    type="number" 
                                    v-model.number="form.target_performance" 
                                    min="0" 
                                    max="100" 
                                    step="0.1"
                                    class="w-full"
                                />
                                <span v-if="form.errors.target_performance" class="text-xs text-red-500">{{ form.errors.target_performance }}</span>
                            </div>

                            <!-- Quality -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <Label for="target_quality" class="text-sm">Quality</Label>
                                    <span class="text-lg font-semibold text-purple-600">{{ form.target_quality }}%</span>
                                </div>
                                <input 
                                    id="target_quality"
                                    type="range" 
                                    v-model.number="form.target_quality" 
                                    min="0" 
                                    max="100" 
                                    class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-purple-600"
                                />
                                <Input 
                                    type="number" 
                                    v-model.number="form.target_quality" 
                                    min="0" 
                                    max="100" 
                                    step="0.1"
                                    class="w-full"
                                />
                                <span v-if="form.errors.target_quality" class="text-xs text-red-500">{{ form.errors.target_quality }}</span>
                            </div>
                        </div>

                        <!-- Calculated OEE Info -->
                        <div class="bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <div class="flex items-center gap-2">
                                <TargetIcon class="h-5 w-5 text-blue-600" />
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-blue-900 dark:text-blue-100">Calculated OEE from Components</p>
                                    <p class="text-xs text-blue-700 dark:text-blue-300">
                                        {{ form.target_availability }}% × {{ form.target_performance }}% × {{ form.target_quality }}% = {{ calculateOEE }}%
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Unit Targets (Optional) -->
                <Card>
                    <CardHeader>
                        <CardTitle>Production Volume Targets (Optional)</CardTitle>
                        <CardDescription>Set expected unit production per shift</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="target_units">Total Units Target</Label>
                                <Input 
                                    id="target_units"
                                    type="number" 
                                    v-model.number="form.target_units" 
                                    min="0"
                                    placeholder="e.g., 1000"
                                />
                                <p class="text-xs text-muted-foreground">Expected total production (good + reject)</p>
                                <span v-if="form.errors.target_units" class="text-xs text-red-500">{{ form.errors.target_units }}</span>
                            </div>

                            <div class="space-y-2">
                                <Label for="target_good_units">Good Units Target</Label>
                                <Input 
                                    id="target_good_units"
                                    type="number" 
                                    v-model.number="form.target_good_units" 
                                    min="0"
                                    placeholder="e.g., 980"
                                />
                                <p class="text-xs text-muted-foreground">Expected good quality units</p>
                                <span v-if="form.errors.target_good_units" class="text-xs text-red-500">{{ form.errors.target_good_units }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Notes -->
                <Card>
                    <CardHeader>
                        <CardTitle>Notes (Optional)</CardTitle>
                        <CardDescription>Add context or reasoning for this target</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Textarea 
                            v-model="form.notes" 
                            placeholder="e.g., Q1 2025 target based on equipment upgrade..."
                            rows="3"
                        />
                        <span v-if="form.errors.notes" class="text-xs text-red-500">{{ form.errors.notes }}</span>
                    </CardContent>
                </Card>

                <!-- Submit -->
                <div class="flex justify-end gap-2">
                    <Button variant="outline" type="button" as-child>
                        <Link href="/admin/targets">Cancel</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        Save Changes
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
