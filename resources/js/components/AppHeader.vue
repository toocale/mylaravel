<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import SearchModal from '@/components/SearchModal.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import { toUrl, urlIsActive } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { BreadcrumbItem, NavItem } from '@/types';
import { InertiaLinkProps, Link, usePage } from '@inertiajs/vue3';
import { BookOpen, ChevronDown, Folder, LayoutGrid, Menu, Search, Users, Settings, Shield, FileText, Monitor, Radio, Bell } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const showSearch = ref(false);

interface Props {
    breadcrumbs?: BreadcrumbItem[];
}

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const auth = computed(() => page.props.auth);

const isCurrentRoute = computed(
    () => (url: NonNullable<InertiaLinkProps['href']>) =>
        urlIsActive(url, page.url),
);

const activeItemStyles = computed(
    () => (url: NonNullable<InertiaLinkProps['href']>) =>
        isCurrentRoute.value(toUrl(url))
            ? 'text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100'
            : '',
);

const permissions = computed<string[]>(() => (page.props.auth as any).permissions || []);
const isAdmin = computed<boolean>(() => {
    const user = (page.props.auth as any).user;
    return user?.role === 'admin' || (user?.groups && user.groups.some((g: any) => g.name === 'Admin')) || (user?.group_names && user.group_names.includes('Admin'));
});

// Helper to check if user belongs to any group
const hasGroups = computed(() => {
    const user = (page.props.auth as any).user;
    return user?.groups?.length > 0 || false;
});

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    // If user is not admin AND has no groups, show only dashboard
    if (!isAdmin.value && !hasGroups.value) {
        return items;
    }

    // Assets is accessible to Ops/Members too, protected by backend logic
    items.push({
        title: 'Assets',
        href: '/admin/configuration',
        icon: Settings,
    });

    if (isAdmin.value || permissions.value.includes('users.view')) {
        items.push({
            title: 'User Management',
            href: '/admin/users',
            icon: Users,
        });
    }

    if (isAdmin.value || permissions.value.includes('groups.manage')) {
        items.push({
            title: 'User Groups',
            href: '/admin/groups',
            icon: Shield,
        });
    }

    // Reports - accessible to users with reports.view permission
    if (isAdmin.value || permissions.value.includes('reports.view')) {
        items.push({
            title: 'Reports',
            href: '/admin/reports',
            icon: FileText,
        });
    }

    // Tickets - accessible to all authenticated users
    items.push({
        title: 'Tickets',
        href: '/tickets',
        icon: BookOpen,
    });



    // Alert Rules - admin only


    return items;
});

const rightNavItems: NavItem[] = [];
</script>

