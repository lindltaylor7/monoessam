<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Dish, DishCategory, Ingredient, IngredientCategory } from '@/types';
import { Head } from '@inertiajs/vue3';
import DishCategoriesModal from './DishCategoriesModal.vue';
import IngredientCategoriesModal from './IngredientCategoriesModal.vue';
import IngredientCategoriesTable from './IngredientCategoriesTable.vue';
import MenuTable from './MenuTable.vue';
import Quebrados from './Quebrados.vue';

interface Props {
    dishes: Dish[];
    ingredient_categories: IngredientCategory[];
    dish_categories: DishCategory[];
    ingredients: Ingredient[];
}

defineProps<Props>();
</script>

<template>
    <Head title="Alimentos" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative h-[800px] overflow-hidden rounded-xl border">
                    <Quebrados :dishes="dishes" :ingredients="ingredients" />
                </div>
        </div>
    </AppLayout>
</template>
