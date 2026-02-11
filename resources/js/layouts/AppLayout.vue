<script setup lang="ts">
import AppLayout from '@/layouts/app/AppHeaderLayout.vue';
import Toaster from '@/components/ui/Toaster.vue';
import Toast from '@/components/ui/toast/Toast.vue';
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
    <AppLayout :breadcrumbs="breadcrumbs" :fluid="fluid">
        <slot />
    </AppLayout>
    <Toaster />
    <Toast />
</template>
