<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Building2, ClipboardList, House, User, Utensils } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const iconMap = {
    House,
    User,
    Utensils,
    ClipboardList,
    Building2,
};

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
    const IconComponent = iconMap[permission.icon_class] || House;

    if (permission.route_name != null) {
        const newPermission = { title: permission.sidebar_name, href: '/' + permission.route_name, icon: IconComponent };
        mainNavItems.push(newPermission);
    }
});

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="bg-red-700">
        <SidebarHeader class="bg-red-700 text-white">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="bg-red-700 text-white">
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter class="bg-red-700 text-white">
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
