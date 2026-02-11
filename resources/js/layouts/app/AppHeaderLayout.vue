<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    fluid?: boolean;
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    fluid: false,
});
</script>

<template>
    <AppShell class="flex-col">
        <AppHeader :breadcrumbs="breadcrumbs" />
        <AppContent :fluid="fluid">
            <slot />
        </AppContent>
    </AppShell>
</template>
