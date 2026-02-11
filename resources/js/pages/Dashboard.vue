<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import OeeDashboard from '@/components/OeeDashboard.vue';
import { useTheme } from '@/composables/useTheme';
import OceanLayout from '@/components/layouts/OceanLayout.vue';
import IndustrialLayout from '@/components/layouts/IndustrialLayout.vue';
import MinimalLayout from '@/components/layouts/MinimalLayout.vue';

const { isOcean, isIndustrial, isMinimal } = useTheme();

const props = defineProps<{
    initialContext?: {
        plantId: number | null;
        lineId: number | null;
        machineId: number | null;
    }
}>();

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
];
</script>

<template>
    <Head title="OEE Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Ocean Theme Layout -->
        <OceanLayout v-if="isOcean">
            <template #default>
                <OeeDashboard :initialContext="props.initialContext" />
            </template>
        </OceanLayout>

        <!-- Industrial Theme Layout -->
        <IndustrialLayout v-else-if="isIndustrial">
            <template #default>
                <OeeDashboard :initialContext="props.initialContext" />
            </template>
        </IndustrialLayout>

        <!-- Minimal Theme Layout -->
        <MinimalLayout v-else-if="isMinimal">
            <template #default>
                <OeeDashboard :initialContext="props.initialContext" />
            </template>
        </MinimalLayout>

        <!-- Default Layout -->
        <div v-else class="flex flex-1 flex-col gap-4 p-4 md:gap-8 md:p-8">
            <OeeDashboard :initialContext="props.initialContext" />
        </div>
    </AppLayout>
</template>
