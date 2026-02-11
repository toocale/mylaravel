<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { MoreHorizontal, Target, Pencil, Trash2, Calendar, TrendingUp } from 'lucide-vue-next';

interface ProductionTarget {
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
    created_at: string;
    updated_at: string;
    machine: {
        id: number;
        name: string;
        line: {
            id: number;
            name: string;
            plant: {
                id: number;
                name: string;
            };
        };
    };
    shift: {
        id: number;
        name: string;
        type: string;
    } | null;
    creator: {
        id: number;
        name: string;
    } | null;
}

const props = defineProps<{
    targets: {
        data: ProductionTarget[];
        links: any[];
        from: number;
        to: number;
        total: number;
    };
    machines: Array<{
        id: number;
        name: string;
        line_name: string;
        plant_name: string;
    }>;
    shifts: Array<{
        id: number;
        name: string;
        type: string;
    }>;
    filters: {
        machine_id?: number;
        shift_id?: number;
        active_only?: boolean;
    };
}>();

// Filters
const machineFilter = ref(props.filters.machine_id || null);
const shiftFilter = ref(props.filters.shift_id || null);
const activeOnlyFilter = ref(props.filters.active_only || false);

const applyFilters = () => {
    router.get('/admin/targets', {
        machine_id: machineFilter.value,
        shift_id: shiftFilter.value,
        active_only: activeOnlyFilter.value ? 1 : 0,
    }, { preserveState: true, replace: true });
};

const clearFilters = () => {
    machineFilter.value = null;
    shiftFilter.value = null;
    activeOnlyFilter.value = false;
    router.get('/admin/targets', {}, { preserveState: true, replace: true });
};

// Delete Target
const targetToDelete = ref<ProductionTarget | null>(null);
const isDeleteDialogOpen = ref(false);

const confirmDelete = (target: ProductionTarget) => {
    targetToDelete.value = target;
    isDeleteDialogOpen.value = true;
};

const deleteTarget = () => {
    if (targetToDelete.value) {
        router.delete(`/admin/targets/${targetToDelete.value.id}`, {
            onSuccess: () => {
                isDeleteDialogOpen.value = false;
                targetToDelete.value = null;
            },
        });
    }
};

// Helper functions
const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const isActiveTarget = (target: ProductionTarget) => {
    const now = new Date();
    const from = new Date(target.effective_from);
    const to = target.effective_to ? new Date(target.effective_to) : null;
    
    return from <= now && (!to || to >= now);
};

const getTargetColor = (value: number | null) => {
    if (!value) return 'text-muted-foreground';
    if (value >= 90) return 'text-green-600';
    if (value >= 80) return 'text-yellow-600';
    return 'text-orange-600';
};

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Production Targets', href: '/admin/targets' },
];
</script>

