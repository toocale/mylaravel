<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';
import { Check } from 'lucide-vue-next';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/appearance';

const { currentThemeName, availableThemes, setTheme } = useTheme();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Appearance settings',
        href: edit().url,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Appearance settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Appearance settings"
                    description="Update your account's appearance settings"
                />
                <AppearanceTabs />

                <div class="pt-6 border-t">
                    <div class="mb-4">
                        <h3 class="text-sm font-medium">Theme Preference</h3>
                        <p class="text-sm text-muted-foreground">Choose your preferred visual theme.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div
                            v-for="theme in availableThemes"
                            :key="theme.name"
                            class="relative flex cursor-pointer rounded-lg border bg-card p-4 shadow-sm focus:outline-none"
                            :class="{ 'border-primary ring-1 ring-primary': currentThemeName === theme.name }"
                            @click="setTheme(theme.name as any)"
                        >
                            <div class="flex flex-1">
                                <div class="flex flex-col">
                                    <span class="block text-sm font-medium text-foreground">
                                        {{ theme.displayName }}
                                    </span>
                                    <span class="mt-1 flex items-center text-sm text-muted-foreground">
                                        {{ theme.description }}
                                    </span>
                                </div>
                            </div>
                            <div
                                v-if="currentThemeName === theme.name"
                                class="h-5 w-5 text-primary"
                            >
                                <Check class="h-5 w-5" />
                            </div>
                            <div
                                :class="`absolute -inset-px rounded-lg border-2 pointer-events-none ${currentThemeName === theme.name ? 'border-primary' : 'border-transparent'}`"
                                aria-hidden="true"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
