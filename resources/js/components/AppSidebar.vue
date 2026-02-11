<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import { Folder, LayoutGrid, Users, Ticket, Settings, ChartPie, Radio, Bell } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const page = usePage();
// Helper to check if user is admin (Role 'admin' or has 'Admin' group)
const isAdmin = computed(() => {
    const user = page.props.auth.user as any;
    return user?.role === 'admin' || (user?.groups && user.groups.some((g: any) => g.name === 'Admin')) || (user?.group_names && user.group_names.includes('Admin'));
});

// Helper to check if user belongs to any group
const hasGroups = computed(() => {
    const user = page.props.auth.user as any;
    return user?.groups?.length > 0 || false;
});


const mainNavItems = computed(() => {
    // If user is not admin AND has no groups, show only dashboard
    if (!isAdmin.value && !hasGroups.value) {
        return [{
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        }];
    }
    
    // Otherwise, show full navigation based on permissions
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Assets',
            href: '/admin/configuration',
            icon: Folder,
        },
        {
            title: 'Advanced Analytics',
            href: '/analytics',
            icon: ChartPie,
        },
        // Only show User Management to Admins
        ...(isAdmin.value ? [{
            title: 'User Management',
            href: '/admin/users',
            icon: Users,
        }] : []),
        ...(isAdmin.value ? [{
            title: 'Site Settings',
            href: '/admin/settings',
            icon: Settings,
        }] : []),


        {
            title: 'Tickets',
            href: '/tickets',
            icon: Ticket,
        },
    ];
    return items;
});


const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