<template>
    <Head title="Production Targets" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6 space-y-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight">Production Targets & Goals</h2>
                    <p class="text-muted-foreground">Set and manage OEE targets for machines and shifts.</p>
                </div>
                <Button as-child>
                    <Link href="/admin/targets/create">
                        <Target class="mr-2 h-4 w-4" /> Set New Target
                    </Link>
                </Button>
            </div>

            <!-- Filters -->
            <div class="bg-card border rounded-lg p-4 space-y-3">
                <div class="flex flex-wrap gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <label class="text-sm font-medium mb-2 block">Machine</label>
                        <select 
                            v-model="machineFilter" 
                            @change="applyFilters"
                            class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option :value="null">All Machines</option>
                            <option v-for="machine in machines" :key="machine.id" :value="machine.id">
                                {{ machine.plant_name }} › {{ machine.line_name }} › {{ machine.name }}
                            </option>
                        </select>
                    </div>

                    <div class="flex-1 min-w-[200px]">
                        <label class="text-sm font-medium mb-2 block">Shift</label>
                        <select 
                            v-model="shiftFilter" 
                            @change="applyFilters"
                            class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option :value="null">All Shifts</option>
                            <option v-for="shift in shifts" :key="shift.id" :value="shift.id">
                                {{ shift.name }} ({{ shift.type }})
                            </option>
                        </select>
                    </div>

                    <div class="flex items-end">
                        <label class="flex items-center gap-2 h-10">
                            <input 
                                type="checkbox" 
                                v-model="activeOnlyFilter" 
                                @change="applyFilters"
                                class="h-4 w-4 rounded border-gray-300"
                            />
                            <span class="text-sm font-medium">Active Only</span>
                        </label>
                    </div>

                    <div class="flex items-end">
                        <Button variant="outline" size="sm" @click="clearFilters">Clear</Button>
                    </div>
                </div>
            </div>

            <!-- Targets Table -->
            <div class="rounded-md border bg-card overflow-hidden">
                <div class="w-full overflow-auto">
                    <table class="w-full caption-bottom text-sm text-left">
                        <thead class="[&_tr]:border-b">
                            <tr class="border-b transition-colors hover:bg-muted/50">
                                <th class="h-12 px-4 align-middle font-medium text-muted-foreground">Machine</th>
                                <th class="h-12 px-4 align-middle font-medium text-muted-foreground">Shift</th>
                                <th class="h-12 px-4 align-middle font-medium text-muted-foreground">Period</th>
                                <th class="h-12 px-4 align-middle font-medium text-muted-foreground text-center">OEE</th>
                                <th class="h-12 px-4 align-middle font-medium text-muted-foreground text-center">A</th>
                                <th class="h-12 px-4 align-middle font-medium text-muted-foreground text-center">P</th>
                                <th class="h-12 px-4 align-middle font-medium text-muted-foreground text-center">Q</th>
                                <th class="h-12 px-4 align-middle font-medium text-muted-foreground">Status</th>
                                <th class="h-12 px-4 align-middle font-medium text-muted-foreground text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="[&_tr:last-child]:border-0">
                            <tr v-if="targets.data.length === 0">
                                <td colspan="9" class="p-8 text-center text-muted-foreground">
                                    <Target class="h-12 w-12 mx-auto mb-2 opacity-20" />
                                    <p>No production targets found.</p>
                                    <p class="text-xs mt-1">Create your first target to start tracking performance goals.</p>
                                </td>
                            </tr>
                            <tr v-for="target in targets.data" :key="target.id" class="border-b transition-colors hover:bg-muted/50">
                                <td class="p-4 align-middle">
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ target.machine.name }}</span>
                                        <span class="text-xs text-muted-foreground">
                                            {{ target.machine.line.plant.name }} › {{ target.machine.line.name }}
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4 align-middle">
                                    <Badge v-if="target.shift" variant="secondary" class="text-xs">
                                        {{ target.shift.name }}
                                    </Badge>
                                    <span v-else class="text-xs text-muted-foreground">All Shifts</span>
                                </td>
                                <td class="p-4 align-middle">
                                    <div class="flex flex-col text-xs">
                                        <span class="flex items-center gap-1">
                                            <Calendar class="h-3 w-3" />
                                            {{ formatDate(target.effective_from) }}
                                        </span>
                                        <span class="text-muted-foreground">
                                            to {{ target.effective_to ? formatDate(target.effective_to) : 'Ongoing' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4 align-middle text-center">
                                    <span v-if="target.target_oee" :class="getTargetColor(target.target_oee)" class="font-semibold">
                                        {{ target.target_oee }}%
                                    </span>
                                    <span v-else class="text-muted-foreground text-xs">-</span>
                                </td>
                                <td class="p-4 align-middle text-center">
                                    <span v-if="target.target_availability" class="text-xs text-muted-foreground">
                                        {{ target.target_availability }}%
                                    </span>
                                    <span v-else class="text-muted-foreground text-xs">-</span>
                                </td>
                                <td class="p-4 align-middle text-center">
                                    <span v-if="target.target_performance" class="text-xs text-muted-foreground">
                                        {{ target.target_performance }}%
                                    </span>
                                    <span v-else class="text-muted-foreground text-xs">-</span>
                                </td>
                                <td class="p-4 align-middle text-center">
                                    <span v-if="target.target_quality" class="text-xs text-muted-foreground">
                                        {{ target.target_quality }}%
                                    </span>
                                    <span v-else class="text-muted-foreground text-xs">-</span>
                                </td>
                                <td class="p-4 align-middle">
                                    <Badge :variant="isActiveTarget(target) ? 'default' : 'outline'" class="text-xs">
                                        {{ isActiveTarget(target) ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </td>
                                <td class="p-4 align-middle text-right">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="icon" class="h-8 w-8 p-0">
                                                <MoreHorizontal class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem as-child>
                                                <Link :href="`/admin/targets/${target.id}/edit`">
                                                    <Pencil class="mr-2 h-4 w-4" /> Edit
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem @click="confirmDelete(target)" class="text-red-600">
                                                <Trash2 class="mr-2 h-4 w-4" /> Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div class="flex items-center justify-between" v-if="targets.links.length > 3">
                <div class="text-sm text-muted-foreground">
                    Showing {{ targets.from }} to {{ targets.to }} of {{ targets.total }} targets
                </div>
                <div class="flex gap-2">
                    <Button
                        v-for="(link, i) in targets.links"
                        :key="i"
                        :variant="link.active ? 'default' : 'outline'"
                        :disabled="!link.url"
                        as-child
                        size="sm"
                    >
                        <Link v-if="link.url" :href="link.url" v-html="link.label" />
                        <span v-else v-html="link.label"></span>
                    </Button>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Target?</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete this production target? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="isDeleteDialogOpen = false">Cancel</Button>
                    <Button variant="destructive" @click="deleteTarget">Delete Target</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </AppLayout>
</template>
