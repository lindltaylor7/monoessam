<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import CategorySelection from './CategorySelection.vue';
import DishSearch from './DishSearch.vue';
import MealSchedule from './MealSchedule.vue';
import MenuDisplay from './MenuDisplay.vue';
import { Mine } from '@/types';
import { ref } from 'vue';

interface Props {
    categories: [];
    mines: Mine[];
    structures?: any[];
}

defineProps<Props>();

const selectedCafeId = ref<string | null>(null);
</script>

<template>
    <Head title="Menu" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <MenuDisplay :mines="mines" @update:cafe="selectedCafeId = $event" />
                <CategorySelection :categories="categories" :structures="structures" :cafe-id="selectedCafeId" />

                <!-- <MealSchedule /> -->
                <!-- <DishSearch /> -->
            </div>
        </div>
    </AppLayout>
</template>
