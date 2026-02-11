<script setup lang="ts">
import { useSlots } from 'vue';

const slots = useSlots();
</script>

<template>
    <!-- Minimal Theme: Single Column List Layout with Generous Spacing -->
    <div class="minimal-layout min-h-screen bg-white dark:bg-gray-950">
        <!-- Header Section - Minimal -->
        <div class="sticky top-0 z-40 bg-white/80 dark:bg-gray-950/80 backdrop-blur-sm border-b border-gray-200 dark:border-gray-800">
            <slot name="header" />
        </div>

        <!-- Main Content - Single Column, List Style -->
        <div class="container mx-auto px-6 py-12 max-w-5xl">
            <!-- KPI Section - Horizontal Scroll on Mobile, Grid on Desktop -->
            <div v-if="slots.kpis" class="mb-12">
                <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-6">Key Metrics</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                    <slot name="kpis" />
                </div>
            </div>

            <!-- Drilldown Section - List Style -->
            <div v-if="slots.drilldown" class="mb-12">
                <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-6">Breakdown</h2>
                <div class="space-y-4">
                    <slot name="drilldown" />
                </div>
            </div>

            <!-- Charts - Stacked Vertically with Generous Spacing -->
            <div v-if="slots.charts" class="space-y-12">
                <slot name="charts" />
            </div>

            <!-- Sidebar -Stacked Below -->
            <div v-if="slots.sidebar" class="mt-12">
                <slot name="sidebar" />
            </div>

            <!-- Default Content -->
            <div v-if="slots.default" class="mt-12">
                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
.minimal-layout {
    font-family: 'Inter', sans-serif;
}

/* Minimal card styling - clean, flat, lots of space */
.minimal-layout :deep(.card),
.minimal-layout :deep([class*="card"]) {
    background: white;
    border: 1px solid rgba(229, 231, 235, 1) !important;
    box-shadow: none !important;
    border-radius: 0.5rem;
    padding: 2rem;
}

.dark .minimal-layout :deep(.card),
.dark .minimal-layout :deep([class*="card"]) {
    background: rgba(17, 24, 39, 1);
    border-color: rgba(55, 65, 81, 1) !important;
}

.minimal-layout :deep(.card):hover {
    border-color: rgba(167, 139, 250, 0.5);
}

/* Generous spacing */
.minimal-layout :deep(h1),
.minimal-layout :deep(h2),
.minimal-layout :deep(h3) {
    margin-bottom: 1.5rem;
}

.minimal-layout :deep(p) {
    line-height: 1.8;
}

/* Clean section dividers */
.minimal-layout section + section {
    margin-top: 4rem;
    padding-top: 2rem;
    border-top: 1px solid rgba(229, 231, 235, 0.5);
}
</style>
