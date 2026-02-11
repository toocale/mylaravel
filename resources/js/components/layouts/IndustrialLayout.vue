<script setup lang="ts">
import { useSlots } from 'vue';

const slots = useSlots();
</script>

<template>
    <!-- Industrial Theme: Masonry/Variable Height Grid with Dark Background -->
    <div class="industrial-layout min-h-screen bg-gradient-to-br from-gray-900 via-gray-800 to-black">
        <!-- Header Section -->
        <div class="sticky top-0 z-40 bg-gray-900/95 backdrop-blur border-b-2 border-orange-500/50 shadow-lg shadow-orange-500/20">
            <slot name="header" />
        </div>

        <!-- Main Content - Masonry Style -->
        <div class="container mx-auto px-4 py-6 max-w-7xl">
            <!-- KPI Section - 4 Columns, Variable Heights -->
            <div v-if="slots.kpis" class="mb-8">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 auto-rows-auto">
                    <slot name="kpis" />
                </div>
            </div>

            <!-- Drilldown Section - Full Width -->
            <div v-if="slots.drilldown" class="mb-6">
                <slot name="drilldown" />
            </div>

            <!-- Charts - Masonry Grid: Different Heights -->
            <div v-if="slots.charts" class="grid grid-cols-1 lg:grid-cols-2 gap-6 auto-rows-auto">
                <slot name="charts" />
            </div>

            <!-- Sidebar - Stacks Below on Mobile -->
            <div v-if="slots.sidebar" class="mt-6">
                <slot name="sidebar" />
            </div>

            <!-- Default Content -->
            <div v-if="slots.default">
                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
.industrial-layout {
    font-family: 'Roboto', 'Roboto Condensed', sans-serif;
    letter-spacing: 0.02em;
}

/* Industrial card styling - sharp, high contrast */
.industrial-layout :deep(.card),
.industrial-layout :deep([class*="card"]) {
    background: linear-gradient(135deg, rgba(31, 31, 31, 0.95), rgba(20, 20, 20, 0.95));
    border: 2px solid rgba(251, 146, 60, 0.3) !important;
    box-shadow: 
        0 0 20px rgba(251, 146, 60, 0.1),
        0 4px 6px -1px rgba(0, 0, 0, 0.5),
        inset 0 1px 0 rgba(251, 146, 60, 0.1);
    border-radius: 0.375rem;
}

.industrial-layout :deep(.card):hover {
    border-color: rgba(251, 146, 60, 0.6);
    box-shadow: 
        0 0 30px rgba(251, 146, 60, 0.3),
        0 10px 15px -3px rgba(0, 0, 0, 0.5),
        inset 0 1px 0 rgba(251, 146, 60, 0.2);
}

/* Variable heights for masonry effect */
.industrial-layout :deep(.card:nth-child(1)) {
    min-height: 220px;
}

.industrial-layout :deep(.card:nth-child(2)) {
    min-height: 180px;
}

.industrial-layout :deep(.card:nth-child(3)) {
    min-height: 200px;
}
</style>
