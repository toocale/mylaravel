<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import AndonBoard from '@/components/AndonBoard.vue';
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Andon Board', href: '/andon' }]" :fluid="true">
        <AndonBoard />
    </AppLayout>
</template>
