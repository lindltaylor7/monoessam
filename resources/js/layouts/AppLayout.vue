<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    contentClass?: string;
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    contentClass: '',
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" :content-class="contentClass">
        <slot />
    </AppLayout>
</template>
