<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Dish, DishCategory, Ingredient, IngredientCategory } from '@/types';
import { Head } from '@inertiajs/vue3';
import Quebrados from './Quebrados.vue';

interface Props {
    dishes: Dish[];
    ingredient_categories: IngredientCategory[];
    dish_categories: DishCategory[];
    levels: any[];
    ingredients: Ingredient[];
}

defineProps<Props>();
</script>

<template>
    <Head title="Alimentos & Recetario" />
    <AppLayout>
        <div class="flex h-[calc(100vh-4.5rem)] min-h-[680px] w-full flex-1 flex-col p-2 md:p-4 overflow-hidden">
            <div class="relative h-full w-full overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-950">
                <Quebrados :dishes="dishes" :ingredients="ingredients" :dish-categories="dish_categories" :levels="levels" />
            </div>
        </div>
    </AppLayout>
</template>
