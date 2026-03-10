<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import CategorySelection from './CategorySelection.vue';
import DishSearch from './DishSearch.vue';
import MealSchedule from './MealSchedule.vue';
import MenuDisplay from './MenuDisplay.vue';

interface Props {
    categories: [];
}

defineProps<Props>();
</script>

<template>
    <Head title="Menu" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <CategorySelection :categories="categories" />
                <MenuDisplay />
                <MealSchedule />
                <DishSearch />
            </div>
        </div>
    </AppLayout>
</template>
