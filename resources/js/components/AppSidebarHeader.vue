<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { Building2, Mountain } from 'lucide-vue-next';
import { computed } from 'vue';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);
const businessName = computed(() => user.value?.business?.name);
const mineName = computed(() => user.value?.mine?.name);
</script>

<template>
    <header
        class="border-border/60 bg-background/95 sticky top-0 z-20 flex h-14 shrink-0 items-center justify-between gap-3 border-b px-4 backdrop-blur-md transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-6"
    >
        <div class="flex items-center gap-2.5 overflow-hidden">
            <SidebarTrigger class="-ml-1 text-muted-foreground hover:text-foreground" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div v-if="businessName || mineName" class="hidden sm:flex items-center gap-2 shrink-0">
            <div
                v-if="businessName"
                class="inline-flex items-center gap-1.5 rounded-full border border-border/80 bg-muted/50 px-2.5 py-1 text-xs font-medium text-foreground shadow-2xs"
            >
                <Building2 class="h-3.5 w-3.5 text-red-600 dark:text-red-400" />
                <span class="max-w-[160px] truncate">{{ businessName }}</span>
            </div>
            <div
                v-if="mineName"
                class="inline-flex items-center gap-1.5 rounded-full border border-border/80 bg-muted/50 px-2.5 py-1 text-xs font-medium text-muted-foreground shadow-2xs"
            >
                <Mountain class="h-3.5 w-3.5 text-muted-foreground" />
                <span class="max-w-[120px] truncate">{{ mineName }}</span>
            </div>
        </div>
    </header>
</template>
