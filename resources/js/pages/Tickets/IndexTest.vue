<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    tickets: any;
    filters: any;
}>();
</script>

<template>
    <AppLayout>
        <Head title="Tickets" />
        <div class="p-6">
            <h1 class="text-3xl font-bold">Tickets</h1>
            <p>Test page - if you see this, the route is working!</p>
            <pre>{{ tickets }}</pre>
        </div>
    </AppLayout>
</template>
