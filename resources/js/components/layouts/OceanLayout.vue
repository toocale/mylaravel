<script setup lang="ts">
import { useSlots } from 'vue';

const slots = useSlots();
</script>

<template>
    <!-- Ocean Theme: 3-Column Responsive Grid with Glassmorphic Cards -->
    <div class="ocean-layout min-h-screen bg-gradient-to-br from-blue-50 via-cyan-50 to-teal-50 dark:from-gray-900 dark:via-blue-950 dark:to-cyan-950">
        <!-- Header Section - Full Width -->
        <div class="sticky top-0 z-40 backdrop-blur-xl bg-white/70 dark:bg-gray-900/70 border-b border-blue-100 dark:border-blue-900/50">
            <slot name="header" />
        </div>

        <!-- Main Content - 3 Column Grid -->
        <div class="container mx-auto px-4 py-6 max-w-7xl">
            <!-- KPI Section - Full Width Row -->
            <div v-if="slots.kpis" class="mb-8">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
                    <slot name="kpis" />
                </div>
            </div>

            <!-- Main Content - 3 Columns -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column - Drilling/Navigation (if provided) -->
                <div v-if="slots.drilldown" class="lg:col-span-3">
                    <slot name="drilldown" />
                </div>

                <!-- Charts - 2 columns on large screens -->
                <div v-if="slots.charts" class="lg:col-span-2">
                    <slot name="charts" />
                </div>

                <!-- Sidebar - 1 column -->
                <div v-if="slots.sidebar" class="lg:col-span-1">
                    <slot name="sidebar" />
                </div>

                <!-- Default Content - Full Width -->
                <div v-if="slots.default" class="lg:col-span-3">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.ocean-layout {
    font-family: 'Inter', sans-serif;
}

/* Glassmorphic card effect for ocean theme */
.ocean-layout :deep(.card),
.ocean-layout :deep([class*="card"]) {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15);
}

.dark .ocean-layout :deep(.card),
.dark .ocean-layout :deep([class*="card"]) {
    background: rgba(30, 41, 59, 0.7);
    border: 1px solid rgba(100, 116, 139, 0.3);
}
</style>
