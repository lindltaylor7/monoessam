<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{
    items: NavItem[];
}>();

const page = usePage<SharedData>();

const isItemActive = (href: string) => {
    if (href === '/dashboard') {
        return page.url === '/dashboard';
    }
    return page.url === href || page.url.startsWith(href + '/') || page.url.startsWith(href + '?');
};
</script>

<template>
    <SidebarGroup class="px-2 py-1">
        <SidebarGroupLabel class="text-white/60 text-[10px] font-bold uppercase tracking-wider px-2 mb-1.5">
            Módulos del Sistema
        </SidebarGroupLabel>
        <SidebarMenu class="space-y-0.5">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isItemActive(item.href)"
                    :tooltip="item.title"
                    class="text-white/80 hover:text-white hover:bg-white/10 active:bg-white/15 data-[active=true]:bg-white/20 data-[active=true]:text-white data-[active=true]:font-semibold data-[active=true]:shadow-xs transition-colors rounded-lg h-9 px-2.5"
                >
                    <Link :href="item.href" class="flex items-center gap-2.5">
                        <component :is="item.icon" class="h-4 w-4 shrink-0 text-white/80 group-data-[active=true]:text-white" />
                        <span class="truncate">{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
