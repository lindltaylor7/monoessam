<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dish, Ingredient } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { Check, ChevronDown, Copy, FileUp, Plus, Search, Trash } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref, watch } from 'vue';
import CalcPopover from './CalcPopover.vue';

const props = defineProps<{
    dishes: Dish[];
    ingredients: Ingredient[];
    dishCategories: any[];
    levels: any[];
}>();

// State
const searchQuery = ref('');
const dishesSearched = ref<Dish[]>(props.dishes || []);

watch(
    () => props.dishes,
    (newVal) => {
        if (!searchQuery.value) {
            dishesSearched.value = newVal || [];
        }
    },
    { deep: true },
);
const ingredientsFounded = ref<Ingredient[]>([]);
const isCreating = ref(false);
const activeLevelTab = ref<number | null>(null);

// Form State
const fileInput = ref<HTMLInputElement | null>(null);

const handleFileUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('excel_file', file);

    router.post(route('dishes.import'), formData as any, {
        onSuccess: () => {
            Swal.fire({
                title: '¡Importación Exitosa!',
                text: 'Los platos se han importado correctamente.',
                icon: 'success',
                confirmButtonColor: '#3085d6',
            });
            if (fileInput.value) fileInput.value.value = '';
        },
        onError: () => {
            Swal.fire({
                title: 'Error',
                text: 'Hubo un problema al importar el archivo.',
                icon: 'error',
                confirmButtonColor: '#d33',
            });
        },
    });
};

const form = useForm({
    id: null as number | null,
    name: '',
    description: '',
    dish_categories: [] as any[],
    mesearument_unit: [] as number[],
    recipes: {} as Record<
        number,
        {
            ingredients: any[];
            total_gross_weight: number;
            total_waste_weight: number;
            total_calories: number;
            total_cost: number;
            total_net_weight: number;
        }
    >,
});

const localLevels = ref([...props.levels]);

const addNewLevel = async () => {
    const { value: name } = await Swal.fire({
        title: 'Nuevo Nivel de Aplicación',
        input: 'text',
        inputLabel: 'Nombre del nivel',
        inputPlaceholder: 'Ej: VIP, Especial, etc.',
        showCancelButton: true,
        confirmButtonColor: '#18181b',
    });

    if (name) {
        try {
            const response = await axios.post(route('levels.store'), { name });
            localLevels.value.push(response.data);
            Swal.fire({
                title: '¡Añadido!',
                text: `Nivel "${name}" añadido correctamente.`,
                icon: 'success',
                timer: 1500,
                showConfirmButton: false,
            });
        } catch (error: any) {
            Swal.fire({
                title: 'Error',
                text: error.response?.data?.message || 'No se pudo crear el nivel.',
                icon: 'error',
            });
        }
    }
};

const toggleCategory = (category: any) => {
    const index = form.dish_categories.findIndex((c) => c.id === category.id);
    if (index === -1) {
        form.dish_categories.push(category);
    } else {
        form.dish_categories.splice(index, 1);
    }
};

const isCategorySelected = (categoryId: number) => {
    return form.dish_categories.some((c) => c.id === categoryId);
};

