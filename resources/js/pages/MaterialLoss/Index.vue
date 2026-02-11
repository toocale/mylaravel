<script setup lang="ts">
import { ref, watch, PropType } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Loader2, Plus, Filter, Calendar as CalendarIcon, PackageX, Scale } from 'lucide-vue-next';

interface Category {
    id: number;
    name: string;
}

const props = defineProps({
    losses: Object as PropType<any>,
    categories: Array as PropType<Category[]>,
    filters: Object,
});

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Material Loss', href: '/material-loss' },
];

const filters = ref({
    category_id: props.filters?.category_id || '',
    start_date: props.filters?.start_date || '',
    end_date: props.filters?.end_date || '',
});

const applyFilters = () => {
    router.get('/material-loss', filters.value, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    filters.value = {
        category_id: '',
        start_date: '',
        end_date: '',
    };
    applyFilters();
};

</script>

<template>
    <Head title="Material Loss" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6 space-y-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-3xl font-bold tracking-tight">Material Loss Tracking</h2>
                    <p class="text-muted-foreground">Monitor and analyze waste, spillage, and production losses.</p>
                </div>
            </div>

            <!-- Filters -->
            <Card>
                <CardContent class="p-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                        <div class="grid gap-2">
                            <Label>Category</Label>
                            <select v-model="filters.category_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                <option value="">All Categories</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label>Start Date</Label>
                            <Input type="date" v-model="filters.start_date" />
                        </div>
                        <div class="grid gap-2">
                            <Label>End Date</Label>
                            <Input type="date" v-model="filters.end_date" />
                        </div>
                        <div class="flex gap-2">
                            <Button variant="outline" @click="applyFilters" class="flex-1">
                                <Filter class="mr-2 h-4 w-4" /> Filter
                            </Button>
                            <Button variant="ghost" @click="clearFilters">Clear</Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Losses Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Recorded Losses</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="rounded-md border">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th class="p-4 font-medium">Date & Time</th>
                                    <th class="p-4 font-medium">Category</th>
                                    <th class="p-4 font-medium">Product / Machine</th>
                                    <th class="p-4 font-medium text-right">Quantity</th>
                                    <th class="p-4 font-medium text-right">Equivalent Units</th>
                                    <th class="p-4 font-medium">Recorded By</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="loss in losses.data" :key="loss.id" class="hover:bg-muted/5">
                                    <td class="p-4">
                                        <div class="font-medium">{{ new Date(loss.occurred_at).toLocaleDateString() }}</div>
                                        <div class="text-xs text-muted-foreground">{{ new Date(loss.occurred_at).toLocaleTimeString() }}</div>
                                    </td>
                                    <td class="p-4">
                                        <Badge variant="outline">{{ loss.category?.name }}</Badge>
                                        <div class="text-xs text-muted-foreground mt-1">{{ loss.reason }}</div>
                                    </td>
                                    <td class="p-4">
                                        <div v-if="loss.product" class="font-medium">{{ loss.product.name }}</div>
                                        <div v-if="loss.machine" class="text-xs text-muted-foreground">{{ loss.machine.name }}</div>
                                        <div v-if="!loss.product && !loss.machine" class="text-xs italic text-muted-foreground">General Loss</div>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div class="font-bold">{{ loss.quantity }} <span class="text-xs font-normal text-muted-foreground">{{ loss.unit }}</span></div>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div v-if="loss.equivalent_units !== null" class="flex items-center justify-end gap-1 text-orange-600 font-medium">
                                            <Scale class="h-3 w-3" />
                                            {{ loss.equivalent_units }}
                                        </div>
                                        <div v-else class="text-xs text-muted-foreground">-</div>
                                    </td>
                                    <td class="p-4 text-xs">
                                        {{ loss.recorder?.name || 'Unknown' }}
                                    </td>
                                </tr>
                                <tr v-if="!losses?.data || losses.data.length === 0">
                                    <td colspan="6" class="p-8 text-center text-muted-foreground">
                                        <div class="flex flex-col items-center gap-2">
                                            <PackageX class="h-8 w-8 opacity-20" />
                                            <p>No material loss records found.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
