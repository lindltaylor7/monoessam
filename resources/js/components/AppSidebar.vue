<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import * as LucideIcons from 'lucide-vue-next';
import { House } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const page = usePage();

const permissions = page.props.auth.permissions;

const mainNavItems: NavItem[] = [
    {
        title: 'Inicio',
        href: '/dashboard',
        icon: House,
    },
];

permissions.forEach((permission: any) => {
    const IconComponent = (LucideIcons as Record<string, any>)[permission.icon_class] ?? House;

    if (permission.route_name != null) {
        mainNavItems.push({ title: permission.sidebar_name, href: '/' + permission.route_name, icon: IconComponent });
    }
});

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="border-r border-red-950/20 bg-gradient-to-b from-red-800 via-red-900 to-rose-950 text-white shadow-xl">
        <SidebarHeader class="border-b border-white/10 px-3 py-2 text-white">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="hover:bg-white/10 hover:text-white transition-colors rounded-xl">
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="sidebar-scroll py-2 text-white">
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter class="border-t border-white/10 px-2 py-2 text-white">
            <NavFooter :items="footerNavItems" />
            <NavUser class="hover:bg-white/10 text-white rounded-xl" />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
