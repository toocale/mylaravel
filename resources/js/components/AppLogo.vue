<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
// Access shared site settings from HandleInertiaRequests
const site = computed(() => (page.props.site || {}) as Record<string, string>);

// Format the logo URL to ensure it works correctly
const logoUrl = computed(() => {
    if (!site.value.site_logo) return null;
    const logo = site.value.site_logo;
    // If it's already a full URL or starts with /, use as-is
    if (logo.startsWith('http') || logo.startsWith('/')) {
        return logo;
    }
    // Otherwise, prepend /storage/
    return `/storage/${logo}`;
});
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground overflow-hidden"
    >
        <img v-if="logoUrl" :src="logoUrl" alt="Logo" class="size-full object-cover" />
        <AppLogoIcon v-else class="size-5 fill-current text-white dark:text-black" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm max-w-[150px] hidden sm:grid">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{ site.site_name || 'Vicoee' }}</span>
    </div>
</template>
