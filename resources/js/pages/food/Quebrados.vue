<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Dish, Ingredient } from '@/types';
import Button from '@/components/ui/button/Button.vue';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Plus, Trash, Search, Copy, FileUp } from 'lucide-vue-next';
import CalcPopover from './CalcPopover.vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps<{
    dishes: Dish[];
    ingredients: Ingredient[];
}>();

// State
const searchQuery = ref('');
const dishesSearched = ref<Dish[]>(props.dishes || []);

watch(() => props.dishes, (newVal) => {
    if (!searchQuery.value) {
        dishesSearched.value = newVal || [];
    }
}, { deep: true });
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
        }
    });
};

const form = useForm({
    id: null as number | null,
    name: '',
    description: '',
    mesearument_unit: [] as number[],
    recipes: {} as Record<number, {
        ingredients: any[],
        total_gross_weight: number,
        total_waste_weight: number,
        total_calories: number,
        total_cost: number,
        total_net_weight: number,
    }>
});

const levels = [
    { id: 1, name: 'Master' },
    { id: 2, name: 'Staff' },
    { id: 3, name: 'Empleado' },
    { id: 4, name: 'Obrero' },
];

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

    axios.get(route('dishes.search', value))
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
            return sum + (nfactor * comp);
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
    const sourceLevelId = form.recipes[1] && form.recipes[1].ingredients.length > 0 
        ? 1 
        : form.mesearument_unit.find(id => form.recipes[id] && form.recipes[id].ingredients.length > 0);
    
    if (sourceLevelId && sourceLevelId !== activeLevelTab.value) {
        const sourceRecipe = form.recipes[sourceLevelId];
        form.recipes[activeLevelTab.value] = JSON.parse(JSON.stringify(sourceRecipe));
        
        Swal.fire({
            title: 'Ingredientes Duplicados',
            text: 'Se copiaron los ingredientes correctamente.',
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
        });
    } else {
        Swal.fire({
            title: 'Atención',
            text: 'No hay ingredientes en otros niveles para duplicar.',
            icon: 'info'
        });
    }
};

const editDish = (dish: Dish) => {
    form.clearErrors();
    isCreating.value = false;
    
    form.id = dish.id;
    form.name = dish.name;
    form.description = dish.description || '';
    
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
                    const fullIng = props.ingredients?.find(i => i.id === ing.id);
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
                })
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
                    const fullIng = props.ingredients?.find(i => i.id === ing.id);
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
                })
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
        text: "No podrás revertir esto",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('dishes.destroy', id), {
                onSuccess: () => {
                    dishesSearched.value = dishesSearched.value.filter(d => d.id !== id);
                    if (form.id === id) {
                        resetView();
                    }
                }
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

    axios.get(route('dishes.search-ingredients', value))
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
    const weightInGrams = (ingredient.selected_unit === 'Kg' || ingredient.selected_unit === 'kg') ? inputVal * 1000 : inputVal;
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
        ingredient.cost = (ingredient.selected_unit === 'Kg' || ingredient.selected_unit === 'kg')
            ? inputVal * ingredient.unit_price 
            : (inputVal / 1000) * ingredient.unit_price;
    }
    
    recalculateTotals();
};

