<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dish, Ingredient } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertCircle,
    ArrowLeft,
    Beef,
    Check,
    ChefHat,
    ChevronDown,
    Cookie,
    Copy,
    DollarSign,
    Droplets,
    FileUp,
    Flame,
    FlaskConical,
    Layers,
    ListFilter,
    Loader2,
    Plus,
    Scale,
    Search,
    Sparkles,
    Trash,
    Utensils,
    Waves,
    Wheat,
    X,
    Zap,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import CalcPopover from './CalcPopover.vue';

const props = defineProps<{
    dishes: Dish[];
    ingredients: Ingredient[];
    dishCategories: any[];
    levels: any[];
}>();

// Helper to format category names (preserving full name including prefixes like 'zBase(Master) | JUGO')
const formatCategoryName = (name: string) => {
    return name || '';
};

// State
const searchQuery = ref('');
const dishesSearched = ref<Dish[]>(props.dishes || []);
const isSearchingDishes = ref(false);
const categoryFilter = ref<string>('all');

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
const isSearchingIngredients = ref(false);
const ingredientSearchDone = ref(false);
const ingredientSearchQuery = ref('');
const isCreating = ref(false);
const activeLevelTab = ref<number | null>(null);

// Snapshot to detect unsaved changes in the form
const formSnapshot = ref<string | null>(null);

const takeSnapshot = () => {
    formSnapshot.value = JSON.stringify(form.data());
};

const hasUnsavedChanges = () => {
    if (form.id === null && !isCreating.value) return false;
    if (formSnapshot.value === null) return false;
    return JSON.stringify(form.data()) !== formSnapshot.value;
};

const confirmDiscard = async (): Promise<boolean> => {
    if (!hasUnsavedChanges()) return true;
    const result = await Swal.fire({
        title: 'Cambios sin guardar',
        text: 'Tiene cambios sin guardar en este plato. ¿Desea descartarlos?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Sí, descartar',
        cancelButtonText: 'Seguir editando',
        customClass: {
            popup: 'rounded-2xl dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-100',
        },
    });
    return result.isConfirmed;
};

const filteredDishes = computed(() => {
    if (categoryFilter.value === 'all') return dishesSearched.value;
    const catId = Number(categoryFilter.value);
    return dishesSearched.value.filter((d: any) => d.dish_categories?.some((c: any) => c.id === catId));
});

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

watch(
    () => props.levels,
    (newLevels) => {
        localLevels.value = [...(newLevels || [])];
    },
);

const addNewLevel = async () => {
    const { value: name } = await Swal.fire({
        title: 'Nuevo Nivel de Aplicación',
        input: 'text',
        inputLabel: 'Nombre del nivel (Ej. VIP, Menú, Especial)',
        inputPlaceholder: 'Nombre del recetario',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        customClass: {
            popup: 'rounded-2xl dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-100',
        },
    });

    if (name) {
        try {
            const response = await axios.post(route('levels.store'), { name });
            localLevels.value.push(response.data);
            router.reload({ only: ['levels'] });
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
                    router.reload({ only: ['levels'] });
                    const index = form.mesearument_unit.indexOf(levelId);
                    if (index !== -1) {
                        form.mesearument_unit.splice(index, 1);
                        delete form.recipes[levelId];
                        if (activeLevelTab.value === levelId) {
                            activeLevelTab.value = form.mesearument_unit.length ? form.mesearument_unit[0] : null;
                        }
                    }
                })
                .catch(() => {
                    Swal.fire({
                        title: 'Error',
                        text: 'No se pudo eliminar el nivel. Es posible que esté en uso.',
                        icon: 'error',
                    });
                });
        }
    });
};

