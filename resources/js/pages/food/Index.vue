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
    <Head title="Alimentos" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative h-[800px] overflow-hidden rounded-xl border">
                <Quebrados :dishes="dishes" :ingredients="ingredients" :dish-categories="dish_categories" :levels="levels" />
            </div>
        </div>
    </AppLayout>
</template>
