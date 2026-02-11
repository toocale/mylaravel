<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { toUrl, urlIsActive } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: editProfile(),
    },
    {
        title: 'Password',
        href: editPassword(),
    },
    {
        title: 'Two-Factor Auth',
        href: show(),
    },
    {
        title: 'Passkeys',
        href: '/settings/passkeys',
    },
    {
        title: 'Appearance',
        href: editAppearance(),
    },
];

const currentPath = typeof window !== undefined ? window.location.pathname : '';
</script>

<template>
    <div class="relative overflow-hidden rounded-3xl min-h-[600px] bg-background/50 border border-border/50">
        <!-- Animated Background Mesh (Scoped to this container) -->
        <div class="absolute inset-0 z-0 opacity-40 dark:opacity-20 pointer-events-none">
            <div class="absolute top-[-20%] left-[-10%] w-[60%] h-[60%] rounded-full bg-blue-500/30 blur-[120px] animate-blob"></div>
            <div class="absolute bottom-[-20%] right-[-10%] w-[60%] h-[60%] rounded-full bg-purple-500/30 blur-[120px] animate-blob animation-delay-2000"></div>
            <div class="absolute top-[30%] left-[30%] w-[50%] h-[50%] rounded-full bg-emerald-500/20 blur-[120px] animate-blob animation-delay-4000"></div>
        </div>

        <!-- Glass Content -->
        <div class="relative z-10 p-6 md:p-8 backdrop-blur-[2px]">
            <Heading
                title="Settings"
                description="Manage your profile and account settings"
                class="mb-8"
            />

            <div class="glass-panel rounded-2xl border border-white/20 shadow-xl overflow-hidden backdrop-blur-md">
                <div class="flex flex-col lg:flex-row h-full">
                    <!-- Sidebar -->
                    <aside class="w-full lg:w-64 border-b lg:border-b-0 lg:border-r border-white/10 dark:border-white/5 bg-white/5 p-4 lg:py-6 lg:px-4">
                        <nav class="flex flex-col space-y-2">
                            <Button
                                v-for="item in sidebarNavItems"
                                :key="toUrl(item.href)"
                                variant="ghost"
                                :class="[
                                    'w-full justify-start transition-all duration-300',
                                    urlIsActive(item.href, currentPath) 
                                        ? 'bg-primary/15 text-primary shadow-[0_0_15px_rgba(var(--primary),0.3)] border border-primary/20 backdrop-blur-sm' 
                                        : 'hover:bg-white/10 hover:text-foreground'
                                ]"
                                as-child
                            >
                                <Link :href="item.href" class="flex items-center gap-3 px-3 py-2">
                                    <component :is="item.icon" class="h-4 w-4" />
                                    {{ item.title }}
                                </Link>
                            </Button>
                        </nav>
                    </aside>

                    <!-- Main Content -->
                    <div class="flex-1 p-6 lg:p-10 bg-white/5">
                        <div class="max-w-2xl mx-auto space-y-8 animated-content">
                            <slot />
                        </div>
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
  animation: blob 8s infinite;
}

.animation-delay-2000 {
  animation-delay: 2s;
}

.animation-delay-4000 {
  animation-delay: 4s;
}

.glass-panel {
    background: rgba(255, 255, 255, 0.4);
}

.dark .glass-panel {
    background: rgba(0, 0, 0, 0.2);
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
}

/* Animations for content entry */
.animated-content {
    animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