const toggleLevel = async (id: number) => {
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
        return;
    }

    // Si el recetario tiene ingredientes cargados, quitarlo lo borraría al guardar: se pide
    // confirmación antes de descartarlo.
    const recipe = form.recipes[id];
    if (recipe?.ingredients?.length > 0) {
        const levelName = localLevels.value.find((l) => l.id === id)?.name || 'este nivel';
        const result = await Swal.fire({
            title: '¿Quitar nivel de aplicación?',
            text: `El recetario "${levelName}" tiene ingredientes cargados. Al desactivarlo se eliminará su receta de este plato. ¿Desea continuar?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar receta',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc2626',
        });
        if (!result.isConfirmed) return;
    }

    form.mesearument_unit.splice(index, 1);
    delete form.recipes[id];
    if (activeLevelTab.value === id) {
        activeLevelTab.value = form.mesearument_unit.length ? form.mesearument_unit[0] : null;
    }
};

// List Logic
let dishSearchTimer: ReturnType<typeof setTimeout> | null = null;

const performDishSearch = (value: string) => {
    if (!value) {
        isSearchingDishes.value = false;
        dishesSearched.value = props.dishes || [];
        return;
    }

    isSearchingDishes.value = true;
    axios
        .get(route('dishes.search', value))
        .then((result) => {
            dishesSearched.value = result.data;
        })
        .catch((err) => {
            console.error(err);
        })
        .finally(() => {
            isSearchingDishes.value = false;
        });
};

const searchDish = (e: Event) => {
    const value = (e.target as HTMLInputElement).value;
    searchQuery.value = value;
    if (dishSearchTimer) clearTimeout(dishSearchTimer);

    if (!value) {
        performDishSearch(value);
        return;
    }

    dishSearchTimer = setTimeout(() => performDishSearch(value), 300);
};

const clearDishSearch = () => {
    searchQuery.value = '';
    performDishSearch('');
};

// Un nutriente expresado en gramos por 100 g de alimento nunca puede superar 100. Los datos
// importados por Excel (DosificationsImport) quedan en mg/kg (~10000× un valor de g/100g), mientras
// que los ingresados a mano en "Valores Nutricionales" ya están en g/100g. Como ambas rutas escriben
// en las mismas columnas, se detecta la escala por campo en vez de asumir una sola.
const toGramsPer100g = (rawValue: unknown): number => {
    const value = parseFloat(rawValue as string) || 0;
    return value > 100 ? value / 10000 : value;
};

const calculateIngredientCalories = (ingredient: any) => {
    const dosification = ingredient?.dosification;
    const atwaterFactor = ingredient?.atwater_factor || ingredient?.atwaterFactor;

    // Fórmula Atwater: kcal = proteína×factor + lípidos×factor + carbohidratos×factor, usando el
    // factor asignado a este ingrediente.
    if (dosification && atwaterFactor) {
        const protein = toGramsPer100g(dosification.protein);
        const lipid = toGramsPer100g(dosification.lipid);
        // El término de carbohidratos usa los disponibles (total menos fibra), que es la base
        // correcta para el cálculo Atwater; si el ingrediente aún no la tiene cargada, se usa el
        // total como respaldo.
        const carbohydrate =
            dosification.carbohydrate_available !== null && dosification.carbohydrate_available !== undefined
                ? toGramsPer100g(dosification.carbohydrate_available)
                : toGramsPer100g(dosification.carbohydrate);

        const proteinFactor = parseFloat(atwaterFactor.protein_kcal) || 0;
        const fatFactor = parseFloat(atwaterFactor.fat_kcal) || 0;
        const carbFactor = parseFloat(atwaterFactor.carb_kcal) || 0;

        return protein * proteinFactor + lipid * fatFactor + carbohydrate * carbFactor;
    }

    // Ingrediente sin factor Atwater asignado todavía: se conserva el cálculo anterior.
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

// Modal de "Valores Nutricionales" accesible desde la fila del ingrediente, para editar sus
// macronutrientes sin salir del editor de platos.
const isRecipeDosificationModalOpen = ref(false);
const isSavingRecipeDosification = ref(false);
const activeIngredientForDosification = ref<any>(null);
const recipeDosificationErrors = ref<Record<string, string>>({});
const recipeDosificationForm = ref({
    energy: null as number | null,
    water: null as number | null,
    protein: null as number | null,
    lipid: null as number | null,
    carbohydrate: null as number | null,
    carbohydrate_available: null as number | null,
    fiber: null as number | null,
});

const openRecipeDosificationModal = (ingredient: any) => {
    activeIngredientForDosification.value = ingredient;
    const d = ingredient.dosification;
    recipeDosificationForm.value = {
        energy: d?.energy ?? null,
        water: d?.water ?? null,
        protein: d?.protein ?? null,
        lipid: d?.lipid ?? null,
        carbohydrate: d?.carbohydrate ?? null,
        carbohydrate_available: d?.carbohydrate_available ?? null,
        fiber: d?.fiber ?? null,
    };
    recipeDosificationErrors.value = {};
    isRecipeDosificationModalOpen.value = true;
};

const submitRecipeDosification = async () => {
    if (!activeIngredientForDosification.value || !activeLevelTab.value) return;

    isSavingRecipeDosification.value = true;
    recipeDosificationErrors.value = {};
    try {
        const response = await axios.post(
            route('ingredients.dosification.update', activeIngredientForDosification.value.id),
            recipeDosificationForm.value,
        );

        // Actualiza el ingrediente en el recetario activo y recalcula sus calorías con los nuevos
        // valores, sin recargar la página (para no perder el resto del plato en edición).
        const recipe = form.recipes[activeLevelTab.value];
        const ingredientIndex = recipe.ingredients.findIndex((ing: any) => ing.id === activeIngredientForDosification.value.id);
        if (ingredientIndex !== -1) {
            const ing = recipe.ingredients[ingredientIndex];
            ing.dosification = response.data.dosification;
            ing.originalValues.calories = calculateIngredientCalories(ing);
            ing.calories = (ing.gross_weight * ing.originalValues.calories) / 100;
            recalculateTotals();
        }

        isRecipeDosificationModalOpen.value = false;
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Valores nutricionales actualizados',
            showConfirmButton: false,
            timer: 1800,
            timerProgressBar: true,
        });
    } catch (error: any) {
        if (error?.response?.status === 422) {
            const errors = error.response.data.errors || {};
            recipeDosificationErrors.value = Object.fromEntries(Object.entries(errors).map(([key, msgs]: [string, any]) => [key, msgs[0]]));
        } else {
            console.error('Error updating dosification:', error);
            Swal.fire({ title: 'Error', text: 'No se pudo actualizar la dosificación.', icon: 'error', confirmButtonText: 'Entendido' });
        }
    } finally {
        isSavingRecipeDosification.value = false;
    }
};

const sortedRecipes = (dish: any) =>
    [...(dish.recipes || [])].sort((a: any, b: any) => (b.ingredients?.length || 0) - (a.ingredients?.length || 0));

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

const editDish = async (dish: Dish) => {
    if (form.id === dish.id) return;
    if (!(await confirmDiscard())) return;
    form.clearErrors();
    isCreating.value = false;

    form.id = dish.id;
    form.name = dish.name;
    form.description = dish.description || '';
    // @ts-ignore
    form.dish_categories = dish.dish_categories || [];

    loadRecipesIntoForm(dish);
    takeSnapshot();
};

const loadRecipesIntoForm = (dish: Dish) => {
    form.mesearument_unit = [];
    form.recipes = {};

    if (dish.recipes && dish.recipes.length > 0) {
        sortedRecipes(dish).forEach((recipe: any) => {
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

const duplicateDish = async (dish: Dish) => {
    if (!(await confirmDiscard())) return;
    form.clearErrors();
    isCreating.value = true;

    form.id = null;
    form.name = dish.name + ' (Copia)';
    form.description = dish.description || '';
    // @ts-ignore
    form.dish_categories = dish.dish_categories || [];

    loadRecipesIntoForm(dish);
    takeSnapshot();
};

const createDish = async () => {
    if (!(await confirmDiscard())) return;
    form.clearErrors();
    form.reset();
    form.id = null;
    form.dish_categories = [];
    form.mesearument_unit = [];
    form.recipes = {};
    activeLevelTab.value = null;
    isCreating.value = true;
    takeSnapshot();
};

const resetView = async (skipConfirm = false) => {
    if (!skipConfirm && !(await confirmDiscard())) return;
    form.reset();
    form.id = null;
    isCreating.value = false;
    formSnapshot.value = null;
};

const deleteDish = (id: number) => {
    Swal.fire({
        title: '¿Estás seguro?',
        text: 'No podrás revertir esta acción.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        customClass: {
            popup: 'rounded-2xl dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-100',
        },
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('dishes.destroy', id), {
                onSuccess: () => {
                    dishesSearched.value = dishesSearched.value.filter((d) => d.id !== id);
                    if (form.id === id) {
                        resetView(true);
                    }
                },
            });
        }
    });
};

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

let ingredientSearchTimer: ReturnType<typeof setTimeout> | null = null;
watch(ingredientSearchQuery, (value) => {
    if (ingredientSearchTimer) clearTimeout(ingredientSearchTimer);

    if (!value) {
        ingredientsFounded.value = [];
        ingredientSearchDone.value = false;
        isSearchingIngredients.value = false;
        return;
    }

    isSearchingIngredients.value = true;
    ingredientSearchTimer = setTimeout(() => {
        axios
            .get(route('dishes.search-ingredients', value))
            .then((result) => {
                ingredientsFounded.value = result.data;
                ingredientSearchDone.value = true;
            })
            .catch((err) => {
                console.error(err);
            })
            .finally(() => {
                isSearchingIngredients.value = false;
            });
    }, 300);
});

const closeIngredientSearch = () => {
    ingredientsFounded.value = [];
    ingredientSearchDone.value = false;
    ingredientSearchQuery.value = '';
    (document.activeElement as HTMLElement | null)?.blur();
};

const selectIngredient = (ingredient: Ingredient) => {
    if (!activeLevelTab.value) {
        Swal.fire({ title: 'Atención', text: 'Seleccione un nivel de aplicación primero.', icon: 'warning' });
        return;
    }
    const recipe = form.recipes[activeLevelTab.value];

    if (recipe.ingredients.some((ing: any) => ing.id === ingredient.id)) {
        Swal.fire({ title: 'Atención', text: 'El ingrediente ya está en la lista de este nivel.', icon: 'info' });
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
    ingredientSearchDone.value = false;
    ingredientSearchQuery.value = '';
    (document.activeElement as HTMLElement | null)?.blur();
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

    const weightInGrams = ingredient.selected_unit === 'Kg' || ingredient.selected_unit === 'kg' ? inputVal * 1000 : inputVal;
    ingredient.gross_weight = weightInGrams;

    const origWaste = parseFloat(ingredient.originalValues?.waste) || 0;
    const origCalories = parseFloat(ingredient.originalValues?.calories) || 0;

    ingredient.solid_waste = (weightInGrams * origWaste) / 100;
    ingredient.final_product = weightInGrams - ingredient.solid_waste;
    ingredient.calories = (ingredient.gross_weight * origCalories) / 100;

    if (ingredient.unit_price) {
        ingredient.cost =
            ingredient.selected_unit === 'Kg' || ingredient.selected_unit === 'kg'
                ? inputVal * ingredient.unit_price
                : (inputVal / 1000) * ingredient.unit_price;
    }

    recalculateTotals();
};

const submit = () => {
    if (!form.name.trim()) {
        Swal.fire({ title: 'Falta el nombre', text: 'Ingrese un nombre para el plato antes de guardar.', icon: 'warning' });
        return;
    }
    if (form.mesearument_unit.length === 0) {
        Swal.fire({ title: 'Sin niveles', text: 'Seleccione al menos un nivel de aplicación para configurar la receta.', icon: 'warning' });
        return;
    }
    const data = {
        ...form.data(),
        dish_categories: form.dish_categories.map((c) => c.id),
    };

    const options = {
        onSuccess: () => {
            resetView(true);
            if (searchQuery.value) performDishSearch(searchQuery.value);
            Swal.fire({
                title: form.id ? '¡Actualizado!' : '¡Creado!',
                text: `El quebrado se ha ${form.id ? 'guardado' : 'creado'} exitosamente.`,
                icon: 'success',
                timer: 2000,
                showConfirmButton: false,
            });
        },
        onError: (errors: any) => {
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

// Keyboard Shortcut: Ctrl+S / Cmd+S to save
const handleKeyDown = (e: KeyboardEvent) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        if (form.id !== null || isCreating.value) {
            submit();
        }
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
});
</script>

<template>
    <div class="flex h-full w-full overflow-hidden bg-zinc-50/50 dark:bg-zinc-950">
        <!-- LEFT PANEL: Dish List (Sidebar) -->
        <div
            class="z-10 flex h-full min-h-0 w-full min-w-0 flex-col border-r border-zinc-200/80 bg-white md:w-80 lg:w-[340px] shrink-0 dark:border-zinc-800 dark:bg-zinc-900/90"
            :class="{ 'hidden md:flex': form.id !== null || isCreating }"
        >
            <!-- Header Section -->
            <div class="space-y-2.5 p-3.5 border-b border-zinc-100 shrink-0 dark:border-zinc-800/80">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-xs">
                            <ChefHat class="h-4.5 w-4.5" />
                        </div>
                        <div>
                            <h2 class="text-sm font-extrabold text-zinc-900 dark:text-zinc-100 leading-tight">Platos y Recetas</h2>
                            <p class="text-[10px] font-medium text-zinc-400">Catálogo de quebrados</p>
                        </div>
                    </div>
                    <Badge variant="secondary" class="rounded-full bg-indigo-50 border border-indigo-100 px-2.5 py-0.5 text-[10px] font-extrabold text-indigo-600 dark:bg-indigo-950/60 dark:border-indigo-900/60 dark:text-indigo-400">
                        {{ filteredDishes.length }}
                    </Badge>
                </div>

                <!-- Search Input -->
                <div class="relative">
                    <Search v-if="!isSearchingDishes" class="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-indigo-500" />
                    <Loader2 v-else class="absolute left-2.5 top-2.5 h-3.5 w-3.5 animate-spin text-indigo-600 dark:text-indigo-400" />
                    <Input
                        :model-value="searchQuery"
                        @input="searchDish"
                        placeholder="Buscar plato o receta..."
                        class="h-8 w-full rounded-xl border-zinc-200 bg-zinc-50/80 pl-8 pr-7 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-zinc-800 dark:bg-zinc-800/50 dark:focus:bg-zinc-900"
                    />
                    <button
                        v-if="searchQuery"
                        @click="clearDishSearch"
                        class="absolute right-2 top-2 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                    >
                        <X class="h-3.5 w-3.5" />
                    </button>
                </div>

                <!-- Filter & Import Controls -->
                <div class="flex items-center gap-1.5">
                    <Select v-model="categoryFilter">
                        <SelectTrigger class="h-8 min-w-0 flex-1 rounded-lg border-zinc-200 text-[11px] dark:border-zinc-800">
                            <div class="flex items-center gap-1.5 truncate">
                                <ListFilter class="h-3 w-3 shrink-0 text-indigo-500" />
                                <span class="truncate">
                                    <SelectValue placeholder="Todas las categorías" />
                                </span>
                            </div>
                        </SelectTrigger>
                        <SelectContent class="max-h-72 rounded-xl">
                            <SelectItem value="all">Todas las categorías</SelectItem>
                            <SelectItem v-for="cat in dishCategories" :key="cat.id" :value="String(cat.id)">
                                {{ formatCategoryName(cat.name) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <input type="file" ref="fileInput" class="hidden" accept=".xlsx,.xls,.csv" @change="handleFileUpload" />
                    <Button
                        @click="fileInput?.click()"
                        variant="outline"
                        size="sm"
                        class="h-8 px-2 rounded-lg border-zinc-200 text-[11px] gap-1 text-zinc-600 hover:bg-zinc-100 shrink-0 dark:border-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-800"
                        title="Importar Excel"
                    >
                        <FileUp class="h-3 w-3 text-indigo-500" />
                        <span>Importar</span>
                    </Button>

                    <Button
                        @click="createDish"
                        size="sm"
                        class="h-8 px-3 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 font-bold text-[11px] gap-1 shadow-xs shadow-indigo-500/20 shrink-0"
                        title="Nuevo plato"
                    >
                        <Plus class="h-3.5 w-3.5" />
                        <span>Nuevo</span>
                    </Button>
                </div>
            </div>

            <!-- Dish List Content -->
            <div class="min-h-0 flex-1 overflow-y-auto p-2.5 space-y-1.5 scrollbar-thin scrollbar-thumb-zinc-200 dark:scrollbar-thumb-zinc-800">
                <div
                    v-if="filteredDishes.length === 0"
                    class="flex h-48 flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-zinc-200 text-center p-4 text-xs text-zinc-400 dark:border-zinc-800"
                >
                    <div class="rounded-full bg-indigo-50 p-2.5 text-indigo-500 dark:bg-indigo-950/60">
                        <Utensils class="h-5 w-5" />
                    </div>
                    <span v-if="searchQuery || categoryFilter !== 'all'">No se encontraron platos con estos filtros.</span>
                    <span v-else>Aún no hay platos. Cree el primero con el botón Nuevo.</span>
                </div>

                <div
                    v-for="dish in filteredDishes"
                    :key="dish.id"
                    @click="editDish(dish)"
                    class="group relative cursor-pointer rounded-xl border p-3 transition-all duration-150 hover:border-indigo-200 hover:bg-indigo-50/30 dark:hover:border-indigo-900/50 dark:hover:bg-indigo-950/20"
                    :class="
                        form.id === dish.id
                            ? 'border-indigo-500 bg-indigo-50/70 ring-1 ring-indigo-500/20 dark:border-indigo-500 dark:bg-indigo-950/40'
                            : 'border-zinc-200/70 bg-white dark:border-zinc-800/70 dark:bg-zinc-900/40'
                    "
                >
                    <!-- Left Accent Bar for selected dish -->
                    <div
                        v-if="form.id === dish.id"
                        class="absolute left-0 top-2.5 bottom-2.5 w-1 rounded-r-full bg-indigo-600 dark:bg-indigo-400"
                    ></div>

                    <div class="flex items-start justify-between gap-2">
                        <h3 class="text-xs font-extrabold text-zinc-900 dark:text-zinc-100 leading-snug line-clamp-1 pr-12">
                            {{ dish.name }}
                        </h3>
                    </div>

                    <p v-if="dish.description" class="mt-0.5 line-clamp-1 text-[11px] text-zinc-500 dark:text-zinc-400 leading-normal">
                        {{ dish.description }}
                    </p>

                    <!-- Tags Footer -->
                    <div class="mt-2 flex flex-wrap items-center gap-1">
                        <span class="rounded-md bg-zinc-100 px-1.5 py-0.5 text-[9px] font-bold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                            {{ getDishIngredientsCount(dish) }} ing.
                        </span>

                        <span
                            v-for="cat in dish.dish_categories"
                            :key="cat.id"
                            class="rounded-md border border-indigo-100 bg-indigo-50/80 px-1.5 py-0.5 text-[9px] font-bold text-indigo-600 dark:border-indigo-900/40 dark:bg-indigo-950/60 dark:text-indigo-400 max-w-full truncate"
                        >
                            {{ formatCategoryName(cat.name) }}
                        </span>

                        <span
                            v-for="recipe in dish.recipes"
                            :key="recipe.id"
                            class="rounded-md border border-sky-100 bg-sky-50/80 px-1.5 py-0.5 text-[9px] font-bold text-sky-600 dark:border-sky-900/40 dark:bg-sky-950/60 dark:text-sky-400"
                        >
                            {{ recipe.level?.name }}
                        </span>
                    </div>

                    <!-- Quick Action Buttons -->
                    <div class="absolute top-2 right-2 flex items-center gap-0.5 opacity-80 group-hover:opacity-100">
                        <Button
                            @click.stop="duplicateDish(dish)"
                            variant="ghost"
                            size="icon"
                            class="h-6 w-6 rounded-md text-zinc-400 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-950 dark:hover:text-indigo-400"
                            title="Duplicar plato"
                        >
                            <Copy class="h-3 w-3" />
                        </Button>
                        <Button
                            @click.stop="deleteDish(dish.id)"
                            variant="ghost"
                            size="icon"
                            class="h-6 w-6 rounded-md text-zinc-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950 dark:hover:text-rose-400"
                            title="Eliminar plato"
                        >
                            <Trash class="h-3 w-3" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT PANEL: Dish Details & Recipe Studio -->
        <div
            class="flex h-full min-h-0 flex-1 flex-col overflow-hidden bg-white dark:bg-zinc-950"
            :class="{
                'fixed inset-0 z-50 bg-white md:static dark:bg-zinc-950': form.id !== null || isCreating,
                'hidden md:flex': !form.id && !isCreating,
            }"
        >
            <template v-if="form.id !== null || isCreating">
                <!-- Header Bar -->
                <div class="sticky top-0 z-30 flex shrink-0 items-center justify-between border-b border-zinc-200/80 bg-white/90 px-4 py-2.5 backdrop-blur-md dark:border-zinc-800 dark:bg-zinc-950/90 md:px-5">
                    <div class="flex items-center gap-2.5">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-7 w-7 md:hidden"
                            @click="resetView()"
                        >
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-extrabold text-zinc-900 dark:text-zinc-100">
                                    {{ form.id ? 'Editar Plato' : 'Nuevo Plato' }}
                                </h3>
                                <span
                                    v-if="hasUnsavedChanges()"
                                    class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/50 dark:text-amber-400"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                    Sin guardar
                                </span>
                            </div>
                            <p class="text-[11px] text-zinc-400">Configure la información básica, categorías e ingredientes por recetario.</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <kbd class="hidden lg:inline-flex items-center gap-1 rounded-md border border-zinc-200 bg-zinc-100 px-1.5 py-0.5 text-[10px] font-mono text-zinc-400 dark:border-zinc-800 dark:bg-zinc-800" title="Atajo de teclado">
                            Ctrl + S
                        </kbd>

                        <Button type="button" variant="ghost" size="sm" class="h-8 px-2.5 text-xs font-semibold" @click="resetView()">
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            @click="submit"
                            :disabled="form.processing"
                            class="h-8 px-4 rounded-xl bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700 shadow-xs shadow-indigo-500/20 gap-1.5"
                        >
                            <Loader2 v-if="form.processing" class="h-3.5 w-3.5 animate-spin" />
                            <Check v-else class="h-3.5 w-3.5" />
                            <span>{{ form.processing ? 'Guardando...' : form.id ? 'Guardar Plato' : 'Crear Plato' }}</span>
                        </Button>
                    </div>
                </div>

                <!-- Main Form Body -->
                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-3.5 md:p-5 bg-zinc-50/40 dark:bg-zinc-950/40">
                    <!-- Basic Info Card -->
                    <div class="rounded-xl border border-zinc-200/80 bg-white p-3.5 md:p-4 shadow-xs space-y-3.5 dark:border-zinc-800 dark:bg-zinc-900/60">
                        <div class="grid grid-cols-1 gap-3.5 md:grid-cols-3">
                            <!-- Nombre -->
                            <div class="space-y-1">
                                <label class="text-[11px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Nombre del Plato</label>
                                <Input v-model="form.name" placeholder="Ej. Lomo Saltado Especial" class="h-8 rounded-lg border-zinc-200 bg-white text-xs font-medium dark:border-zinc-700 dark:bg-zinc-900" />
                            </div>

                            <!-- Descripción -->
                            <div class="space-y-1">
                                <label class="text-[11px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Descripción</label>
                                <Input v-model="form.description" placeholder="Breve detalle del plato" class="h-8 rounded-lg border-zinc-200 bg-white text-xs dark:border-zinc-700 dark:bg-zinc-900" />
                            </div>

                            <!-- Categorías -->
                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <label class="text-[11px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Categorías</label>
                                    <Popover>
                                        <PopoverTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="h-5 gap-1 px-1.5 text-[10px] font-bold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-950"
                                            >
                                                <span>Seleccionar</span>
                                                <ChevronDown class="h-3 w-3" />
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent class="w-64 p-0 rounded-xl shadow-xl border-zinc-200 dark:border-zinc-800" align="end">
                                            <div class="border-b border-zinc-100 dark:border-zinc-800 p-2">
                                                <h4 class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Seleccionar Categorías</h4>
                                            </div>
                                            <div class="max-h-60 overflow-y-auto p-1">
                                                <div
                                                    v-for="cat in dishCategories"
                                                    :key="cat.id"
                                                    @click="toggleCategory(cat)"
                                                    class="flex cursor-pointer items-center gap-2 rounded-lg p-1.5 transition-colors hover:bg-indigo-50 dark:hover:bg-indigo-950/60"
                                                >
                                                    <div
                                                        class="flex h-3.5 w-3.5 items-center justify-center rounded border transition-colors"
                                                        :class="isCategorySelected(cat.id) ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-zinc-300 dark:border-zinc-700'"
                                                    >
                                                        <Check v-if="isCategorySelected(cat.id)" class="h-2.5 w-2.5" />
                                                    </div>
                                                    <span
                                                        class="text-xs font-medium"
                                                        :class="isCategorySelected(cat.id) ? 'text-indigo-600 font-bold dark:text-indigo-400' : 'text-zinc-600 dark:text-zinc-400'"
                                                    >
                                                        {{ formatCategoryName(cat.name) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </PopoverContent>
                                    </Popover>
                                </div>
                                <div class="flex flex-wrap gap-1 pt-0.5">
                                    <Badge
                                        v-for="cat in form.dish_categories"
                                        :key="cat.id"
                                        variant="secondary"
                                        class="rounded-md border border-indigo-100 bg-indigo-50/80 px-2 py-0.5 text-[10px] font-bold text-indigo-600 gap-1 max-w-full truncate dark:border-indigo-900/50 dark:bg-indigo-950/60 dark:text-indigo-400"
                                    >
                                        <span class="truncate">{{ formatCategoryName(cat.name) }}</span>
                                        <button @click.stop="toggleCategory(cat)" class="hover:text-indigo-900 shrink-0 dark:hover:text-white">
                                            <X class="h-2.5 w-2.5" />
                                        </button>
                                    </Badge>
                                    <span v-if="!form.dish_categories?.length" class="text-[11px] text-zinc-400 italic">Sin categorías asignadas</span>
                                </div>
                            </div>
                        </div>

                        <!-- Niveles de Aplicación Selector -->
                        <div class="border-t border-zinc-100 pt-2.5 dark:border-zinc-800 space-y-1.5">
                            <div class="flex items-center justify-between">
                                <label class="text-[11px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                    Niveles de Aplicación (Recetarios)
                                </label>
                                <button
                                    type="button"
                                    @click="addNewLevel"
                                    class="inline-flex items-center gap-1 text-[11px] font-bold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                                >
                                    <Plus class="h-3 w-3" />
                                    <span>Añadir Nivel</span>
                                </button>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <div v-for="level in localLevels" :key="level.id" class="group relative">
                                    <button
                                        type="button"
                                        @click="toggleLevel(level.id)"
                                        class="flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-bold transition-all shadow-2xs"
                                        :class="
                                            form.mesearument_unit.includes(level.id)
                                                ? 'border-indigo-600 bg-indigo-600 text-white shadow-xs shadow-indigo-500/20'
                                                : 'border-zinc-200 bg-zinc-50 text-zinc-600 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-200 dark:border-zinc-800 dark:bg-zinc-800/80 dark:text-zinc-400 dark:hover:bg-indigo-950'
                                        "
                                    >
                                        <span>{{ level.name }}</span>
                                        <Trash
                                            @click.stop="deleteLevelFromList(level.id)"
                                            class="h-3 w-3 opacity-0 transition-opacity group-hover:opacity-100 hover:text-rose-400"
                                        />
                                    </button>
                                </div>
                            </div>
                            <p v-if="form.errors.mesearument_unit" class="text-xs font-semibold text-rose-500">{{ form.errors.mesearument_unit }}</p>
                        </div>
                    </div>

                    <!-- Recipe Level Tabs & Table -->
                    <div v-if="form.mesearument_unit.length > 0" class="space-y-3">
                        <!-- Level Tabs Navigation -->
                        <div class="flex items-center gap-1.5 overflow-x-auto border-b border-zinc-200 pb-1.5 dark:border-zinc-800">
                            <button
                                v-for="levelId in form.mesearument_unit"
                                :key="levelId"
                                type="button"
                                @click="activeLevelTab = levelId"
                                class="flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-extrabold transition-all whitespace-nowrap"
                                :class="
                                    activeLevelTab === levelId
                                        ? 'bg-indigo-600 text-white shadow-xs shadow-indigo-500/20'
                                        : 'text-zinc-500 hover:bg-indigo-50 hover:text-indigo-600 dark:text-zinc-400 dark:hover:bg-indigo-950/60'
                                "
                            >
                                <Layers class="h-3.5 w-3.5" />
                                <span>{{ localLevels.find((l) => l.id === levelId)?.name }}</span>
                                <Badge
                                    variant="secondary"
                                    class="rounded-md px-1.5 py-0 text-[9px] font-extrabold"
                                    :class="activeLevelTab === levelId ? 'bg-indigo-700 text-white' : 'bg-zinc-200 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400'"
                                >
                                    {{ form.recipes[levelId]?.ingredients.length || 0 }}
                                </Badge>
                            </button>
                        </div>

                        <!-- Ingredients & Calculator Table -->
                        <div v-if="activeLevelTab && form.recipes[activeLevelTab]" class="space-y-2.5">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-2">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-indigo-700 dark:text-indigo-400">
                                        Ingredientes — Receta {{ localLevels.find((l) => l.id === activeLevelTab)?.name }}
                                    </h4>
                                </div>

                                <!-- Ingredient Search Bar -->
                                <div class="relative w-full sm:w-72">
                                    <Search v-if="!isSearchingIngredients" class="absolute top-2 left-2.5 h-3.5 w-3.5 text-indigo-500" />
                                    <Loader2 v-else class="absolute top-2 left-2.5 h-3.5 w-3.5 animate-spin text-indigo-600 dark:text-indigo-400" />
                                    <Input
                                        v-model="ingredientSearchQuery"
                                        placeholder="Agregar ingrediente... (Esc para cerrar)"
                                        @keyup.esc="closeIngredientSearch"
                                        class="h-7.5 rounded-lg border-zinc-200 bg-white pl-8 text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-zinc-800 dark:bg-zinc-900"
                                    />
                                    <!-- Search Results Dropdown -->
                                    <div
                                        v-if="ingredientsFounded.length > 0 || (ingredientSearchDone && !isSearchingIngredients)"
                                        class="absolute left-0 right-0 top-full z-50 mt-1 max-h-56 overflow-y-auto rounded-xl border border-zinc-200 bg-white p-1 shadow-xl dark:border-zinc-800 dark:bg-zinc-900"
                                    >
                                        <div
                                            v-for="ingredient in ingredientsFounded"
                                            :key="ingredient.id"
                                            @click="selectIngredient(ingredient)"
                                            class="flex cursor-pointer items-center justify-between rounded-lg p-1.5 text-xs transition-colors hover:bg-indigo-50 dark:hover:bg-indigo-950/60"
                                        >
                                            <div class="flex items-center gap-2">
                                                <div class="flex h-5 w-5 items-center justify-center rounded bg-indigo-100 text-indigo-600 dark:bg-indigo-900/60 dark:text-indigo-400">
                                                    <Plus class="h-3 w-3" />
                                                </div>
                                                <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ ingredient.name }}</span>
                                            </div>
                                            <span v-if="ingredient.unit_price" class="text-[10px] font-mono font-bold text-amber-600 dark:text-amber-400">
                                                S/. {{ Number(ingredient.unit_price).toFixed(2) }}
                                            </span>
                                        </div>
                                        <div
                                            v-if="ingredientsFounded.length === 0"
                                            class="p-2.5 text-center text-xs text-zinc-400 italic"
                                        >
                                            No se encontraron ingredientes con ese nombre.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Table Container -->
                            <div class="overflow-hidden rounded-xl border border-zinc-200/80 bg-white shadow-2xs dark:border-zinc-800 dark:bg-zinc-900">
                                <div class="overflow-x-auto">
                                    <Table class="w-full text-xs">
                                        <TableHeader class="bg-zinc-900 text-zinc-100 dark:bg-zinc-900 dark:text-zinc-100">
                                            <TableRow class="border-zinc-800 hover:bg-transparent">
                                                <TableHead class="h-8 text-[10px] font-extrabold uppercase tracking-wider text-zinc-200">Insumo</TableHead>
                                                <TableHead class="h-8 text-center text-[10px] font-extrabold uppercase tracking-wider text-amber-300">P. Unit</TableHead>
                                                <TableHead class="h-8 text-center text-[10px] font-extrabold uppercase tracking-wider text-purple-300">P. x Gr</TableHead>
                                                <TableHead class="h-8 text-center text-[10px] font-extrabold uppercase tracking-wider text-sky-300">Cantidad</TableHead>
                                                <TableHead class="h-8 text-center text-[10px] font-extrabold uppercase tracking-wider text-lime-300">Und</TableHead>
                                                <TableHead class="h-8 text-center text-[10px] font-extrabold uppercase tracking-wider text-emerald-300">Costo Base</TableHead>
                                                <TableHead class="h-8 text-center text-[10px] font-extrabold uppercase tracking-wider text-zinc-300">Mat. Prima</TableHead>
                                                <TableHead class="h-8 text-center text-[10px] font-extrabold uppercase tracking-wider text-orange-300">Desecho</TableHead>
                                                <TableHead class="h-8 text-center text-[10px] font-extrabold uppercase tracking-wider text-indigo-300">Prod. Final</TableHead>
                                                <TableHead class="h-8 text-center text-[10px] font-extrabold uppercase tracking-wider text-rose-300">Calorías</TableHead>
                                                <TableHead class="h-8 text-center text-[10px] font-extrabold uppercase tracking-wider text-indigo-300">Ajustes</TableHead>
                                                <TableHead class="h-8 w-8 text-right"></TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow
                                                v-for="ingredient in form.recipes[activeLevelTab].ingredients"
                                                :key="ingredient.id"
                                                class="border-b border-zinc-100 dark:border-zinc-800/60 transition-colors hover:bg-indigo-50/30 dark:hover:bg-indigo-950/20"
                                            >
                                                <!-- Insumo -->
                                                <TableCell class="py-1.5 font-bold text-zinc-900 dark:text-zinc-100">
                                                    {{ ingredient.name }}
                                                </TableCell>

                                                <!-- Precio Unitario -->
                                                <TableCell class="py-1.5 text-center font-mono font-bold text-amber-600 dark:text-amber-400">
                                                    S/. {{ Number(ingredient.unit_price || 0).toFixed(2) }}
                                                </TableCell>

                                                <!-- Precio x Gr -->
                                                <TableCell class="py-1.5 text-center font-mono text-[11px] text-purple-600 dark:text-purple-400">
                                                    S/. {{ Number((ingredient.unit_price || 0) / 1000).toFixed(4) }}
                                                </TableCell>

                                                <!-- Cantidad Input -->
                                                <TableCell class="py-1.5 text-center">
                                                    <Input
                                                        type="number"
                                                        v-model="ingredient.input_quantity"
                                                        @input="onWeightInput(ingredient)"
                                                        step="any"
                                                        class="h-7 w-18 mx-auto rounded-lg border-zinc-200 text-center text-xs font-bold text-indigo-900 dark:text-indigo-200 dark:border-zinc-700 dark:bg-zinc-900 focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500"
                                                    />
                                                </TableCell>

                                                <!-- Unidad Select -->
                                                <TableCell class="py-1.5 text-center">
                                                    <select
                                                        v-model="ingredient.selected_unit"
                                                        @change="onWeightInput(ingredient)"
                                                        class="h-7 rounded-lg border-zinc-200 bg-zinc-50 text-[11px] font-bold text-zinc-800 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 focus:ring-2 focus:ring-indigo-500/30"
                                                    >
                                                        <option value="Kg">Kg</option>
                                                        <option value="g">Gr</option>
                                                    </select>
                                                </TableCell>

                                                <!-- Costo Base -->
                                                <TableCell class="py-1.5 text-center font-mono font-extrabold text-emerald-600 dark:text-emerald-400">
                                                    S/. {{ Number(ingredient.cost).toFixed(2) }}
                                                </TableCell>

                                                <!-- Materia Prima -->
                                                <TableCell class="py-1.5 text-center font-mono text-zinc-700 dark:text-zinc-300">
                                                    {{ Number(ingredient.gross_weight).toFixed(1) }} g
                                                </TableCell>

                                                <!-- Desecho -->
                                                <TableCell class="py-1.5 text-center font-mono text-orange-600 dark:text-orange-400">
                                                    {{ Number(ingredient.solid_waste).toFixed(1) }} g
                                                </TableCell>

                                                <!-- Producto Final -->
                                                <TableCell class="py-1.5 text-center font-mono font-extrabold text-indigo-600 dark:text-indigo-400">
                                                    {{ Number(ingredient.final_product).toFixed(1) }} g
                                                </TableCell>

                                                <!-- Calorías -->
                                                <TableCell class="py-1.5 text-center font-mono text-rose-600 dark:text-rose-400">
                                                    {{ Number(ingredient.calories).toFixed(1) }} kcal
                                                </TableCell>

                                                <!-- Calculadora Popover -->
                                                <TableCell class="py-1.5 text-center">
                                                    <div class="flex items-center justify-center gap-0.5">
                                                        <CalcPopover
                                                            :ingredient="ingredient"
                                                            :totalMateriaPrima="form.recipes[activeLevelTab].total_gross_weight"
                                                            :totalWasteWeight="form.recipes[activeLevelTab].total_waste_weight"
                                                            :totalCalories="form.recipes[activeLevelTab].total_calories"
                                                            :totalCost="form.recipes[activeLevelTab].total_cost"
                                                            :totalfinalProduct="form.recipes[activeLevelTab].total_net_weight"
                                                            @calcMassiveProperties="calcMassiveProperties"
                                                        />
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            @click="openRecipeDosificationModal(ingredient)"
                                                            class="h-8 w-8 rounded-full text-zinc-500 transition-colors hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-950/50"
                                                            title="Valores Nutricionales"
                                                        >
                                                            <FlaskConical class="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>

                                                <!-- Acciones -->
                                                <TableCell class="py-1.5 text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        @click="removeIngredientFromForm(ingredient.id)"
                                                        class="h-6 w-6 text-zinc-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50"
                                                    >
                                                        <Trash class="h-3 w-3" />
                                                    </Button>
                                                </TableCell>
                                            </TableRow>

                                            <TableRow v-if="form.recipes[activeLevelTab].ingredients.length === 0">
                                                <TableCell colspan="12" class="h-36 text-center">
                                                    <div class="flex flex-col items-center justify-center gap-1.5 text-xs text-zinc-400">
                                                        <div class="rounded-full bg-indigo-50 p-2.5 text-indigo-500 dark:bg-indigo-950/60">
                                                            <Plus class="h-4 w-4" />
                                                        </div>
                                                        <span class="font-medium text-zinc-600 dark:text-zinc-300">Busque e ingrese ingredientes para configurar la receta.</span>
                                                        <Button
                                                            v-if="form.mesearument_unit.length > 1"
                                                            type="button"
                                                            @click="copyIngredientsFromMaster"
                                                            variant="outline"
                                                            size="sm"
                                                            class="mt-1 rounded-lg border-indigo-200 text-xs font-bold text-indigo-600 hover:bg-indigo-50 dark:border-indigo-900 dark:text-indigo-400 dark:hover:bg-indigo-950"
                                                        >
                                                            <Copy class="mr-1.5 h-3 w-3" />
                                                            Duplicar ingredientes (Master)
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </div>

                            <!-- KPI Summary Footer Bar -->
                            <div class="grid grid-cols-2 gap-2 pt-1 sm:grid-cols-3 md:grid-cols-5">
                                <!-- Peso Bruto -->
                                <div class="rounded-xl border border-zinc-200/80 bg-white p-2 text-center dark:border-zinc-800 dark:bg-zinc-900 shadow-2xs">
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-zinc-400">Peso Bruto</div>
                                    <div class="mt-0.5 font-mono text-sm font-extrabold text-zinc-900 dark:text-zinc-100">
                                        {{ Number(form.recipes[activeLevelTab].total_gross_weight).toFixed(1) }} <span class="text-[10px] font-normal text-zinc-400">g</span>
                                    </div>
                                </div>

                                <!-- Mermas Totales -->
                                <div class="rounded-xl border border-amber-200/80 bg-amber-50/50 p-2 text-center dark:border-amber-900/40 dark:bg-amber-950/30 shadow-2xs">
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Mermas Totales</div>
                                    <div class="mt-0.5 font-mono text-sm font-extrabold text-amber-700 dark:text-amber-300">
                                        {{ Number(form.recipes[activeLevelTab].total_waste_weight).toFixed(1) }} <span class="text-[10px] font-normal text-amber-600">g</span>
                                    </div>
                                </div>

                                <!-- Calorías -->
                                <div class="rounded-xl border border-rose-200/80 bg-rose-50/50 p-2 text-center dark:border-rose-900/40 dark:bg-rose-950/30 shadow-2xs">
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-rose-700 dark:text-rose-400">Calorías</div>
                                    <div class="mt-0.5 font-mono text-sm font-extrabold text-rose-700 dark:text-rose-300">
                                        {{ Number(form.recipes[activeLevelTab].total_calories).toFixed(1) }} <span class="text-[10px] font-normal text-rose-600">kcal</span>
                                    </div>
                                </div>

                                <!-- Costo Receta -->
                                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/50 p-2 text-center dark:border-emerald-900/40 dark:bg-emerald-950/30 shadow-2xs">
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Costo Receta</div>
                                    <div class="mt-0.5 font-mono text-sm font-extrabold text-emerald-700 dark:text-emerald-300">
                                        S/. {{ Number(form.recipes[activeLevelTab].total_cost).toFixed(2) }}
                                    </div>
                                </div>

                                <!-- Prod Final -->
                                <div class="rounded-xl border border-indigo-200/80 bg-indigo-50/50 p-2 text-center dark:border-indigo-900/40 dark:bg-indigo-950/30 shadow-2xs">
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-indigo-700 dark:text-indigo-400">Prod. Final</div>
                                    <div class="mt-0.5 font-mono text-sm font-extrabold text-indigo-700 dark:text-indigo-300">
                                        {{ Number(form.recipes[activeLevelTab].total_net_weight).toFixed(1) }} <span class="text-[10px] font-normal text-indigo-600">g</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State for Level Selection -->
                        <div v-else-if="form.mesearument_unit.length > 0" class="flex h-44 flex-col items-center justify-center rounded-xl border border-dashed border-zinc-200 text-zinc-400 dark:border-zinc-800">
                            <Layers class="mb-2 h-7 w-7 opacity-40 text-indigo-500" />
                            <p class="text-xs font-semibold text-zinc-600 dark:text-zinc-300">Seleccione un nivel de aplicación en la barra superior para ver su receta.</p>
                        </div>
                    </div>

                    <!-- Empty State for No Levels Selected -->
                    <div v-else class="flex h-44 flex-col items-center justify-center rounded-xl border border-dashed border-zinc-200 text-zinc-400 dark:border-zinc-800">
                        <AlertCircle class="mb-2 h-7 w-7 text-amber-500 opacity-80" />
                        <p class="text-xs font-bold text-zinc-700 dark:text-zinc-300">Sin niveles de aplicación seleccionados</p>
                        <p class="text-[11px] text-zinc-400">Seleccione o añada un nivel arriba para comenzar a armar la receta.</p>
                    </div>
                </div>
            </template>

            <!-- Blank Empty State (No dish selected) -->
            <div v-else class="flex h-full flex-col items-center justify-center p-8 text-center bg-zinc-50/50 dark:bg-zinc-950">
                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-md">
                    <ChefHat class="h-7 w-7" />
                </div>
                <h3 class="text-base font-extrabold text-zinc-900 dark:text-zinc-100">Seleccione un plato o cree uno nuevo</h3>
                <p class="mt-1 max-w-xs text-xs text-zinc-400 leading-relaxed">
                    Seleccione un plato del panel izquierdo para revisar y modificar sus ingredientes y valores nutricionales, o cree una nueva receta.
                </p>
                <Button @click="createDish" class="mt-5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 shadow-sm shadow-indigo-500/20 gap-1.5">
                    <Plus class="h-3.5 w-3.5" />
                    <span>Crear Nuevo Plato</span>
                </Button>
            </div>
        </div>

        <!-- Modal: Valores Nutricionales del ingrediente (editable desde la tabla de la receta) -->
        <Dialog v-model:open="isRecipeDosificationModalOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-emerald-100 p-2 dark:bg-emerald-950/60">
                            <FlaskConical class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div>
                            <DialogTitle class="text-lg font-bold">Valores Nutricionales</DialogTitle>
                            <DialogDescription class="text-xs">
                                Gestionando dosificación para:
                                <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ activeIngredientForDosification?.name }}</span>
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div class="space-y-4 pt-2">
                    <div class="mb-1 flex items-center gap-2">
                        <div class="h-4 w-1 rounded-full bg-emerald-500"></div>
                        <span class="text-[11px] font-bold tracking-wider text-zinc-400 uppercase">Macronutrientes</span>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 space-y-1.5">
                            <Label class="flex items-center gap-1.5 text-[11px] font-bold text-zinc-500 uppercase">
                                <Zap class="h-3 w-3" /> Energía (kcal)
                            </Label>
                            <Input
                                :model-value="recipeDosificationForm.energy ?? undefined"
                                @update:model-value="(val) => (recipeDosificationForm.energy = val ? Number(val) : null)"
                                type="number"
                                step="0.01"
                                placeholder="0.00"
                                class="h-9 border-zinc-200"
                            />
                            <div v-if="recipeDosificationErrors.energy" class="text-xs font-medium text-red-500">
                                {{ recipeDosificationErrors.energy }}
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label class="flex items-center gap-1.5 text-[11px] font-bold text-zinc-500 uppercase">
                                <Waves class="h-3 w-3" /> Agua (g)
                            </Label>
                            <Input
                                :model-value="recipeDosificationForm.water ?? undefined"
                                @update:model-value="(val) => (recipeDosificationForm.water = val ? Number(val) : null)"
                                type="number"
                                step="0.01"
                                placeholder="0.00"
                                class="h-9 border-zinc-200"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label class="flex items-center gap-1.5 text-[11px] font-bold text-zinc-500 uppercase">
                                <Beef class="h-3 w-3" /> Proteína (g)
                            </Label>
                            <Input
                                :model-value="recipeDosificationForm.protein ?? undefined"
                                @update:model-value="(val) => (recipeDosificationForm.protein = val ? Number(val) : null)"
                                type="number"
                                step="0.01"
                                placeholder="0.00"
                                class="h-9 border-zinc-200"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label class="flex items-center gap-1.5 text-[11px] font-bold text-zinc-500 uppercase">
                                <Droplets class="h-3 w-3" /> Lípidos (g)
                            </Label>
                            <Input
                                :model-value="recipeDosificationForm.lipid ?? undefined"
                                @update:model-value="(val) => (recipeDosificationForm.lipid = val ? Number(val) : null)"
                                type="number"
                                step="0.01"
                                placeholder="0.00"
                                class="h-9 border-zinc-200"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label class="flex items-center gap-1.5 text-[11px] font-bold text-zinc-500 uppercase">
                                <Cookie class="h-3 w-3" /> Carbohidratos Totales (g)
                            </Label>
                            <Input
                                :model-value="recipeDosificationForm.carbohydrate ?? undefined"
                                @update:model-value="(val) => (recipeDosificationForm.carbohydrate = val ? Number(val) : null)"
                                type="number"
                                step="0.01"
                                placeholder="0.00"
                                class="h-9 border-zinc-200"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label class="flex items-center gap-1.5 text-[11px] font-bold text-zinc-500 uppercase">
                                <Cookie class="h-3 w-3" /> Carbohidratos Disponibles (g)
                            </Label>
                            <Input
                                :model-value="recipeDosificationForm.carbohydrate_available ?? undefined"
                                @update:model-value="(val) => (recipeDosificationForm.carbohydrate_available = val ? Number(val) : null)"
                                type="number"
                                step="0.01"
                                placeholder="0.00"
                                class="h-9 border-zinc-200"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label class="flex items-center gap-1.5 text-[11px] font-bold text-zinc-500 uppercase">
                                <Wheat class="h-3 w-3" /> Fibra (g)
                            </Label>
                            <Input
                                :model-value="recipeDosificationForm.fiber ?? undefined"
                                @update:model-value="(val) => (recipeDosificationForm.fiber = val ? Number(val) : null)"
                                type="number"
                                step="0.01"
                                placeholder="0.00"
                                class="h-9 border-zinc-200"
                            />
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2 pt-4">
                    <Button type="button" variant="ghost" @click="isRecipeDosificationModalOpen = false" class="font-bold text-zinc-500">
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        :disabled="isSavingRecipeDosification"
                        @click="submitRecipeDosification"
                        class="bg-emerald-600 font-bold text-white hover:bg-emerald-700"
                    >
                        {{ isSavingRecipeDosification ? 'Guardando...' : 'Guardar Nutrientes' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