<template>
    <div>
        <div class="border-b border-sidebar-border/80">
            <div class="mx-auto flex h-16 items-center justify-between px-4 md:max-w-7xl">
                <!-- Left Section: Menu + Logo -->
                <div class="flex items-center gap-2">
                    <!-- Mobile Menu -->
                    <div class="lg:hidden">
                        <Sheet>
                            <SheetTrigger :as-child="true">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-9 w-9"
                                >
                                    <Menu class="h-5 w-5" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="left" class="w-[300px] p-6">
                                <SheetTitle class="sr-only"
                                    >Navigation Menu</SheetTitle
                                >
                                <SheetHeader class="flex flex-row items-center justify-start text-left gap-2">
                                    <AppLogo />
                                </SheetHeader>
                                <div
                                    class="flex h-full flex-1 flex-col justify-between space-y-4 py-6"
                                >
                                    <nav class="-mx-3 space-y-1">
                                        <Link
                                            v-for="item in mainNavItems"
                                            :key="item.title"
                                            :href="item.href"
                                            class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-accent"
                                            :class="activeItemStyles(item.href)"
                                        >
                                            <component
                                                v-if="item.icon"
                                                :is="item.icon"
                                                class="h-5 w-5"
                                            />
                                            {{ item.title }}
                                        </Link>
                                    </nav>
                                    <div class="flex flex-col space-y-4">
                                        <a
                                            v-for="item in rightNavItems"
                                            :key="item.title"
                                            :href="toUrl(item.href)"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="flex items-center space-x-2 text-sm font-medium"
                                        >
                                            <component
                                                v-if="item.icon"
                                                :is="item.icon"
                                                class="h-5 w-5"
                                            />
                                            <span>{{ item.title }}</span>
                                        </a>
                                    </div>
                                </div>
                            </SheetContent>
                        </Sheet>
                    </div>

                    <!-- Logo -->
                    <Link :href="dashboard()" class="flex items-center gap-x-2">
                        <AppLogo />
                    </Link>
                </div>

                <!-- Desktop Menu -->
                <div class="hidden h-full lg:flex lg:flex-1">
                    <NavigationMenu class="ml-10 flex h-full items-stretch">
                        <NavigationMenuList
                            class="flex h-full items-stretch space-x-2"
                        >
                            <NavigationMenuItem
                                v-for="(item, index) in mainNavItems"
                                :key="index"
                                class="relative flex h-full items-center"
                            >
                                <Link
                                    :class="[
                                        navigationMenuTriggerStyle(),
                                        activeItemStyles(item.href),
                                        'h-9 cursor-pointer px-3',
                                    ]"
                                    :href="item.href"
                                >
                                    <component
                                        v-if="item.icon"
                                        :is="item.icon"
                                        class="mr-2 h-4 w-4"
                                    />
                                    {{ item.title }}
                                </Link>
                                <div
                                    v-if="isCurrentRoute(item.href)"
                                    class="absolute bottom-0 left-0 h-0.5 w-full translate-y-px bg-black dark:bg-white"
                                ></div>
                            </NavigationMenuItem>
                        </NavigationMenuList>
                    </NavigationMenu>
                </div>

                <!-- Right Section: Search + Notifications + Theme + User -->
                <div class="flex items-center space-x-2">
                    <div class="relative flex items-center space-x-1">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="group h-9 w-9 cursor-pointer"
                            @click="showSearch = true"
                        >
                            <Search
                                class="size-5 opacity-80 group-hover:opacity-100"
                            />
                        </Button>

                        <!-- Operator Kiosk Button -->
                        <TooltipProvider :delay-duration="0">
                            <Tooltip>
                                <TooltipTrigger>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        as-child
                                        class="group h-9 w-9 cursor-pointer"
                                    >
                                        <Link href="/operator">
                                            <span class="sr-only">Operator Kiosk</span>
                                            <Monitor
                                                class="size-5 opacity-80 group-hover:opacity-100 text-emerald-600"
                                            />
                                        </Link>
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Operator Kiosk</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>

                        <!-- Notification Bell -->
                        <NotificationBell />

                        <div class="hidden space-x-1 lg:flex">
                            <template
                                v-for="item in rightNavItems"
                                :key="item.title"
                            >
                                <TooltipProvider :delay-duration="0">
                                    <Tooltip>
                                        <TooltipTrigger>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                as-child
                                                class="group h-9 w-9 cursor-pointer"
                                            >
                                                <a
                                                    :href="toUrl(item.href)"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    <span class="sr-only">{{
                                                        item.title
                                                    }}</span>
                                                    <component
                                                        :is="item.icon"
                                                        class="size-5 opacity-80 group-hover:opacity-100"
                                                    />
                                                </a>
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{{ item.title }}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </template>
                        </div>
                    </div>

                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                class="relative h-10 w-auto rounded-md px-2 sm:px-3 flex items-center gap-1 sm:gap-2 hover:bg-accent focus-within:ring-2 focus-within:ring-primary"
                            >
                                <span class="text-xs sm:text-sm font-bold tracking-wide text-primary truncate max-w-[80px] sm:max-w-none" style="font-family: 'Outfit', sans-serif;">{{ auth.user?.name }}</span>
                                <ChevronDown class="h-4 w-4 opacity-50 flex-shrink-0" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <UserMenuContent :user="auth.user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </div>




    </div>
    
    <!-- Global Search Modal -->
    <SearchModal :show="showSearch" @close="showSearch = false" />
</template>
