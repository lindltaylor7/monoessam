<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Mine } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import CategorySelection from './CategorySelection.vue';
import MenuDisplay from './MenuDisplay.vue';

interface Props {
    categories: [];
    mines: Mine[];
    structures?: any[];
}

defineProps<Props>();

const selectedServiceableId = ref<string | null>(null);
</script>

<template>
    <Head title="Menu" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-1">
                <MenuDisplay :mines="mines" @update:serviceable="selectedServiceableId = $event" />
                <CategorySelection :categories="categories" :structures="structures" :serviceable-id="selectedServiceableId" />
            </div>
        </div>
    </AppLayout>
</template>