const deleteLevelFromList = (levelId: number) => {
    const level = localLevels.value.find((l) => l.id === levelId);
    Swal.fire({
        title: '¿Eliminar nivel?',
        text: `Se quitará "${level?.name}" de la base de datos. Esta acción puede afectar a otros platos.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            axios
                .delete(route('levels.destroy', levelId))
                .then(() => {
                    localLevels.value = localLevels.value.filter((l) => l.id !== levelId);
                    const index = form.mesearument_unit.indexOf(levelId);
                    if (index !== -1) {
                        form.mesearument_unit.splice(index, 1);
                        delete form.recipes[levelId];
                        if (activeLevelTab.value === levelId) {
                            activeLevelTab.value = form.mesearument_unit.length ? form.mesearument_unit[0] : null;
                        }
                    }
                })
                .catch((error) => {
                    Swal.fire({
                        title: 'Error',
                        text: 'No se pudo eliminar el nivel. Es posible que esté en uso.',
                        icon: 'error',
                    });
                });
        }
    });
};

const toggleLevel = (id: number) => {
    const index = form.mesearument_unit.indexOf(id);
    if (index === -1) {
        form.mesearument_unit.push(id);
        form.recipes[id] = {
            ingredients: [],
            total_gross_weight: 0,
            total_waste_weight: 0,
            total_calories: 0,
            total_cost: 0,
            total_net_weight: 0,
        };
        activeLevelTab.value = id;
    } else {
        form.mesearument_unit.splice(index, 1);
        delete form.recipes[id];
        if (activeLevelTab.value === id) {
            activeLevelTab.value = form.mesearument_unit.length ? form.mesearument_unit[0] : null;
        }
    }
};

// List Logic
const searchDish = (e: Event) => {
    const value = (e.target as HTMLInputElement).value;
    if (!value) {
        dishesSearched.value = props.dishes || [];
        return;
    }

    axios
        .get(route('dishes.search', value))
        .then((result) => {
            dishesSearched.value = result.data;
        })
        .catch((err) => {
            console.error(err);
        });
};

const calculateIngredientCalories = (ingredient: any) => {
    const factors = ingredient?.nutritional_factors || ingredient?.nutritionalFactors;
    if (factors && factors.length > 0) {
        return factors.reduce((sum: number, factor: any) => {
            const nfactor = parseFloat(factor.nfactorcal) || 0;
            const comp = parseFloat(factor.composition) || 0;
            return sum + nfactor * comp;
        }, 0);
    }
    return ingredient?.dosification?.energy || ingredient?.energy || 0;
};

const getDishIngredientsCount = (dish: any) => {
    if (!dish.recipes || dish.recipes.length === 0) return 0;
    const ingredientIds = new Set();
    dish.recipes.forEach((recipe: any) => {
        if (recipe.ingredients) {
            recipe.ingredients.forEach((ing: any) => ingredientIds.add(ing.id));
        }
    });
    return ingredientIds.size;
};

const copyIngredientsFromMaster = () => {
    if (!activeLevelTab.value) return;
    if (form.mesearument_unit.length <= 1) return;

    // Find Master (level 1) or the first available level that has ingredients
    const sourceLevelId =
        form.recipes[1] && form.recipes[1].ingredients.length > 0
            ? 1
            : form.mesearument_unit.find((id) => form.recipes[id] && form.recipes[id].ingredients.length > 0);

    if (sourceLevelId && sourceLevelId !== activeLevelTab.value) {
        const sourceRecipe = form.recipes[sourceLevelId];
        form.recipes[activeLevelTab.value] = JSON.parse(JSON.stringify(sourceRecipe));

        Swal.fire({
            title: 'Ingredientes Duplicados',
            text: 'Se copiaron los ingredientes correctamente.',
            icon: 'success',
            timer: 1500,
            showConfirmButton: false,
        });
    } else {
        Swal.fire({
            title: 'Atención',
            text: 'No hay ingredientes en otros niveles para duplicar.',
            icon: 'info',
        });
    }
};

const editDish = (dish: Dish) => {
    form.clearErrors();
    isCreating.value = false;

    form.id = dish.id;
    form.name = dish.name;
    form.description = dish.description || '';
    // @ts-ignore
    form.dish_categories = dish.dish_categories || [];

    form.mesearument_unit = [];
    form.recipes = {};

    if (dish.recipes && dish.recipes.length > 0) {
        dish.recipes.forEach((recipe: any) => {
            const levelId = recipe.level_id;
            if (!levelId) return;
            if (form.mesearument_unit.includes(levelId)) return;
            form.mesearument_unit.push(levelId);

            form.recipes[levelId] = {
                total_gross_weight: recipe.total_gross_weight || 0,
                total_waste_weight: recipe.total_waste_weight || 0,
                total_calories: recipe.total_calories || 0,
                total_cost: recipe.total_cost || 0,
                total_net_weight: recipe.total_net_weight || 0,
                ingredients: (recipe.ingredients || []).map((ing: any) => {
                    const fullIng = props.ingredients?.find((i) => i.id === ing.id);
                    const newIng = {
                        ...ing,
                        gross_weight: parseFloat(ing.gross_weight) || 0,
                        solid_waste: parseFloat(ing.solid_waste) || 0,
                        liquid_waste: parseFloat(ing.liquid_waste) || 0,
                        calories: parseFloat(ing.calories) || 0,
                        cost: parseFloat(ing.cost) || 0,
                        final_product: parseFloat(ing.final_product) || 0,
                        unit_price: parseFloat(ing.unit_price) || 0,
                        selected_unit: 'g',
                        input_quantity: parseFloat(ing.gross_weight) || 0,
                        originalValues: {
                            waste: ing.waste || fullIng?.waste || 0,
                            calories: calculateIngredientCalories(fullIng || ing),
                        },
                    };
                    newIng.calories = (newIng.gross_weight * newIng.originalValues.calories) / 100;
                    return newIng;
                }),
            };
        });
        activeLevelTab.value = form.mesearument_unit[0];
    } else {
        activeLevelTab.value = null;
    }
};

const duplicateDish = (dish: Dish) => {
    form.clearErrors();
    isCreating.value = true;

    form.id = null;
    form.name = dish.name + ' (Copia)';
    form.description = dish.description || '';
    // @ts-ignore
    form.dish_categories = dish.dish_categories || [];

    form.mesearument_unit = [];
    form.recipes = {};

    if (dish.recipes && dish.recipes.length > 0) {
        dish.recipes.forEach((recipe: any) => {
            const levelId = recipe.level_id;
            if (!levelId) return;
            if (form.mesearument_unit.includes(levelId)) return;
            form.mesearument_unit.push(levelId);

            form.recipes[levelId] = {
                total_gross_weight: recipe.total_gross_weight || 0,
                total_waste_weight: recipe.total_waste_weight || 0,
                total_calories: recipe.total_calories || 0,
                total_cost: recipe.total_cost || 0,
                total_net_weight: recipe.total_net_weight || 0,
                ingredients: (recipe.ingredients || []).map((ing: any) => {
                    const fullIng = props.ingredients?.find((i) => i.id === ing.id);
                    const newIng = {
                        ...ing,
                        gross_weight: parseFloat(ing.gross_weight) || 0,
                        solid_waste: parseFloat(ing.solid_waste) || 0,
                        liquid_waste: parseFloat(ing.liquid_waste) || 0,
                        calories: parseFloat(ing.calories) || 0,
                        cost: parseFloat(ing.cost) || 0,
                        final_product: parseFloat(ing.final_product) || 0,
                        unit_price: parseFloat(ing.unit_price) || 0,
                        selected_unit: 'g',
                        input_quantity: parseFloat(ing.gross_weight) || 0,
                        originalValues: {
                            waste: ing.waste || fullIng?.waste || 0,
                            calories: calculateIngredientCalories(fullIng || ing),
                        },
                    };
                    newIng.calories = (newIng.gross_weight * newIng.originalValues.calories) / 100;
                    return newIng;
                }),
            };
        });
        activeLevelTab.value = form.mesearument_unit[0];
    } else {
        activeLevelTab.value = null;
    }
};

const createDish = () => {
    form.clearErrors();
    form.reset();
    form.id = null;
    form.dish_categories = [];
    form.mesearument_unit = [];
    form.recipes = {};
    activeLevelTab.value = null;
    isCreating.value = true;
};

const resetView = () => {
    form.reset();
    form.id = null;
    isCreating.value = false;
};

const deleteDish = (id: number) => {
    Swal.fire({
        title: '¿Estás seguro?',
        text: 'No podrás revertir esto',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('dishes.destroy', id), {
                onSuccess: () => {
                    dishesSearched.value = dishesSearched.value.filter((d) => d.id !== id);
                    if (form.id === id) {
                        resetView();
                    }
                },
            });
        }
    });
};

// Form Logic

const recalculateTotals = () => {
    if (!activeLevelTab.value) return;
    const recipe = form.recipes[activeLevelTab.value];
    if (!recipe) return;

    recipe.total_gross_weight = recipe.ingredients.reduce((sum, i) => sum + (parseFloat(i.gross_weight) || 0), 0);
    recipe.total_waste_weight = recipe.ingredients.reduce((sum, i) => sum + (parseFloat(i.solid_waste) || 0), 0);
    recipe.total_calories = recipe.ingredients.reduce((sum, i) => sum + (parseFloat(i.calories) || 0), 0);
    recipe.total_cost = recipe.ingredients.reduce((sum, i) => sum + (parseFloat(i.cost) || 0), 0);
    recipe.total_net_weight = recipe.ingredients.reduce((sum, i) => sum + (parseFloat(i.final_product) || 0), 0);
};

const searchIngredients = (e: Event) => {
    const value = (e.target as HTMLInputElement).value;
    if (!value) {
        ingredientsFounded.value = [];
        return;
    }

    axios
        .get(route('dishes.search-ingredients', value))
        .then((result) => {
            ingredientsFounded.value = result.data;
        })
        .catch((err) => {
            console.error(err);
        });
};

const selectIngredient = (ingredient: Ingredient) => {
    if (!activeLevelTab.value) {
        Swal.fire({ title: 'Error', text: 'Seleccione un nivel de aplicación primero.', icon: 'error' });
        return;
    }
    const recipe = form.recipes[activeLevelTab.value];

    if (recipe.ingredients.some((ing: any) => ing.id === ingredient.id)) {
        Swal.fire({ title: 'Atención', text: 'El ingrediente ya está en la lista de este nivel.', icon: 'warning' });
        return;
    }

    const newIng = {
        ...ingredient,
        originalValues: {
            waste: ingredient.waste || 0,
            calories: calculateIngredientCalories(ingredient),
        },
        selected_unit: 'Kg',
        input_quantity: 0,
        gross_weight: 0,
        solid_waste: 0,
        calories: 0,
        cost: 0,
        final_product: 0,
        unit_price: 0,
    };
    recipe.ingredients.push(newIng);
    ingredientsFounded.value = [];
    recalculateTotals();
};

const removeIngredientFromForm = (id: number) => {
    if (!activeLevelTab.value) return;
    const recipe = form.recipes[activeLevelTab.value];
    recipe.ingredients = recipe.ingredients.filter((ing) => ing.id !== id);
    recalculateTotals();
};

const calcMassiveProperties = (id: number, calcArray: number[]) => {
    if (!activeLevelTab.value) return;
    const recipe = form.recipes[activeLevelTab.value];
    const ingredientIndex = recipe.ingredients.findIndex((ing) => ing.id == id);
    if (ingredientIndex !== -1) {
        recipe.ingredients[ingredientIndex].gross_weight = calcArray[0];
        recipe.ingredients[ingredientIndex].solid_waste = calcArray[1];
        recipe.ingredients[ingredientIndex].calories = calcArray[2];
        recipe.ingredients[ingredientIndex].cost = calcArray[3];
        recipe.ingredients[ingredientIndex].final_product = calcArray[4];
        recipe.ingredients[ingredientIndex].unit_price = calcArray[5];
        recalculateTotals();
    }
};

const onWeightInput = (ingredient: any) => {
    const inputVal = parseFloat(ingredient.input_quantity) || 0;

    // Convertir a gramos para cálculos internos
    const weightInGrams = ingredient.selected_unit === 'Kg' || ingredient.selected_unit === 'kg' ? inputVal * 1000 : inputVal;
    ingredient.gross_weight = weightInGrams;

    const origWaste = parseFloat(ingredient.originalValues?.waste) || 0;
    const origCalories = parseFloat(ingredient.originalValues?.calories) || 0;

    // Recalcular Merma
    ingredient.solid_waste = (weightInGrams * origWaste) / 100;

    // Recalcular Producto Final
    ingredient.final_product = weightInGrams - ingredient.solid_waste;

    // Recalcular Calorías
    ingredient.calories = (ingredient.gross_weight * origCalories) / 100;

    // Recalcular Costo si existe precio unitario
    if (ingredient.unit_price) {
        ingredient.cost =
            ingredient.selected_unit === 'Kg' || ingredient.selected_unit === 'kg'
                ? inputVal * ingredient.unit_price
                : (inputVal / 1000) * ingredient.unit_price;
    }

    recalculateTotals();
};

const submit = () => {
    const data = {
        ...form.data(),
        dish_categories: form.dish_categories.map((c) => c.id),
    };

    const options = {
        onSuccess: () => {
            resetView();
            Swal.fire({
                title: form.id ? '¡Actualizado!' : '¡Creado!',
                text: `El quebrado se ha ${form.id ? 'guardado' : 'creado'} exitosamente.`,
                icon: 'success',
                timer: 2000,
                showConfirmButton: false,
            });
        },
        onError: (errors) => {
            let errorMsg = 'Hubo un problema al procesar la solicitud.';
            if (errors) {
                errorMsg = Object.values(errors).flat().join('\n');
            }
            Swal.fire({
                title: 'Error de Validación',
                text: errorMsg,
                icon: 'error',
                confirmButtonColor: '#ef4444',
            });
        },
    };

    if (form.id) {
        router.put(route('dishes.update', form.id), data as any, options);
    } else {
        router.post(route('dishes.store'), data as any, options);
    }
};
</script>

<template>
    <!-- Content Wrapper -->
    <div
        class="flex h-full gap-0 overflow-hidden rounded-xl border border-zinc-200 bg-white p-0 md:gap-4 md:p-4 dark:border-zinc-800 dark:bg-zinc-950"
    >
        <!-- LEFT PANEL: Dish List -->
        <div
            class="z-10 flex w-full flex-col gap-4 border-r bg-white pr-0 md:w-1/3 md:pr-4 dark:bg-zinc-950"
            :class="{ 'hidden md:flex': form.id !== null || isCreating }"
        >
            <div class="flex items-center gap-2 border-b p-4 md:border-0 md:p-0">
                <div class="relative flex-1">
                    <Search class="absolute top-2.5 left-2.5 h-4 w-4 text-zinc-500" />
                    <Input @keyup="searchDish" placeholder="Buscar plato..." class="w-full pl-9" />
                </div>
                <input type="file" ref="fileInput" class="hidden" accept=".xlsx,.xls,.csv" @change="handleFileUpload" />
                <Button
                    @click="fileInput?.click()"
                    variant="outline"
                    class="flex items-center gap-2 border-zinc-200"
                    title="Importar platos e ingredientes"
                >
                    <FileUp class="h-4 w-4 text-zinc-500" />
                    <span class="hidden text-xs lg:inline">Importar</span>
                </Button>
                <Button @click="createDish" size="icon" class="bg-primary hover:bg-primary/90 shrink-0" title="Nuevo plato">
                    <Plus class="h-4 w-4" />
                </Button>
            </div>

            <div class="scrollbar-thin scrollbar-thumb-zinc-200 dark:scrollbar-thumb-zinc-800 flex-1 space-y-1 overflow-y-auto p-2 pr-2">
                <div
                    v-for="dish in dishesSearched"
                    :key="dish.id"
                    @click="editDish(dish)"
                    class="group relative cursor-pointer rounded-lg border p-3 transition-all hover:bg-zinc-100 dark:hover:bg-zinc-900"
                    :class="form.id === dish.id ? 'border-primary bg-zinc-100 dark:bg-zinc-900' : 'border-transparent bg-zinc-50 dark:bg-zinc-900/50'"
                >
                    <div class="flex items-start justify-between">
                        <div class="text-sm font-medium">{{ dish.name }}</div>
                    </div>
                    <div class="text-muted-foreground mt-1 line-clamp-2 text-xs">{{ dish.description }}</div>
                    <div class="mt-2 flex flex-wrap gap-1">
                        <div class="rounded-full bg-zinc-200 px-1.5 py-0.5 text-[10px] text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                            {{ getDishIngredientsCount(dish) }} Ingredientes
                        </div>
                        <span
                            v-for="cat in dish.dish_categories"
                            :key="cat.id"
                            class="rounded-full border border-indigo-100 bg-indigo-50 px-1.5 py-0.5 text-[9px] font-medium text-indigo-600"
                        >
                            {{ cat.name }}
                        </span>
                        <span
                            v-for="recipe in dish.recipes"
                            :key="recipe.id"
                            class="rounded-md border border-blue-100 bg-blue-50 px-1.5 py-0.5 text-[9px] font-bold text-blue-600 dark:border-blue-800/50 dark:bg-blue-900/30 dark:text-blue-400"
                        >
                            {{ recipe.level?.name }}
                        </span>
                    </div>

                    <div class="absolute top-2 right-2 flex gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                        <Button
                            @click.stop="duplicateDish(dish)"
                            variant="ghost"
                            size="icon"
                            class="h-7 w-7 border border-transparent text-zinc-400 transition-all hover:border-blue-100 hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-blue-900/30"
                            title="Duplicar plato"
                        >
                            <Copy class="h-3.5 w-3.5" />
                        </Button>
                        <Button
                            @click.stop="deleteDish(dish.id)"
                            variant="ghost"
                            size="icon"
                            class="h-7 w-7 border border-transparent text-zinc-400 transition-all hover:border-red-100 hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-900/30"
                            title="Eliminar plato"
                        >
                            <Trash class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT PANEL: Form/Details -->
        <div
            class="flex h-full flex-1 flex-col overflow-hidden rounded-xl bg-zinc-50/30 p-1 md:border md:border-dashed md:border-zinc-200 dark:bg-zinc-900/10 dark:md:border-zinc-800"
            :class="{
                'fixed inset-0 z-50 bg-white md:static md:bg-transparent dark:bg-zinc-950': form.id !== null || isCreating,
                'hidden md:flex': !form.id && !isCreating,
            }"
        >
            <div v-if="form.id !== null || isCreating" class="flex h-full flex-col">
                <div class="flex shrink-0 items-center justify-between rounded-t-xl border-b bg-white p-4 dark:bg-zinc-950">
                    <div>
                        <h3 class="text-lg leading-none font-bold">{{ form.id ? 'Editar Plato' : 'Nuevo Plato' }}</h3>
                        <p class="text-muted-foreground mt-1 text-xs">Configure los ingredientes y valores del plato.</p>
                    </div>
                    <div class="flex gap-2">
                        <Button type="button" variant="ghost" size="sm" @click="resetView">Cancelar</Button>
                        <Button type="button" size="sm" @click="submit" :disabled="form.processing">
                            {{ form.id ? 'Guardar' : 'Crear' }}
                        </Button>
                    </div>
                </div>

                <div class="flex-1 space-y-6 overflow-y-auto bg-white p-4 dark:bg-zinc-950">
                    <!-- Basic Info Inputs -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-zinc-500 uppercase">Nombre</label>
                            <Input v-model="form.name" placeholder="Nombre del plato" class="h-9" />
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-zinc-500 uppercase">Descripción</label>
                            <Input v-model="form.description" placeholder="Descripción breve" class="h-9" />
                        </div>
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-semibold text-zinc-500 uppercase">Categorías</label>
                                <Popover>
                                    <PopoverTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-6 gap-1 px-2 text-[10px] font-bold text-indigo-600 transition-all hover:bg-indigo-50 hover:text-indigo-700"
                                        >
                                            Gestionar <ChevronDown class="h-3 w-3" />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent class="w-64 p-0" align="start">
                                        <div class="border-b p-2">
                                            <h4 class="px-2 py-1 text-xs font-bold text-zinc-500 uppercase">Seleccionar Categorías</h4>
                                        </div>
                                        <div class="max-h-60 overflow-y-auto p-1">
                                            <div
                                                v-for="cat in dishCategories"
                                                :key="cat.id"
                                                @click="toggleCategory(cat)"
                                                class="flex cursor-pointer items-center gap-2 rounded-md p-2 transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                            >
                                                <div
                                                    class="flex h-4 w-4 items-center justify-center rounded border transition-colors"
                                                    :class="isCategorySelected(cat.id) ? 'border-indigo-600 bg-indigo-600' : 'border-zinc-300'"
                                                >
                                                    <Check v-if="isCategorySelected(cat.id)" class="h-3 w-3 text-white" />
                                                </div>
                                                <span
                                                    class="text-xs font-medium"
                                                    :class="isCategorySelected(cat.id) ? 'text-indigo-600' : 'text-zinc-600'"
                                                >
                                                    {{ cat.name }}
                                                </span>
                                            </div>
                                        </div>
                                    </PopoverContent>
                                </Popover>
                            </div>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <Badge
                                    v-for="cat in form.dish_categories"
                                    :key="cat.id"
                                    variant="secondary"
                                    class="border-indigo-100 bg-indigo-50 px-2 py-0 text-[10px] font-medium text-indigo-600"
                                >
                                    {{ cat.name }}
                                    <button @click.stop="toggleCategory(cat)" class="ml-1 hover:text-indigo-800">
                                        <Trash class="h-2.5 w-2.5" />
                                    </button>
                                </Badge>
                                <span v-if="!form.dish_categories?.length" class="text-[10px] text-zinc-400 italic"
                                    >Sin categorías seleccionadas</span
                                >
                            </div>
                        </div>
                        <div class="col-span-1 space-y-1.5 md:col-span-3">
                            <div class="mb-1 flex items-center justify-between">
                                <label class="text-xs font-semibold text-zinc-500 uppercase">Niveles de Aplicación</label>
                                <button
                                    type="button"
                                    @click="addNewLevel"
                                    class="flex items-center gap-1 text-[10px] font-bold text-indigo-600 transition-colors hover:text-indigo-700"
                                >
                                    <Plus class="h-3 w-3" /> Añadir Nivel
                                </button>
                            </div>
                            <div class="flex flex-wrap gap-2 pt-1">
                                <div v-for="level in localLevels" :key="level.id" class="group relative">
                                    <button
                                        type="button"
                                        @click="toggleLevel(level.id)"
                                        class="flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-bold transition-all"
                                        :class="
                                            form.mesearument_unit.includes(level.id)
                                                ? 'border-zinc-900 bg-zinc-900 text-white shadow-sm'
                                                : 'border-zinc-200 bg-zinc-50 text-zinc-600 hover:bg-zinc-100'
                                        "
                                    >
                                        {{ level.name }}
                                        <Trash
                                            @click.stop="deleteLevelFromList(level.id)"
                                            class="ml-1 h-3 w-3 text-zinc-400 opacity-0 transition-all group-hover:opacity-100 hover:text-red-500"
                                        />
                                    </button>
                                </div>
                            </div>
                            <p v-if="form.errors.mesearument_unit" class="text-[10px] font-medium text-red-500">{{ form.errors.mesearument_unit }}</p>
                        </div>
                    </div>

                    <!-- Tabs de Niveles -->
                    <div v-if="form.mesearument_unit.length > 0" class="mt-4 border-b">
                        <div class="scrollbar-none -mb-px flex gap-2 overflow-x-auto pb-1">
                            <button
                                v-for="levelId in form.mesearument_unit"
                                :key="levelId"
                                type="button"
                                @click="activeLevelTab = levelId"
                                class="border-b-2 px-4 py-2 text-xs font-bold whitespace-nowrap transition-all"
                                :class="
                                    activeLevelTab === levelId
                                        ? 'border-primary text-primary'
                                        : 'border-transparent text-zinc-400 hover:text-zinc-600'
                                "
                            >
                                {{ localLevels.find((l) => l.id === levelId)?.name }}
                            </button>
                        </div>
                    </div>

                    <!-- Ingredients & Calculator -->
                    <div v-if="activeLevelTab && form.recipes[activeLevelTab]" class="space-y-3 pt-2">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-semibold">Ingredientes - {{ localLevels.find((l) => l.id === activeLevelTab)?.name }}</h4>
                            <div class="relative w-full max-w-xs">
                                <Search class="absolute top-2 left-2 h-3.5 w-3.5 text-zinc-400" />
                                <Input placeholder="Buscar ingrediente..." @keyup="searchIngredients" class="h-8 pl-8 text-xs" />
                                <!-- Dropdown Results -->
                                <div
                                    v-if="ingredientsFounded.length > 0"
                                    class="absolute top-full right-0 left-0 z-50 mt-1 max-h-48 overflow-y-auto rounded-md border bg-white p-1 shadow-lg dark:bg-zinc-900"
                                >
                                    <div
                                        v-for="ingredient in ingredientsFounded"
                                        :key="ingredient.id"
                                        @click="selectIngredient(ingredient)"
                                        class="flex cursor-pointer items-center gap-2 rounded-sm p-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                    >
                                        <Plus class="text-primary h-3 w-3" />
                                        <span>{{ ingredient.name }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-hidden rounded-md border bg-white dark:bg-zinc-950">
                            <Table>
                                <TableHeader class="sticky top-0 z-20 bg-zinc-50 dark:bg-zinc-900">
                                    <TableRow class="border-none hover:bg-transparent">
                                        <TableHead class="h-10 border-r border-zinc-400 bg-zinc-500 text-[10px] font-black text-white uppercase"
                                            >Insumo</TableHead
                                        >
                                        <TableHead
                                            class="h-10 border-r border-zinc-400 bg-blue-400 text-center text-[10px] font-black text-white uppercase"
                                            >P. Unit</TableHead
                                        >
                                        <TableHead
                                            class="h-10 border-r border-zinc-400 bg-purple-400 text-center text-[10px] font-black text-white uppercase"
                                            >P. x Gr</TableHead
                                        >
                                        <TableHead
                                            class="h-10 border-r border-zinc-400 bg-blue-900 text-center text-[10px] font-black text-white uppercase"
                                            >Cant.</TableHead
                                        >
                                        <TableHead
                                            class="h-10 border-r border-zinc-400 bg-lime-400 text-center text-[10px] font-black text-zinc-900 uppercase"
                                            >Und</TableHead
                                        >
                                        <TableHead
                                            class="h-10 border-r border-zinc-400 bg-sky-400 text-center text-[10px] font-black text-blue-900 uppercase"
                                            >Costo Base</TableHead
                                        >
                                        <TableHead
                                            class="h-10 border-r border-zinc-400 bg-red-900 text-center text-[10px] font-black text-white uppercase"
                                            >Mat. Prima</TableHead
                                        >
                                        <TableHead
                                            class="h-10 border-r border-zinc-400 bg-teal-900 text-center text-[10px] font-black text-white uppercase"
                                            >Desecho</TableHead
                                        >
                                        <TableHead
                                            class="h-10 border-r border-zinc-400 bg-orange-800 text-center text-[10px] font-black text-white uppercase"
                                            >Prod Final</TableHead
                                        >
                                        <TableHead
                                            class="h-10 border-r border-zinc-400 bg-orange-400 text-center text-[10px] font-black text-white uppercase"
                                            >Calorías</TableHead
                                        >
                                        <TableHead class="h-10 bg-zinc-100 text-center"></TableHead>
                                        <TableHead class="h-10 w-[40px] bg-zinc-100 text-right">Opc.</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="ingredient in form.recipes[activeLevelTab].ingredients"
                                        :key="ingredient.id"
                                        class="border-b border-zinc-200 transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-900/50"
                                    >
                                        <!-- Insumo -->
                                        <TableCell class="border-r border-zinc-100 px-2 py-1">
                                            <div class="text-[11px] leading-tight font-bold text-blue-800 uppercase">{{ ingredient.name }}</div>
                                        </TableCell>

                                        <!-- Precio Unitario -->
                                        <TableCell class="border-r border-zinc-100 bg-amber-50/30 px-1 py-1 text-center">
                                            <span class="text-xs font-bold text-red-600">{{ Number(ingredient.unit_price || 0).toFixed(2) }}</span>
                                        </TableCell>

                                        <!-- Precio x Gr -->
                                        <TableCell class="border-r border-zinc-100 bg-purple-50/30 px-1 py-1 text-center">
                                            <span class="font-mono text-[10px] text-purple-700">{{
                                                Number((ingredient.unit_price || 0) / 1000).toFixed(5)
                                            }}</span>
                                        </TableCell>

                                        <!-- Cantidad Input -->
                                        <TableCell class="border-r border-zinc-100 bg-blue-50/50 px-1 py-1 text-center">
                                            <input
                                                type="number"
                                                v-model="ingredient.input_quantity"
                                                @input="onWeightInput(ingredient)"
                                                class="h-7 w-16 rounded-none border border-red-800 text-center text-xs font-bold shadow-inner focus:outline-none"
                                            />
                                        </TableCell>

                                        <!-- Unidad Select -->
                                        <TableCell class="border-r border-zinc-100 bg-lime-50/30 px-1 py-1 text-center">
                                            <select
                                                v-model="ingredient.selected_unit"
                                                @change="onWeightInput(ingredient)"
                                                class="h-7 border-none bg-transparent text-[10px] font-bold text-red-800 focus:ring-0"
                                            >
                                                <option value="Kg">Kg</option>
                                                <option value="g">Gr</option>
                                            </select>
                                        </TableCell>

                                        <!-- Costo Base -->
                                        <TableCell class="border-r border-zinc-100 bg-sky-50 px-1 py-1 text-center">
                                            <span class="text-xs font-bold text-blue-800">{{ Number(ingredient.cost).toFixed(2) }}</span>
                                        </TableCell>

                                        <!-- Materia Prima (Gr) -->
                                        <TableCell class="border-r border-zinc-100 px-1 py-1 text-center">
                                            <span class="font-mono text-xs">{{ Number(ingredient.gross_weight).toFixed(1) }}</span>
                                        </TableCell>

                                        <!-- Desecho (Gr) -->
                                        <TableCell class="border-r border-zinc-100 px-1 py-1 text-center">
                                            <span class="font-mono text-xs text-zinc-600">{{ Number(ingredient.solid_waste).toFixed(1) }}</span>
                                        </TableCell>

                                        <!-- Producto Final (Gr) -->
                                        <TableCell class="border-r border-zinc-100 px-1 py-1 text-center font-bold">
                                            <span class="font-mono text-xs text-red-900">{{ Number(ingredient.final_product).toFixed(1) }}</span>
                                        </TableCell>

                                        <!-- Calorías -->
                                        <TableCell class="border-r border-zinc-100 px-1 py-1 text-center">
                                            <span class="font-mono text-xs">{{ Number(ingredient.calories).toFixed(2) }}</span>
                                        </TableCell>

                                        <!-- Calculadora -->
                                        <TableCell class="px-1 py-1 text-center align-middle">
                                            <CalcPopover
                                                :ingredient="ingredient"
                                                :totalMateriaPrima="form.recipes[activeLevelTab].total_gross_weight"
                                                :totalWasteWeight="form.recipes[activeLevelTab].total_waste_weight"
                                                :totalCalories="form.recipes[activeLevelTab].total_calories"
                                                :totalCost="form.recipes[activeLevelTab].total_cost"
                                                :totalfinalProduct="form.recipes[activeLevelTab].total_net_weight"
                                                @calcMassiveProperties="calcMassiveProperties"
                                            />
                                        </TableCell>

                                        <TableCell class="px-1 py-1 text-right">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                @click="removeIngredientFromForm(ingredient.id)"
                                                class="h-6 w-6 text-red-500 hover:text-red-700"
                                            >
                                                <Trash class="h-3 w-3" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="form.recipes[activeLevelTab].ingredients.length === 0">
                                        <TableCell colspan="12" class="text-muted-foreground h-32 border-dashed text-center text-sm">
                                            <div class="flex flex-col items-center gap-2">
                                                <div class="rounded-full bg-zinc-50 p-2 dark:bg-zinc-900">
                                                    <Plus class="h-4 w-4 text-zinc-400" />
                                                </div>
                                                <span>Seleccione ingredientes en el buscador superior para comenzar.</span>
                                                <Button
                                                    v-if="form.mesearument_unit.length > 1"
                                                    type="button"
                                                    @click="copyIngredientsFromMaster"
                                                    variant="outline"
                                                    size="sm"
                                                    class="mt-2 border-blue-200 text-xs text-blue-600 hover:bg-blue-50 dark:border-blue-900/50 dark:text-blue-400 dark:hover:bg-blue-900/30"
                                                >
                                                    <Copy class="mr-2 h-3 w-3" />
                                                    Duplicar ingredientes (Master)
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- Totals Footer -->
                        <div
                            class="sticky bottom-0 grid grid-cols-2 gap-2 border-t bg-white pt-4 pb-2 sm:grid-cols-2 md:grid-cols-5 dark:bg-zinc-950"
                        >
                            <div class="rounded border bg-zinc-50 p-2 text-center dark:bg-zinc-900">
                                <div class="text-muted-foreground mb-1 text-[10px] font-bold uppercase">P. Bruto</div>
                                <div class="font-mono text-sm font-bold">
                                    {{ Number(form.recipes[activeLevelTab].total_gross_weight).toFixed(2) }}
                                </div>
                            </div>
                            <div class="rounded border bg-zinc-50 p-2 text-center dark:bg-zinc-900">
                                <div class="text-muted-foreground mb-1 text-[10px] font-bold uppercase">Mermas Tot.</div>
                                <div class="font-mono text-sm font-bold text-amber-600">
                                    {{ Number(form.recipes[activeLevelTab].total_waste_weight).toFixed(2) }}
                                </div>
                            </div>
                            <div class="rounded border bg-zinc-50 p-2 text-center dark:bg-zinc-900">
                                <div class="text-muted-foreground mb-1 text-[10px] font-bold uppercase">Calorías</div>
                                <div class="font-mono text-sm font-bold text-rose-600">
                                    {{ Number(form.recipes[activeLevelTab].total_calories).toFixed(2) }}
                                </div>
                            </div>
                            <div class="rounded border bg-zinc-50 p-2 text-center dark:bg-zinc-900">
                                <div class="text-muted-foreground mb-1 text-[10px] font-bold uppercase">Costo</div>
                                <div class="font-mono text-sm font-bold text-emerald-600">
                                    S/. {{ Number(form.recipes[activeLevelTab].total_cost).toFixed(2) }}
                                </div>
                            </div>
                            <div class="rounded border bg-zinc-50 p-2 text-center dark:bg-zinc-900">
                                <div class="text-muted-foreground mb-1 text-[10px] font-bold uppercase">P. Final</div>
                                <div class="font-mono text-sm font-bold text-indigo-600">
                                    {{ Number(form.recipes[activeLevelTab].total_net_weight).toFixed(2) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State for Recipes -->
                    <div v-else-if="form.mesearument_unit.length > 0" class="text-muted-foreground flex h-48 flex-col items-center justify-center">
                        <Plus class="mb-2 h-8 w-8 opacity-20" />
                        <p class="text-sm">Seleccione un nivel de aplicación arriba para ver su receta.</p>
                    </div>

                    <!-- No Levels Selected -->
                    <div v-else class="text-muted-foreground flex h-48 flex-col items-center justify-center rounded-lg border border-dashed">
                        <p class="text-sm">Debe seleccionar al menos un nivel de aplicación para configurar recetas.</p>
                    </div>
                </div>
            </div>

            <div v-else class="flex h-full flex-col items-center justify-center bg-zinc-50 p-8 text-center dark:bg-zinc-900/50">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-zinc-200 dark:bg-zinc-800">
                    <Plus class="h-8 w-8 text-zinc-400" />
                </div>
                <h3 class="text-lg font-semibold">Seleccione o cree un plato</h3>
                <p class="text-muted-foreground mt-2 max-w-xs text-sm">
                    Seleccione un plato de la lista izquierda para editar sus detalles o cree uno nuevo para comenzar.
                </p>
                <Button @click="createDish" class="mt-6"> Crear Nuevo Plato </Button>
            </div>
        </div>
    </div>
</template>
