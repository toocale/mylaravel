<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage();
const siteLogo = computed(() => (page.props.site as any)?.site_logo || null);
const siteName = computed(() => (page.props.site as any)?.site_name || page.props.name || 'App');
</script>

<template>
    <div class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden bg-background">
        <!-- Animated Background Mesh -->
        <div class="absolute inset-0 z-0 opacity-60 dark:opacity-40 pointer-events-none">
            <div class="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] rounded-full bg-blue-500/40 blur-[100px] animate-blob"></div>
            <div class="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] rounded-full bg-purple-500/40 blur-[100px] animate-blob animation-delay-2000"></div>
            <div class="absolute top-[40%] left-[40%] w-[40%] h-[40%] rounded-full bg-emerald-500/30 blur-[100px] animate-blob animation-delay-4000"></div>
        </div>

        <!-- Glass Card -->
        <div class="w-full max-w-sm relative z-10">
            <div class="glass-panel p-8 md:p-10 rounded-3xl border border-white/20 shadow-2xl backdrop-blur-xl">
                <div class="flex flex-col gap-8">
                    <div class="flex flex-col items-center gap-4 text-center">
                        <Link href="/" class="transition-transform hover:scale-105">
                            <div v-if="siteLogo" class="flex items-center justify-center">
                                <img :src="siteLogo" :alt="siteName" class="h-16 w-auto max-w-[200px] object-contain" />
                            </div>
                            <div v-else class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <svg class="size-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M13 2L3 14H12L11 22L21 10H12L13 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                        </Link>
                        <div class="space-y-1">
                            <h1 class="text-2xl font-bold tracking-tight bg-gradient-to-br from-foreground to-muted-foreground bg-clip-text text-transparent">
                                {{ title }}
                            </h1>
                            <p class="text-sm text-muted-foreground">
                                {{ description }}
                            </p>
                        </div>
                    </div>
                    
                    <!-- Slot Content -->
                    <div class="auth-content">
                        <slot />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes blob {
  0% { transform: translate(0px, 0px) scale(1); }
  33% { transform: translate(30px, -50px) scale(1.1); }
  66% { transform: translate(-20px, 20px) scale(0.9); }
  100% { transform: translate(0px, 0px) scale(1); }
}

.animate-blob {
  animation: blob 7s infinite;
}

.animation-delay-2000 {
  animation-delay: 2s;
}

.animation-delay-4000 {
  animation-delay: 4s;
}

.glass-panel {
    background: rgba(255, 255, 255, 0.6);
    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
}

.dark .glass-panel {
    background: rgba(0, 0, 0, 0.2);
    box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.5);
    border-color: rgba(255, 255, 255, 0.08);
}

.auth-content :deep(input) {
    background: rgba(255, 255, 255, 0.5);
    border-color: transparent;
    transition: all 0.3s ease;
}

.dark .auth-content :deep(input) {
    background: rgba(0, 0, 0, 0.3);
}

.auth-content :deep(input):focus {
    background: transparent;
    border-color: hsl(var(--primary));
    box-shadow: 0 0 0 1px hsl(var(--primary));
}
</style>
