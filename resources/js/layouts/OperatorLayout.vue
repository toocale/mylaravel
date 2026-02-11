<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useTerminology } from '@/composables/useTerminology';
import { Monitor, LogOut, Moon, Sun } from 'lucide-vue-next';
import { useDark, useToggle } from '@vueuse/core';

const { siteName } = useTerminology();
const isDark = useDark();
const toggleDark = useToggle(isDark);
</script>

<template>
    <div class="min-h-screen bg-slate-50 dark:bg-slate-950 flex flex-col font-sans">
        <!-- Minimal Header -->
        <header class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-6 py-3 flex items-center justify-between shadow-sm z-10">
            <div class="flex items-center gap-3">
                <div class="bg-blue-600 p-2 rounded-lg">
                    <Monitor class="h-6 w-6 text-white" />
                </div>
                <div>
                    <h1 class="text-xl font-bold bg-gradient-to-r from-blue-700 to-cyan-600 bg-clip-text text-transparent">
                        {{ siteName }} Operator
                    </h1>
                    <p class="text-xs text-muted-foreground">Kiosk Mode</p>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button @click="toggleDark()" class="p-3 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    <component :is="isDark ? Sun : Moon" class="h-6 w-6 text-slate-600 dark:text-slate-400" />
                </button>
                <div class="h-8 w-px bg-slate-200 dark:bg-slate-800"></div>
                <!-- TODO: User Avatar/Name if logged in -->
                <Link href="/dashboard" class="flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                    <LogOut class="h-5 w-5" />
                    Exit Kiosk
                </Link>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 relative overflow-hidden flex flex-col">
            <slot />
        </main>
    </div>
</template>