const submit = () => {
    if (form.id) {
        form.put(route('dishes.update', form.id), {
            onSuccess: () => {
                resetView();
                Swal.fire({
                    title: '¡Actualizado!',
                    text: 'El quebrado se ha guardado exitosamente.',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
            },
        });
    } else {
        form.post(route('dishes.store'), {
            onSuccess: () => {
                resetView();
                Swal.fire({
                    title: '¡Creado!',
                    text: 'El quebrado se ha creado exitosamente.',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
            },
        });
    }
};
</script>

<template>
    <!-- Content Wrapper -->
    <div class="flex h-full gap-0 md:gap-4 p-0 md:p-4 overflow-hidden bg-white dark:bg-zinc-950 rounded-xl border border-zinc-200 dark:border-zinc-800">
        <!-- LEFT PANEL: Dish List -->
        <div class="w-full md:w-1/3 flex flex-col gap-4 border-r pr-0 md:pr-4 bg-white dark:bg-zinc-950 z-10" :class="{'hidden md:flex': form.id !== null || isCreating}">
            <div class="flex items-center gap-2 p-4 md:p-0 border-b md:border-0">
                <div class="relative flex-1">
                    <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-zinc-500" />
                    <Input 
                        @keyup="searchDish" 
                        placeholder="Buscar plato..." 
                        class="pl-9 w-full" 
                    />
                </div>
                <input 
                    type="file" 
                    ref="fileInput" 
                    class="hidden" 
                    accept=".xlsx,.xls,.csv"
                    @change="handleFileUpload"
                />
                <Button @click="fileInput?.click()" variant="outline" class="flex items-center gap-2 border-zinc-200" title="Importar platos e ingredientes">
                    <FileUp class="h-4 w-4 text-zinc-500" />
                    <span class="hidden lg:inline text-xs">Importar</span>
                </Button>
                <Button @click="createDish" size="icon" class="shrink-0 bg-primary hover:bg-primary/90" title="Nuevo plato">
                    <Plus class="h-4 w-4" />
                </Button>
            </div>
            
            <div class="flex-1 overflow-y-auto space-y-1 pr-2 scrollbar-thin scrollbar-thumb-zinc-200 dark:scrollbar-thumb-zinc-800 p-2">
                <div 
                    v-for="dish in dishesSearched" 
                    :key="dish.id"
                    @click="editDish(dish)"
                    class="group p-3 rounded-lg border cursor-pointer transition-all hover:bg-zinc-100 dark:hover:bg-zinc-900 relative"
                    :class="form.id === dish.id ? 'bg-zinc-100 dark:bg-zinc-900 border-primary' : 'border-transparent bg-zinc-50 dark:bg-zinc-900/50'"
                >
                    <div class="flex justify-between items-start">
                        <div class="font-medium text-sm">{{ dish.name }}</div>
                    </div>
                    <div class="text-xs text-muted-foreground line-clamp-2 mt-1">{{ dish.description }}</div>
                    <div class="flex flex-wrap gap-1 mt-2">
                        <div class="text-[10px] px-1.5 py-0.5 rounded-full bg-zinc-200 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
                            {{ getDishIngredientsCount(dish) }} Ingredientes
                        </div>
                        <span v-for="recipe in dish.recipes" :key="recipe.id" class="text-[9px] px-1.5 py-0.5 rounded-md bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 font-bold border border-blue-100 dark:border-blue-800/50">
                            {{ recipe.level?.name }}
                        </span>
                    </div>
                    
                    <div class="absolute top-2 right-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <Button 
                            @click.stop="duplicateDish(dish)" 
                            variant="ghost" 
                            size="icon" 
                            class="h-7 w-7 text-zinc-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-all border border-transparent hover:border-blue-100"
                            title="Duplicar plato"
                        >
                            <Copy class="h-3.5 w-3.5" />
                        </Button>
                        <Button 
                            @click.stop="deleteDish(dish.id)" 
                            variant="ghost" 
                            size="icon" 
                            class="h-7 w-7 text-zinc-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition-all border border-transparent hover:border-red-100"
                            title="Eliminar plato"
                        >
                            <Trash class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT PANEL: Form/Details -->
        <div class="flex-1 flex flex-col h-full overflow-hidden bg-zinc-50/30 dark:bg-zinc-900/10 rounded-xl md:border md:border-dashed md:border-zinc-200 dark:md:border-zinc-800 p-1" :class="{'fixed inset-0 z-50 bg-white dark:bg-zinc-950 md:static md:bg-transparent': form.id !== null || isCreating, 'hidden md:flex': !form.id && !isCreating}">
            <div v-if="form.id !== null || isCreating" class="h-full flex flex-col">
                <div class="p-4 border-b flex justify-between items-center bg-white dark:bg-zinc-950 rounded-t-xl shrink-0">
                    <div>
                        <h3 class="font-bold text-lg leading-none">{{ form.id ? 'Editar Plato' : 'Nuevo Plato' }}</h3>
                        <p class="text-xs text-muted-foreground mt-1">Configure los ingredientes y valores del plato.</p>
                    </div>
                    <div class="flex gap-2">
                            <Button type="button" variant="ghost" size="sm" @click="resetView">Cancelar</Button>
                            <Button type="button" size="sm" @click="submit" :disabled="form.processing">
                            {{ form.id ? 'Guardar' : 'Crear' }}
                        </Button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-4 space-y-6 bg-white dark:bg-zinc-950">
                    <!-- Basic Info Inputs -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold uppercase text-zinc-500">Nombre</label>
                            <Input v-model="form.name" placeholder="Nombre del plato" class="h-9" />
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold uppercase text-zinc-500">Descripción</label>
                            <Input v-model="form.description" placeholder="Descripción breve" class="h-9" />
                        </div>
                        <div class="space-y-1.5 col-span-1 md:col-span-3">
                            <label class="text-xs font-semibold uppercase text-zinc-500">Niveles de Aplicación</label>
                            <div class="flex flex-wrap gap-2 pt-1">
                                <button 
                                    v-for="level in levels" 
                                    :key="level.id"
                                    type="button"
                                    @click="toggleLevel(level.id)"
                                    class="px-3 py-1.5 rounded-full text-xs font-bold transition-all border"
                                    :class="form.mesearument_unit.includes(level.id) 
                                        ? 'bg-zinc-900 border-zinc-900 text-white shadow-sm' 
                                        : 'bg-zinc-50 border-zinc-200 text-zinc-600 hover:bg-zinc-100'"
                                >
                                    {{ level.name }}
                                </button>
                            </div>
                            <p v-if="form.errors.mesearument_unit" class="text-[10px] text-red-500 font-medium">{{ form.errors.mesearument_unit }}</p>
                        </div>
                    </div>
                    
                    <!-- Tabs de Niveles -->
                    <div v-if="form.mesearument_unit.length > 0" class="mt-4 border-b">
                        <div class="flex gap-2 -mb-px overflow-x-auto pb-1 scrollbar-none">
                            <button 
                                v-for="levelId in form.mesearument_unit" 
                                :key="levelId"
                                type="button"
                                @click="activeLevelTab = levelId"
                                class="px-4 py-2 text-xs font-bold transition-all border-b-2 whitespace-nowrap"
                                :class="activeLevelTab === levelId 
                                    ? 'border-primary text-primary' 
                                    : 'border-transparent text-zinc-400 hover:text-zinc-600'"
                            >
                                {{ levels.find(l => l.id === levelId)?.name }}
                            </button>
                        </div>
                    </div>

                    <!-- Ingredients & Calculator -->
                    <div v-if="activeLevelTab && form.recipes[activeLevelTab]" class="space-y-3 pt-2">
                        <div class="flex items-center justify-between">
                            <h4 class="font-semibold text-sm">Ingredientes - {{ levels.find(l => l.id === activeLevelTab)?.name }}</h4>
                            <div class="relative w-full max-w-xs">
                                    <Search class="absolute left-2 top-2 h-3.5 w-3.5 text-zinc-400" />
                                    <Input placeholder="Buscar ingrediente..." @keyup="searchIngredients" class="h-8 pl-8 text-xs" />
                                    <!-- Dropdown Results -->
                                    <div v-if="ingredientsFounded.length > 0" class="absolute top-full left-0 right-0 mt-1 p-1 bg-white dark:bg-zinc-900 border rounded-md shadow-lg z-50 max-h-48 overflow-y-auto">
                                        <div
                                            v-for="ingredient in ingredientsFounded"
                                            :key="ingredient.id"
                                            @click="selectIngredient(ingredient)"
                                            class="flex items-center gap-2 p-2 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-sm cursor-pointer text-sm"
                                        >
                                            <Plus class="w-3 h-3 text-primary" />
                                            <span>{{ ingredient.name }}</span>
                                        </div>
                                    </div>
                            </div>
                        </div>

                        <div class="border rounded-md overflow-hidden bg-white dark:bg-zinc-950">
                            <Table>
                                <TableHeader class="bg-zinc-50 dark:bg-zinc-900 sticky top-0 z-20">
                                    <TableRow class="hover:bg-transparent border-none">
                                        <TableHead class="h-10 text-[10px] uppercase font-black bg-zinc-500 text-white border-r border-zinc-400">Insumo</TableHead>
                                        <TableHead class="h-10 text-center text-[10px] uppercase font-black bg-blue-400 text-white border-r border-zinc-400">P. Unit</TableHead>
                                        <TableHead class="h-10 text-center text-[10px] uppercase font-black bg-purple-400 text-white border-r border-zinc-400">P. x Gr</TableHead>
                                        <TableHead class="h-10 text-center text-[10px] uppercase font-black bg-blue-900 text-white border-r border-zinc-400">Cant.</TableHead>
                                        <TableHead class="h-10 text-center text-[10px] uppercase font-black bg-lime-400 text-zinc-900 border-r border-zinc-400">Und</TableHead>
                                        <TableHead class="h-10 text-center text-[10px] uppercase font-black bg-sky-400 text-blue-900 border-r border-zinc-400">Costo Base</TableHead>
                                        <TableHead class="h-10 text-center text-[10px] uppercase font-black bg-red-900 text-white border-r border-zinc-400">Mat. Prima</TableHead>
                                        <TableHead class="h-10 text-center text-[10px] uppercase font-black bg-teal-900 text-white border-r border-zinc-400">Desecho</TableHead>
                                        <TableHead class="h-10 text-center text-[10px] uppercase font-black bg-orange-800 text-white border-r border-zinc-400">Prod Final</TableHead>
                                        <TableHead class="h-10 text-center text-[10px] uppercase font-black bg-orange-400 text-white border-r border-zinc-400">Calorías</TableHead>
                                        <TableHead class="h-10 text-center bg-zinc-100"></TableHead>
                                        <TableHead class="h-10 text-right w-[40px] bg-zinc-100">Opc.</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="ingredient in form.recipes[activeLevelTab].ingredients" :key="ingredient.id" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-900/50 transition-colors border-b border-zinc-200">
                                        <!-- Insumo -->
                                        <TableCell class="py-1 px-2 border-r border-zinc-100">
                                            <div class="font-bold text-[11px] text-blue-800 leading-tight uppercase">{{ ingredient.name }}</div>
                                        </TableCell>

                                        <!-- Precio Unitario -->
                                        <TableCell class="py-1 px-1 text-center border-r border-zinc-100 bg-amber-50/30">
                                            <span class="font-bold text-xs text-red-600">{{ Number(ingredient.unit_price || 0).toFixed(2) }}</span>
                                        </TableCell>

                                        <!-- Precio x Gr -->
                                        <TableCell class="py-1 px-1 text-center border-r border-zinc-100 bg-purple-50/30">
                                            <span class="font-mono text-[10px] text-purple-700">{{ Number((ingredient.unit_price || 0) / 1000).toFixed(5) }}</span>
                                        </TableCell>

                                        <!-- Cantidad Input -->
                                        <TableCell class="py-1 px-1 text-center border-r border-zinc-100 bg-blue-50/50">
                                            <input 
                                                type="number" 
                                                v-model="ingredient.input_quantity" 
                                                @input="onWeightInput(ingredient)"
                                                class="h-7 w-16 text-center border border-red-800 rounded-none font-bold text-xs shadow-inner focus:outline-none"
                                            />
                                        </TableCell>

                                        <!-- Unidad Select -->
                                        <TableCell class="py-1 px-1 text-center border-r border-zinc-100 bg-lime-50/30">
                                            <select 
                                                v-model="ingredient.selected_unit" 
                                                @change="onWeightInput(ingredient)"
                                                class="h-7 text-[10px] font-bold border-none bg-transparent text-red-800 focus:ring-0"
                                            >
                                                <option value="Kg">Kg</option>
                                                <option value="g">Gr</option>
                                            </select>
                                        </TableCell>

                                        <!-- Costo Base -->
                                        <TableCell class="py-1 px-1 text-center border-r border-zinc-100 bg-sky-50">
                                            <span class="font-bold text-xs text-blue-800">{{ Number(ingredient.cost).toFixed(2) }}</span>
                                        </TableCell>

                                        <!-- Materia Prima (Gr) -->
                                        <TableCell class="py-1 px-1 text-center border-r border-zinc-100">
                                            <span class="font-mono text-xs">{{ Number(ingredient.gross_weight).toFixed(1) }}</span>
                                        </TableCell>

                                        <!-- Desecho (Gr) -->
                                        <TableCell class="py-1 px-1 text-center border-r border-zinc-100">
                                            <span class="font-mono text-xs text-zinc-600">{{ Number(ingredient.solid_waste).toFixed(1) }}</span>
                                        </TableCell>

                                        <!-- Producto Final (Gr) -->
                                        <TableCell class="py-1 px-1 text-center border-r border-zinc-100 font-bold">
                                            <span class="font-mono text-xs text-red-900">{{ Number(ingredient.final_product).toFixed(1) }}</span>
                                        </TableCell>

                                        <!-- Calorías -->
                                        <TableCell class="py-1 px-1 text-center border-r border-zinc-100">
                                            <span class="font-mono text-xs">{{ Number(ingredient.calories).toFixed(2) }}</span>
                                        </TableCell>

                                        <!-- Calculadora -->
                                        <TableCell class="py-1 px-1 text-center align-middle">
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

                                        <TableCell class="py-1 px-1 text-right">
                                            <Button variant="ghost" size="icon" @click="removeIngredientFromForm(ingredient.id)" class="h-6 w-6 text-red-500 hover:text-red-700">
                                                <Trash class="h-3 w-3" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="form.recipes[activeLevelTab].ingredients.length === 0">
                                        <TableCell colspan="12" class="h-32 text-center text-muted-foreground text-sm border-dashed">
                                            <div class="flex flex-col items-center gap-2">
                                                <div class="p-2 rounded-full bg-zinc-50 dark:bg-zinc-900">
                                                    <Plus class="w-4 h-4 text-zinc-400" />
                                                </div>
                                                <span>Seleccione ingredientes en el buscador superior para comenzar.</span>
                                                <Button 
                                                    v-if="form.mesearument_unit.length > 1" 
                                                    type="button" 
                                                    @click="copyIngredientsFromMaster" 
                                                    variant="outline" 
                                                    size="sm" 
                                                    class="mt-2 text-xs border-blue-200 text-blue-600 hover:bg-blue-50 dark:border-blue-900/50 dark:text-blue-400 dark:hover:bg-blue-900/30"
                                                >
                                                    <Copy class="w-3 h-3 mr-2" />
                                                    Duplicar ingredientes (Master)
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- Totals Footer -->
                        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-5 gap-2 pt-4 border-t sticky bottom-0 bg-white dark:bg-zinc-950 pb-2">
                            <div class="p-2 bg-zinc-50 dark:bg-zinc-900 rounded border text-center">
                                <div class="text-[10px] uppercase text-muted-foreground font-bold mb-1">P. Bruto</div>
                                <div class="font-mono font-bold text-sm">{{ Number(form.recipes[activeLevelTab].total_gross_weight).toFixed(2) }}</div>
                            </div>
                            <div class="p-2 bg-zinc-50 dark:bg-zinc-900 rounded border text-center">
                                <div class="text-[10px] uppercase text-muted-foreground font-bold mb-1">Mermas Tot.</div>
                                <div class="font-mono font-bold text-amber-600 text-sm">{{ Number(form.recipes[activeLevelTab].total_waste_weight).toFixed(2) }}</div>
                            </div>
                            <div class="p-2 bg-zinc-50 dark:bg-zinc-900 rounded border text-center">
                                <div class="text-[10px] uppercase text-muted-foreground font-bold mb-1">Calorías</div>
                                <div class="font-mono font-bold text-rose-600 text-sm">{{ Number(form.recipes[activeLevelTab].total_calories).toFixed(2) }}</div>
                            </div>
                            <div class="p-2 bg-zinc-50 dark:bg-zinc-900 rounded border text-center">
                                <div class="text-[10px] uppercase text-muted-foreground font-bold mb-1">Costo</div>
                                <div class="font-mono font-bold text-emerald-600 text-sm">S/. {{ Number(form.recipes[activeLevelTab].total_cost).toFixed(2) }}</div>
                            </div>
                            <div class="p-2 bg-zinc-50 dark:bg-zinc-900 rounded border text-center">
                                <div class="text-[10px] uppercase text-muted-foreground font-bold mb-1">P. Final</div>
                                <div class="font-mono font-bold text-indigo-600 text-sm">{{ Number(form.recipes[activeLevelTab].total_net_weight).toFixed(2) }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State for Recipes -->
                    <div v-else-if="form.mesearument_unit.length > 0" class="flex flex-col items-center justify-center h-48 text-muted-foreground">
                        <Plus class="w-8 h-8 opacity-20 mb-2" />
                        <p class="text-sm">Seleccione un nivel de aplicación arriba para ver su receta.</p>
                    </div>

                    <!-- No Levels Selected -->
                    <div v-else class="flex flex-col items-center justify-center h-48 text-muted-foreground border border-dashed rounded-lg">
                        <p class="text-sm">Debe seleccionar al menos un nivel de aplicación para configurar recetas.</p>
                    </div>
                </div>
            </div>
            
            <div v-else class="h-full flex flex-col items-center justify-center text-center p-8 bg-zinc-50 dark:bg-zinc-900/50">
                <div class="w-16 h-16 rounded-full bg-zinc-200 dark:bg-zinc-800 flex items-center justify-center mb-4">
                    <Plus class="w-8 h-8 text-zinc-400" />
                </div>
                <h3 class="font-semibold text-lg">Seleccione o cree un plato</h3>
                <p class="text-muted-foreground text-sm max-w-xs mt-2">
                    Seleccione un plato de la lista izquierda para editar sus detalles o cree uno nuevo para comenzar.
                </p>
                <Button @click="createDish" class="mt-6">
                    Crear Nuevo Plato
                </Button>
            </div>
        </div>
    </div>
</template>
