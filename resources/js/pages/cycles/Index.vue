<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import CalcPopover from '@/pages/food/CalcPopover.vue';
import MenuDisplay from '@/pages/structure-menu/MenuDisplay.vue';
import { Mine } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    Calculator,
    CalendarDays,
    ChevronDown,
    Download,
    FolderOpen,
    LineChart,
    Loader2,
    Plus,
    Save,
    Search,
    Settings2,
    X,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, ref, watch } from 'vue';

interface Props {
    mines?: Mine[];
    structures?: any[];
    savedCycles?: any[];
    dishCategories?: any[];
    levels?: any[];
}

const props = defineProps<Props>();

const selectedServiceableId = ref<string | null>(null);
const activeCycleId = ref<number | null>(null);
const activeCycleName = ref<string>('');
const isServiceCyclesModalOpen = ref(false);
const serviceCyclesToSelect = ref<any[]>([]);
const isStructureSelectModalOpen = ref(false);
const structuresToSelect = ref<any[]>([]);

// Table configuration state
const inputDays = ref<number>(7);
const generatedDays = ref<number>(7);

const generateColumns = () => {
    if (inputDays.value > 0 && inputDays.value <= 31) {
        generatedDays.value = inputDays.value;
    }
};

const daysColumns = computed(() => {
    return Array.from({ length: generatedDays.value }, (_, i) => `Día ${i + 1}`);
});

const isSavedCyclesModalOpen = ref(false);
const repeatedDishes = ref<{ rowId: any; dayIndex: number }[]>([]);

const getServiceName = (serviceableId: any) => {
    if (!props.mines) return 'Servicio Desconocido';
    for (const mine of props.mines) {
        if (!mine.units) continue;
        for (const unit of mine.units) {
            if (!unit.cafes) continue;
            for (const cafe of unit.cafes) {
                if (!cafe.services) continue;
                for (const service of cafe.services) {
                    if (String(service.pivot?.id) === String(serviceableId)) {
                        return `${mine.name} - ${unit.name} - ${cafe.name} - ${service.name}`;
                    }
                }
            }
        }
    }
    return `Servicio ID: ${serviceableId}`;
};

const isRepeated = (rowId: any, dayIndex: number) => {
    return repeatedDishes.value.some((r) => r.rowId === rowId && r.dayIndex === dayIndex);
};

const copyCycle = (cycle: any) => {
    activeCycleId.value = cycle.id;
    activeCycleName.value = cycle.name || '';
    inputDays.value = cycle.days;
    generatedDays.value = cycle.days;
    menuStructureData.value = JSON.parse(JSON.stringify(cycle.cycle_data));
    repeatedDishes.value = [];
    isSavedCyclesModalOpen.value = false;
    isServiceCyclesModalOpen.value = false;
    Swal.fire({
        icon: 'success',
        title: 'Ciclo copiado',
        text: 'La estructura se ha cargado tal como se guardó.',
        timer: 1500,
        showConfirmButton: false,
    });
};

const compareCycle = (cycle: any) => {
    repeatedDishes.value = [];
    let matchCount = 0;
    cycle.cycle_data.forEach((compareRow: any) => {
        const currentRow = menuStructureData.value.find((r: any) => r.id === compareRow.id);
        if (currentRow) {
            Object.keys(currentRow.days).forEach((dayKey) => {
                const currentDay = currentRow.days[dayKey];
                const compareDay = compareRow.days[dayKey];
                if (currentDay && compareDay && currentDay.dish_id === compareDay.dish_id) {
                    repeatedDishes.value.push({ rowId: currentRow.id, dayIndex: parseInt(dayKey) });
                    matchCount++;
                }
            });
        }
    });

    if (matchCount > 0) {
        Swal.fire('Comparación Finalizada', `Se encontraron ${matchCount} platos repetidos en las mismas posiciones.`, 'warning');
    } else {
        Swal.fire('Comparación Finalizada', 'No se encontraron platos repetidos respecto a este ciclo.', 'success');
    }
    isSavedCyclesModalOpen.value = false;
};

// Dynamic menu structure data
const menuStructureData = ref<any[]>([]);

// Once a single structure is settled on for this service, load its matching cycle (if there's exactly
// one) or fall back to blank defaults from the structure's costs.
const applyStructure = (structure: any, newId: string) => {
    const serviceCycles = props.savedCycles?.filter((c) => String(c.serviceable_id) === String(newId)) || [];

    if (serviceCycles.length === 1) {
        const savedCycle = serviceCycles[0];
        activeCycleId.value = savedCycle.id;
        activeCycleName.value = savedCycle.name || '';
        inputDays.value = savedCycle.days;
        generatedDays.value = savedCycle.days;

        // Rebuild rows from the CURRENT structure's categories (source of truth), carrying over day
        // assignments from the saved cycle only for categories that still exist in the structure.
        // A cycle's cycle_data is a frozen snapshot from whenever it was saved, so if the structure
        // was edited since (categories added/removed), it must not dictate which rows are shown.
        menuStructureData.value = (structure.costs || []).map((cost: any) => {
            const savedRow = savedCycle.cycle_data.find((row: any) => row.dishCategoryId === cost.dish_category_id);
            return {
                id: cost.id,
                category: cost.name || 'Categoría',
                dishCategoryId: cost.dish_category_id,
                costValue: parseFloat(cost.total_cost || 0),
                costValueMax: parseFloat(cost.total_cost_superior || 0),
                days: savedRow?.days || {},
            };
        });
        return;
    }

    activeCycleId.value = null;
    activeCycleName.value = '';

    if (serviceCycles.length > 1) {
        serviceCyclesToSelect.value = serviceCycles;
        isServiceCyclesModalOpen.value = true;
    }

    // Populate the table with the structure costs defaults
    menuStructureData.value = (structure.costs || []).map((cost: any) => {
        return {
            id: cost.id,
            category: cost.name || 'Categoría',
            dishCategoryId: cost.dish_category_id,
            costValue: parseFloat(cost.total_cost || 0),
            costValueMax: parseFloat(cost.total_cost_superior || 0),
            days: {}, // Will hold { dayIndex: { dish: 'Name', calories: 100, price: 5.5 } }
        };
    });
};

const selectStructure = (structure: any) => {
    isStructureSelectModalOpen.value = false;
    structuresToSelect.value = [];
    if (selectedServiceableId.value) {
        applyStructure(structure, selectedServiceableId.value);
    }
};

watch(selectedServiceableId, (newId) => {
    repeatedDishes.value = [];
    isStructureSelectModalOpen.value = false;
    structuresToSelect.value = [];

    if (!newId) {
        menuStructureData.value = [];
        return;
    }

    // Never auto-pick a structure when several are saved for the same service: let the user choose.
    const matchingStructures = props.structures?.filter((s) => String(s.serviceable_id) === String(newId)) || [];

    if (matchingStructures.length === 0) {
        menuStructureData.value = [];
        Swal.fire({
            icon: 'warning',
            title: 'Sin estructura de menú',
            text: 'Este servicio aún no tiene una estructura asignada. Por favor configúrela primero.',
            confirmButtonColor: '#FF5A1F',
        });
        return;
    }

    if (matchingStructures.length > 1) {
        menuStructureData.value = [];
        structuresToSelect.value = matchingStructures;
        isStructureSelectModalOpen.value = true;
        return;
    }

    applyStructure(matchingStructures[0], newId);
});

// Search Dialog State
const isSearchModalOpen = ref(false);
const searchQuery = ref('');
const searchCategory = ref('');
const searchLevel = ref('');
const searchResults = ref<any[]>([]);
const currentSearchTarget = ref<{ rowIndex: number; dayIndex: number; categoryId: any }>({ rowIndex: -1, dayIndex: -1, categoryId: null });

const openSearchModal = (rowIndex: number, dayIndex: number, categoryId: any) => {
    currentSearchTarget.value = { rowIndex, dayIndex, categoryId };
    searchQuery.value = '';
    searchCategory.value = categoryId || '';
    searchLevel.value = '';
    searchResults.value = [];
    isSearchModalOpen.value = true;
    searchDish();
};

const searchDish = async () => {
    if (!searchQuery.value && !searchCategory.value && !searchLevel.value) {
        searchResults.value = [];
        return;
    }
    try {
        const queryPath = searchQuery.value ? `/${encodeURIComponent(searchQuery.value)}` : '';
        const response = await axios.get(`/dishes/search${queryPath}`, {
            params: {
                category_id: searchCategory.value || null,
                level_id: searchLevel.value || null,
            },
        });
        searchResults.value = response.data;
    } catch (err) {
        console.error('Error searching dish', err);
    }
};

const assignDish = (dish: any, recipe: any, action: 'single' | 'all') => {
    const { rowIndex, dayIndex } = currentSearchTarget.value;
    if (rowIndex >= 0) {
        const dishData = {
            dish_id: dish.id,
            dish_name: dish.name,
            level_id: recipe.level_id,
            calories: recipe.total_calories || '0',
            price: recipe.total_cost || '0.00',
        };

        if (action === 'all') {
            for (let i = 1; i <= generatedDays.value; i++) {
                menuStructureData.value[rowIndex].days[i] = { ...dishData };
            }
        } else if (dayIndex > 0) {
            menuStructureData.value[rowIndex].days[dayIndex] = { ...dishData };
        }
    }
    isSearchModalOpen.value = false;
};

const clearDayDish = async (rowIndex: number, dayIndex: number) => {
    const result = await Swal.fire({
        title: '¿Quitar plato asignado?',
        text: 'La casilla quedará vacía y podrá asignar otro plato.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#FF5A1F',
        confirmButtonText: 'Sí, quitar',
        cancelButtonText: 'Cancelar',
    });

    if (!result.isConfirmed) return;

    delete menuStructureData.value[rowIndex].days[dayIndex];
};

// Helper for semaphore colors
const getSemaphoreColor = (status: string) => {
    switch (status) {
        case 'gravisimo':
            return 'bg-red-500';
        case 'pesimo':
            return 'bg-yellow-400';
        case 'bueno':
            return 'bg-green-500';
        default:
            return 'bg-gray-300';
    }
};

const getSemaphoreText = (status: string) => {
    switch (status) {
        case 'gravisimo':
            return 'Costos Muy Altos';
        case 'pesimo':
            return 'Costos Muy Bajos';
        case 'bueno':
            return 'Costos Óptimos';
        default:
            return 'Sin Asignar';
    }
};

const getSemaphoreCellClass = (status: string) => {
    switch (status) {
        case 'gravisimo':
            return 'bg-red-50/80 group-hover/row:bg-red-100/70';
        case 'pesimo':
            return 'bg-yellow-50/80 group-hover/row:bg-yellow-100/60';
        case 'bueno':
            return 'bg-green-50/70 group-hover/row:bg-green-100/50';
        default:
            return 'bg-slate-50/40 group-hover/row:bg-slate-100/50';
    }
};

const getSemaphoreTextClass = (status: string) => {
    switch (status) {
        case 'gravisimo':
            return 'text-red-700';
        case 'pesimo':
            return 'text-yellow-700';
        case 'bueno':
            return 'text-green-700';
        default:
            return 'text-slate-400';
    }
};

const getRowStatus = (row: any) => {
    const days = Object.values(row.days || {}) as any[];
    if (days.length === 0) return 'desconocido';

    let totalAssignedCost = 0;
    days.forEach((day) => {
        totalAssignedCost += parseFloat(day.price || 0);
    });

    const averageCost = totalAssignedCost / days.length;
    const minLimit = parseFloat(row.costValue || 0);
    const maxLimit = parseFloat(row.costValueMax || 0);

    if (averageCost < minLimit) {
        return 'pesimo';
    } else if (averageCost > maxLimit) {
        return 'gravisimo';
    } else {
        return 'bueno';
    }
};

// Cost-vs-limits range indicator (opens from a row's aggregate semaphore, or from a single day cell)
const isChartModalOpen = ref(false);
const chartRow = ref<any>(null);
const chartDayIndex = ref<number | null>(null);

const openRowChartModal = (row: any) => {
    if (Object.keys(row.days || {}).length === 0) return;
    chartRow.value = row;
    chartDayIndex.value = null;
    isChartModalOpen.value = true;
};

const openDayChartModal = (row: any, dayIndex: number) => {
    if (!row.days[dayIndex]) return;
    chartRow.value = row;
    chartDayIndex.value = dayIndex;
    isChartModalOpen.value = true;
};

// Per-day status uses the same thresholds as the row's aggregate semaphore (getRowStatus),
// but compares a single day's price instead of the average across all assigned days.
const getDayStatus = (price: number, min: number, max: number): 'pesimo' | 'gravisimo' | 'bueno' => {
    if (price < min) return 'pesimo';
    if (price > max) return 'gravisimo';
    return 'bueno';
};

// Reuses the same cost-vs-limits logic as getRowStatus/getDayStatus, but also returns
// the raw numbers and their position (0-100%) along a padded scale, so the modal
// can place the value marker between the min/max ticks without ever pinning it
// flush to the track edge. Shows the average across days, or a single day's price
// when opened from a day cell (chartDayIndex set).
const chartStats = computed(() => {
    if (!chartRow.value) return null;

    const min = parseFloat(chartRow.value.costValue || 0);
    const max = parseFloat(chartRow.value.costValueMax || 0);

    let value: number;
    if (chartDayIndex.value !== null) {
        const day = chartRow.value.days[chartDayIndex.value];
        value = day ? parseFloat(day.price || 0) : 0;
    } else {
        const days = Object.values(chartRow.value.days || {}) as any[];
        const total = days.reduce((sum: number, d: any) => sum + parseFloat(d.price || 0), 0);
        value = days.length ? total / days.length : 0;
    }

    const status: 'pesimo' | 'gravisimo' | 'bueno' = value < min ? 'pesimo' : value > max ? 'gravisimo' : 'bueno';

    const span = Math.max(max - min, 0.01);
    const padding = Math.max(span * 0.5, 0.3);
    const domainMin = Math.min(min, value) - padding;
    const domainMax = Math.max(max, value) + padding;
    const domainSpan = domainMax - domainMin;
    const toPct = (v: number) => ((v - domainMin) / domainSpan) * 100;

    return {
        value,
        min,
        max,
        status,
        minPct: toPct(min),
        maxPct: toPct(max),
        valuePct: toPct(value),
    };
});

// Quebrado (recipe breakdown) quick editor — opens from a day cell's calculator button.
// Edits here save straight to the dish's master DishRecipe (so the Alimentos module and any
// future assignment picks up the change), but never touch cycle_data already persisted on
// other saved cycles, since that data is a frozen snapshot taken at save time. The current,
// still-unsaved cycle being edited IS updated live so what's on screen matches what was saved.
const isQuebradoModalOpen = ref(false);
const isQuebradoLoading = ref(false);
const isQuebradoSaving = ref(false);
const quebradoDishName = ref('');
const quebradoRecipeId = ref<number | null>(null);
const quebradoRecipe = ref<{
    total_gross_weight: number;
    total_waste_weight: number;
    total_calories: number;
    total_cost: number;
    total_net_weight: number;
    ingredients: any[];
} | null>(null);
const quebradoRow = ref<any>(null);
const quebradoDayIndex = ref<number | null>(null);

// Same formula as food/Quebrados.vue's calculateIngredientCalories/toGramsPer100g, duplicated
// here since this editor lives on a different page and only needs this one calculation.
const toGramsPer100g = (rawValue: unknown): number => {
    const value = parseFloat(rawValue as string) || 0;
    return value > 100 ? value / 10000 : value;
};

const calculateIngredientCalories = (ingredient: any) => {
    const dosification = ingredient?.dosification;
    const atwaterFactor = ingredient?.atwater_factor || ingredient?.atwaterFactor;

    if (dosification && atwaterFactor) {
        const protein = toGramsPer100g(dosification.protein);
        const lipid = toGramsPer100g(dosification.lipid);
        const carbohydrate =
            dosification.carbohydrate_available !== null && dosification.carbohydrate_available !== undefined
                ? toGramsPer100g(dosification.carbohydrate_available)
                : toGramsPer100g(dosification.carbohydrate);

        const proteinFactor = parseFloat(atwaterFactor.protein_kcal) || 0;
        const fatFactor = parseFloat(atwaterFactor.fat_kcal) || 0;
        const carbFactor = parseFloat(atwaterFactor.carb_kcal) || 0;

        return protein * proteinFactor + lipid * fatFactor + carbohydrate * carbFactor;
    }

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

const openQuebradoModal = async (row: any, dayIndex: number) => {
    const day = row.days[dayIndex];
    if (!day) return;

    quebradoRow.value = row;
    quebradoDayIndex.value = dayIndex;
    quebradoDishName.value = day.dish_name;
    quebradoRecipeId.value = null;
    quebradoRecipe.value = null;
    isQuebradoModalOpen.value = true;
    isQuebradoLoading.value = true;

    try {
        const response = await axios.get(route('dish-recipes.lookup'), {
            params: { dish_id: day.dish_id, level_id: day.level_id },
        });
        const recipe = response.data;
        quebradoRecipeId.value = recipe.id;
        quebradoRecipe.value = {
            total_gross_weight: parseFloat(recipe.total_gross_weight) || 0,
            total_waste_weight: parseFloat(recipe.total_waste_weight) || 0,
            total_calories: parseFloat(recipe.total_calories) || 0,
            total_cost: parseFloat(recipe.total_cost) || 0,
            total_net_weight: parseFloat(recipe.total_net_weight) || 0,
            ingredients: (recipe.ingredients || []).map((ing: any) => {
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
                        waste: ing.waste || 0,
                        calories: calculateIngredientCalories(ing),
                    },
                };
                newIng.calories = (newIng.gross_weight * newIng.originalValues.calories) / 100;
                return newIng;
            }),
        };
    } catch (err: any) {
        isQuebradoModalOpen.value = false;
        Swal.fire('Atención', err?.response?.data?.message || 'No se pudo cargar el quebrado de este plato.', 'warning');
    } finally {
        isQuebradoLoading.value = false;
    }
};

const recalculateQuebradoTotals = () => {
    if (!quebradoRecipe.value) return;
    const recipe = quebradoRecipe.value;
    recipe.total_gross_weight = recipe.ingredients.reduce((sum, i) => sum + (parseFloat(i.gross_weight) || 0), 0);
    recipe.total_waste_weight = recipe.ingredients.reduce((sum, i) => sum + (parseFloat(i.solid_waste) || 0), 0);
    recipe.total_calories = recipe.ingredients.reduce((sum, i) => sum + (parseFloat(i.calories) || 0), 0);
    recipe.total_cost = recipe.ingredients.reduce((sum, i) => sum + (parseFloat(i.cost) || 0), 0);
    recipe.total_net_weight = recipe.ingredients.reduce((sum, i) => sum + (parseFloat(i.final_product) || 0), 0);
};

const onQuebradoWeightInput = (ingredient: any) => {
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

    recalculateQuebradoTotals();
};

const calcQuebradoMassiveProperties = (id: number, calcArray: number[]) => {
    if (!quebradoRecipe.value) return;
    const ingredientIndex = quebradoRecipe.value.ingredients.findIndex((ing) => ing.id == id);
    if (ingredientIndex !== -1) {
        const ing = quebradoRecipe.value.ingredients[ingredientIndex];
        ing.gross_weight = calcArray[0];
        ing.solid_waste = calcArray[1];
        ing.calories = calcArray[2];
        ing.cost = calcArray[3];
        ing.final_product = calcArray[4];
        ing.unit_price = calcArray[5];
        recalculateQuebradoTotals();
    }
};

const saveQuebrado = async () => {
    if (!quebradoRecipeId.value || !quebradoRecipe.value) return;

    isQuebradoSaving.value = true;
    try {
        const recipe = quebradoRecipe.value;
        await axios.put(route('dish-recipes.update', quebradoRecipeId.value), {
            total_gross_weight: recipe.total_gross_weight,
            total_waste_weight: recipe.total_waste_weight,
            total_calories: recipe.total_calories,
            total_cost: recipe.total_cost,
            total_net_weight: recipe.total_net_weight,
            ingredients: recipe.ingredients.map((ing) => ({
                id: ing.id,
                gross_weight: ing.gross_weight,
                solid_waste: ing.solid_waste,
                liquid_waste: ing.liquid_waste,
                calories: ing.calories,
                cost: ing.cost,
                unit_price: ing.unit_price,
                final_product: ing.final_product,
            })),
        });

        // The cycle currently being edited hasn't been saved yet (or is being re-edited before
        // the next save), so reflect the new totals immediately in the day cell on screen.
        if (quebradoRow.value && quebradoDayIndex.value !== null) {
            const day = quebradoRow.value.days[quebradoDayIndex.value];
            if (day) {
                day.calories = recipe.total_calories;
                day.price = recipe.total_cost;
            }
        }

        isQuebradoModalOpen.value = false;
        Swal.fire({
            icon: 'success',
            title: 'Quebrado actualizado',
            text: 'Los cambios se guardaron en la receta del plato.',
            timer: 1500,
            showConfirmButton: false,
        });
    } catch (err: any) {
        Swal.fire('Error', err?.response?.data?.message || 'No se pudo guardar el quebrado.', 'error');
    } finally {
        isQuebradoSaving.value = false;
    }
};

const saveCycle = async () => {
    if (!selectedServiceableId.value) {
        Swal.fire('Atención', 'Seleccione un servicio primero', 'warning');
        return;
    }

    if (menuStructureData.value.length === 0) {
        Swal.fire('Atención', 'No hay estructura de menú para guardar', 'warning');
        return;
    }

    const { value: formValues } = await Swal.fire({
        title: 'Guardar Ciclo',
        html:
            `<input id="swal-name" class="swal2-input" placeholder="Nombre del Ciclo" value="${activeCycleName.value || ''}">` +
            (activeCycleId.value
                ? `<div class="mt-4 flex items-center justify-center gap-2">
                    <input type="checkbox" id="swal-as-new" class="w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500">
                    <label for="swal-as-new" class="text-sm text-gray-700">Guardar como nuevo ciclo (Copia)</label>
                </div>`
                : ''),
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#FF5A1F',
        preConfirm: () => {
            const name = (document.getElementById('swal-name') as HTMLInputElement).value;
            const asNew = activeCycleId.value ? (document.getElementById('swal-as-new') as HTMLInputElement).checked : true;
            if (!name) {
                Swal.showValidationMessage('El nombre es obligatorio');
                return false;
            }
            return { name, asNew };
        },
    });

    if (formValues) {
        const { name, asNew } = formValues;

        router.post(
            '/cycles',
            {
                id: asNew ? null : activeCycleId.value,
                serviceable_id: selectedServiceableId.value,
                name: name,
                days: generatedDays.value,
                cycle_data: menuStructureData.value,
            },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    activeCycleName.value = name;
                    // If it was saved as new, we should update the activeCycleId with the new one
                    // This might require the backend to return the new ID or we find it in props
                    Swal.fire({
                        icon: 'success',
                        title: '¡Guardado!',
                        text: 'El ciclo de menú se ha guardado correctamente.',
                        timer: 2000,
                        showConfirmButton: false,
                    });
                },
                onError: (errors) => {
                    console.error(errors);
                    Swal.fire('Error', 'Hubo un problema al guardar el ciclo', 'error');
                },
            },
        );
    }
};

const exportCycle = () => {
    if (!activeCycleId.value) {
        Swal.fire('Atención', 'Primero guarde o cargue un ciclo para exportar.', 'warning');
        return;
    }
    window.location.href = `/cycles/export/${activeCycleId.value}`;
};

const exportCycleWithoutKcal = () => {
    if (!activeCycleId.value) {
        Swal.fire('Atención', 'Primero guarde o cargue un ciclo para exportar.', 'warning');
        return;
    }
    window.location.href = `/cycles/export/${activeCycleId.value}?hide_kcal=true`;
};

const resetToNew = () => {
    if (!selectedServiceableId.value) {
        Swal.fire('Atención', 'Seleccione un servicio primero', 'warning');
        return;
    }
    const structure = props.structures?.find((s) => String(s.serviceable_id) === String(selectedServiceableId.value));
    if (!structure) return;

    activeCycleId.value = null;
    activeCycleName.value = '';
    menuStructureData.value = (structure.costs || []).map((cost: any) => ({
        id: cost.id,
        category: cost.name || 'Categoría',
        dishCategoryId: cost.dish_category_id,
        costValue: parseFloat(cost.total_cost || 0),
        costValueMax: parseFloat(cost.total_cost_superior || 0),
        days: {},
    }));

    Swal.fire({
        icon: 'success',
        title: 'Nuevo Ciclo',
        text: 'Se ha preparado una estructura vacía para un nuevo ciclo.',
        timer: 1500,
        showConfirmButton: false,
    });
};
</script>

<template>
    <Head title="Ciclos de Menú" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 overflow-hidden bg-slate-50/50 p-6">
            <div class="flex flex-none items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Configuración de Ciclos</h1>
                    <p class="mt-1 text-sm text-slate-500">Gestione la programación de platos por día para cada servicio.</p>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" class="flex items-center gap-2 border-slate-200 bg-white text-slate-600 hover:bg-slate-50" @click="resetToNew">
                        <Plus class="h-4 w-4" />
                        Nuevo Ciclo
                    </Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" class="flex items-center gap-2 border-slate-200 bg-white text-slate-600 hover:bg-slate-50">
                                <Download class="h-4 w-4" />
                                Exportar
                                <ChevronDown class="h-3.5 w-3.5 text-slate-400" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <DropdownMenuItem class="cursor-pointer" @select="exportCycle">Con calorías</DropdownMenuItem>
                            <DropdownMenuItem class="cursor-pointer" @select="exportCycleWithoutKcal">Sin calorías</DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <Button variant="outline" class="flex items-center gap-2 border-slate-200 bg-white text-slate-600 hover:bg-slate-50" @click="isSavedCyclesModalOpen = true">
                        <Settings2 class="h-4 w-4" />
                        Ajustes
                    </Button>
                    <Button
                        @click="saveCycle"
                        class="flex items-center gap-2 bg-[#FF5A1F] text-white shadow-sm shadow-orange-500/20 hover:bg-[#e04a17]"
                    >
                        <Save class="h-4 w-4" />
                        Guardar Ciclo
                    </Button>
                </div>
            </div>

            <div class="flex-none">
                <MenuDisplay :mines="mines" @update:serviceable="selectedServiceableId = $event" />
            </div>

            <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-slate-100 bg-white shadow-sm">
                <div class="flex flex-none flex-wrap items-center justify-between gap-4 border-b border-slate-100 bg-white p-5">
                    <div class="flex items-center gap-6">
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-medium whitespace-nowrap text-slate-700">Número de Días:</span>
                            <div class="relative flex items-center">
                                <Input
                                    type="number"
                                    v-model="inputDays"
                                    min="1"
                                    max="31"
                                    class="h-9 w-24 border-slate-200 focus-visible:ring-[#FF5A1F]"
                                />
                            </div>
                            <Button @click="generateColumns" size="sm" variant="secondary" class="h-9 bg-slate-100 text-slate-700 hover:bg-slate-200">
                                <CalendarDays class="mr-2 h-4 w-4" />
                                Generar Columnas
                            </Button>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 text-xs font-medium text-slate-500">
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-green-500"></span>Óptimo</span>
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-yellow-400"></span>Muy bajo</span>
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-red-500"></span>Muy alto</span>
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-gray-300"></span>Sin asignar</span>
                    </div>
                </div>

                <div class="scrollbar-thin scrollbar-thumb-slate-200 scrollbar-track-transparent relative isolate w-full flex-1 overflow-auto pb-4">
                    <Table class="w-full border-collapse">
                        <TableHeader>
                            <TableRow class="border-b-slate-200 bg-slate-50/80">
                                <TableHead
                                    class="sticky top-0 left-0 z-20 w-[60px] bg-slate-50/95 text-center font-semibold text-slate-600 shadow-[1px_1px_0_0_#e2e8f0] backdrop-blur"
                                    >Ord</TableHead
                                >
                                <TableHead
                                    class="sticky top-0 left-[60px] z-20 min-w-[220px] bg-slate-50/95 font-semibold text-slate-600 shadow-[1px_1px_0_0_#e2e8f0] backdrop-blur"
                                    >Estructura del Menú</TableHead
                                >
                                <TableHead
                                    class="sticky top-0 left-[280px] z-20 w-[120px] bg-slate-50/95 text-center font-semibold text-slate-600 shadow-[1px_1px_0_0_#e2e8f0] backdrop-blur"
                                    >Semáforo</TableHead
                                >
                                <TableHead
                                    v-for="(day, index) in daysColumns"
                                    :key="index"
                                    class="sticky top-0 z-10 min-w-[180px] border-l border-slate-200 bg-slate-50/95 text-center font-semibold text-slate-600 shadow-[0_1px_0_0_#e2e8f0] backdrop-blur"
                                >
                                    {{ day }}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="(row, rowIndex) in menuStructureData"
                                :key="row.id"
                                class="group/row transition-colors hover:bg-slate-50/50"
                            >
                                <TableCell
                                    class="sticky left-0 z-10 border-r border-slate-100 bg-white text-center font-medium text-slate-500 shadow-[1px_0_0_0_#f1f5f9] backdrop-blur group-hover/row:bg-slate-50/95"
                                >
                                    {{ rowIndex + 1 }}
                                </TableCell>

                                <TableCell
                                    class="sticky left-[60px] z-10 border-r border-slate-100 bg-white shadow-[1px_0_0_0_#f1f5f9] backdrop-blur group-hover/row:bg-slate-50/95"
                                >
                                    <div class="flex flex-col gap-1.5 py-1">
                                        <Badge variant="outline" class="w-max border-slate-200 bg-slate-50 font-semibold text-slate-600">
                                            S/ {{ row.costValue.toFixed(2) }}
                                        </Badge>
                                        <span class="text-[13px] font-bold tracking-tight text-slate-800">{{ row.category }}</span>
                                    </div>
                                </TableCell>

                                <TableCell
                                    class="sticky left-[280px] z-10 border-r border-slate-100 text-center shadow-[1px_0_0_0_#f1f5f9] backdrop-blur transition-colors"
                                    :class="[
                                        getSemaphoreCellClass(getRowStatus(row)),
                                        Object.keys(row.days || {}).length > 0 ? 'cursor-pointer' : '',
                                    ]"
                                    @click="openRowChartModal(row)"
                                >
                                    <div class="group/semaphore flex flex-col items-center justify-center gap-1 py-2">
                                        <div
                                            class="h-3 w-3 rounded-full shadow-sm ring-2 ring-white"
                                            :class="getSemaphoreColor(getRowStatus(row))"
                                        ></div>
                                        <span
                                            class="text-[10px] leading-tight font-bold"
                                            :class="getSemaphoreTextClass(getRowStatus(row))"
                                            >{{ getSemaphoreText(getRowStatus(row)) }}</span
                                        >
                                        <span
                                            v-if="Object.keys(row.days || {}).length > 0"
                                            class="flex items-center gap-1 text-[9px] font-medium text-slate-400 opacity-0 transition-opacity group-hover/semaphore:opacity-100"
                                        >
                                            <LineChart class="h-2.5 w-2.5" />
                                            Ver gráfico
                                        </span>
                                    </div>
                                </TableCell>

                                <!-- Days Columns -->
                                <TableCell
                                    v-for="dayIndex in generatedDays"
                                    :key="dayIndex"
                                    class="group/cell relative border-l border-slate-100 p-2 align-top"
                                >
                                    <div
                                        class="flex h-full flex-col overflow-hidden border bg-white transition-all"
                                        :class="{
                                            'rounded-md border-transparent shadow-sm group-hover/cell:border-[#FF5A1F]': !isRepeated(
                                                row.id,
                                                dayIndex,
                                            ),
                                            'rounded-md border-red-400 bg-red-50/80 shadow-md ring-2 ring-red-200': isRepeated(row.id, dayIndex),
                                        }"
                                    >
                                        <div
                                            v-if="!row.days[dayIndex]"
                                            class="group/empty flex h-full min-h-[80px] w-full cursor-pointer flex-col items-center justify-center gap-1 rounded-md border border-dashed border-slate-200 transition-colors hover:border-[#FF5A1F] hover:bg-orange-50/40"
                                            @click="openSearchModal(rowIndex, dayIndex, row.dishCategoryId)"
                                        >
                                            <Plus class="h-4 w-4 text-slate-300 transition-colors group-hover/empty:text-[#FF5A1F]" />
                                            <span class="text-[11px] font-medium text-slate-400 transition-colors group-hover/empty:text-[#FF5A1F]"
                                                >Agregar plato</span
                                            >
                                        </div>

                                        <template v-else>
                                            <div class="cursor-pointer p-3" @click="openSearchModal(rowIndex, dayIndex, row.dishCategoryId)">
                                                <div class="mb-2 flex items-center justify-between gap-1">
                                                    <div class="flex min-w-0 items-center gap-1.5">
                                                        <button
                                                            type="button"
                                                            class="h-2.5 w-2.5 shrink-0 rounded-full ring-1 ring-white transition-transform hover:scale-125"
                                                            :class="
                                                                getSemaphoreColor(
                                                                    getDayStatus(Number(row.days[dayIndex].price), row.costValue, row.costValueMax),
                                                                )
                                                            "
                                                            :title="
                                                                getSemaphoreText(
                                                                    getDayStatus(Number(row.days[dayIndex].price), row.costValue, row.costValueMax),
                                                                )
                                                            "
                                                            @click.stop="openDayChartModal(row, dayIndex)"
                                                        ></button>
                                                        <div
                                                            v-if="isRepeated(row.id, dayIndex)"
                                                            class="flex items-center gap-1 truncate rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-bold text-red-600"
                                                        >
                                                            <AlertTriangle class="h-3 w-3 shrink-0" />
                                                            Repetido
                                                        </div>
                                                    </div>
                                                    <div
                                                        class="shrink-0 rounded border border-orange-100 bg-orange-50 px-1.5 py-0.5 text-[10px] font-bold text-orange-700 shadow-sm"
                                                    >
                                                        {{ row.days[dayIndex].calories }} kcal
                                                    </div>
                                                </div>
                                                <span class="line-clamp-2 text-[12px] leading-snug font-semibold text-slate-800">
                                                    {{ row.days[dayIndex].dish_name }}
                                                </span>
                                                <div class="mt-2 flex items-center justify-between gap-1">
                                                    <span class="text-[12px] font-bold tabular-nums text-slate-900">
                                                        S/ {{ Number(row.days[dayIndex].price).toFixed(2) }}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        title="Ver / editar quebrado"
                                                        class="rounded-full p-1 text-slate-400 transition-colors hover:bg-orange-50 hover:text-[#FF5A1F]"
                                                        @click.stop="openQuebradoModal(row, dayIndex)"
                                                    >
                                                        <Calculator class="h-3.5 w-3.5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        title="Quitar plato asignado"
                                                        class="rounded-full p-1 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-500"
                                                        @click.stop="clearDayDish(rowIndex, dayIndex)"
                                                    >
                                                        <X class="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>

        <!-- Search Modal -->
        <Dialog :open="isSearchModalOpen" @update:open="isSearchModalOpen = $event">
            <DialogContent class="overflow-hidden rounded-xl border-0 bg-white p-0 shadow-2xl sm:max-w-[850px]">
                <DialogHeader class="border-b border-slate-100 bg-white p-6 pb-4">
                    <DialogTitle class="flex items-center gap-2 text-xl font-bold text-slate-800">
                        <Search class="h-5 w-5 text-[#FF5A1F]" />
                        Buscar Plato
                    </DialogTitle>
                </DialogHeader>
                <div class="p-6 pt-2">
                    <div class="mt-4 mb-6 flex flex-col gap-3 sm:flex-row">
                        <div class="relative min-w-0 flex-[1.6]">
                            <input
                                ref="searchInputRef"
                                v-model="searchQuery"
                                type="text"
                                placeholder="Ej. Lomo saltado, arroz..."
                                class="w-full rounded-lg border border-slate-300 py-2 pr-3 pl-3 text-sm font-medium text-slate-800 shadow-sm transition-all placeholder:text-slate-400 focus:border-[#FF5A1F] focus:ring-2 focus:ring-[#FF5A1F]"
                                @keyup.enter="searchDish"
                            />
                        </div>
                        <select
                            v-model="searchCategory"
                            class="min-w-0 flex-[1.4] truncate rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm font-medium text-slate-800 shadow-sm focus:ring-2 focus:ring-[#FF5A1F]"
                            @change="searchDish"
                        >
                            <option value="">Todas las Categorías</option>
                            <option v-for="cat in props.dishCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                        <select
                            v-model="searchLevel"
                            class="min-w-0 flex-1 truncate rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm font-medium text-slate-800 shadow-sm focus:ring-2 focus:ring-[#FF5A1F]"
                            @change="searchDish"
                        >
                            <option value="">Todos los Niveles</option>
                            <option v-for="level in props.levels" :key="level.id" :value="level.id">{{ level.name }}</option>
                        </select>
                        <button
                            class="flex shrink-0 items-center justify-center rounded-lg bg-[#FF5A1F] p-2.5 text-white shadow-sm transition-colors hover:bg-[#e04a17]"
                            @click="searchDish"
                        >
                            <Search class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="max-h-[400px] overflow-y-auto rounded-md border border-slate-200">
                        <ul v-if="searchResults.length > 0" class="divide-y divide-slate-100">
                            <template v-for="dish in searchResults" :key="dish.id">
                                <template v-if="dish.recipes && dish.recipes.length > 0">
                                    <li
                                        v-for="recipe in dish.recipes"
                                        :key="recipe.id"
                                        class="flex flex-col gap-4 p-4 transition-colors hover:bg-orange-50/50 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div>
                                            <p class="text-sm font-bold text-slate-800">{{ dish.name }}</p>
                                            <p class="mt-1 text-[11px] text-slate-600">
                                                Categoría:
                                                <span class="font-medium text-slate-800">{{
                                                    dish.dish_categories?.[0]?.name || 'Sin Categoría'
                                                }}</span>
                                                &bull; Nivel: <span class="font-medium text-slate-800">{{ recipe.level?.name || 'Sin Nivel' }}</span>
                                            </p>
                                            <p class="mt-1 text-[11px] text-slate-500">
                                                Costo: S/ {{ Number(recipe.total_cost || 0).toFixed(2) }} &bull; Calorías:
                                                {{ recipe.total_calories || 0 }} kcal
                                            </p>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                class="border-slate-200 text-slate-600 hover:bg-slate-100"
                                                @click="assignDish(dish, recipe, 'all')"
                                            >
                                                Replicar para todos los días
                                            </Button>
                                            <Button
                                                size="sm"
                                                class="bg-[#FF5A1F] text-white hover:bg-[#e04a17]"
                                                @click="assignDish(dish, recipe, 'single')"
                                            >
                                                Asignar
                                            </Button>
                                        </div>
                                    </li>
                                </template>
                                <template v-else>
                                    <li
                                        class="flex flex-col gap-4 p-4 transition-colors hover:bg-orange-50/50 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div>
                                            <p class="text-sm font-bold text-slate-800">{{ dish.name }}</p>
                                            <p class="mt-1 text-xs text-slate-600">
                                                Categoría:
                                                <span class="font-medium text-slate-800">{{
                                                    dish.dish_categories?.[0]?.name || 'Sin Categoría'
                                                }}</span>
                                                &bull; Nivel: <span class="font-medium text-slate-400">Sin Nivel</span>
                                            </p>
                                            <p class="mt-1 text-[11px] text-slate-500">Costo: S/ 0.00 &bull; Calorías: 0 kcal</p>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                class="border-slate-200 text-slate-600 hover:bg-slate-100"
                                                @click="assignDish(dish, {}, 'all')"
                                            >
                                                Replicar para todos los días
                                            </Button>
                                            <Button
                                                size="sm"
                                                class="bg-[#FF5A1F] text-white hover:bg-[#e04a17]"
                                                @click="assignDish(dish, {}, 'single')"
                                            >
                                                Asignar
                                            </Button>
                                        </div>
                                    </li>
                                </template>
                            </template>
                        </ul>
                        <div
                            v-else-if="!searchQuery && !searchCategory && !searchLevel"
                            class="p-8 text-center text-sm font-medium text-slate-600"
                        >
                            Ingrese el nombre de un plato para buscar...
                        </div>
                        <div v-else class="p-8 text-center text-sm font-medium text-slate-600">
                            No se encontraron resultados{{ searchQuery ? ` para "${searchQuery}"` : '' }}.
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Saved Cycles Modal -->
        <Dialog :open="isSavedCyclesModalOpen" @update:open="isSavedCyclesModalOpen = $event">
            <DialogContent class="overflow-hidden rounded-xl border-0 bg-white p-0 shadow-2xl sm:max-w-[700px]">
                <DialogHeader class="border-b border-slate-100 bg-white p-6 pb-4">
                    <DialogTitle class="flex items-center gap-2 text-xl font-bold text-slate-800">
                        <Settings2 class="h-5 w-5 text-[#FF5A1F]" />
                        Ajustes de Ciclos ({{ props.savedCycles?.length || 0 }} guardados)
                    </DialogTitle>
                </DialogHeader>
                <div class="max-h-[500px] overflow-y-auto bg-slate-50/30 p-6">
                    <ul class="divide-y divide-slate-200/60" v-if="props.savedCycles && props.savedCycles.length > 0">
                        <li
                            v-for="cycle in props.savedCycles"
                            :key="cycle.id"
                            class="flex items-center justify-between gap-4 rounded px-2 py-4 transition-colors hover:bg-white"
                        >
                            <div>
                                <p class="text-[13px] leading-snug font-bold text-slate-800">
                                    {{ cycle.name || 'Ciclo sin nombre' }} - ID: {{ cycle.id }}
                                </p>
                                <p class="mb-1 text-[11px] text-slate-400 italic">{{ getServiceName(cycle.serviceable_id) }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    <span class="font-medium">Días generados:</span> {{ cycle.days }}
                                    <span class="mx-1">&bull;</span>
                                    <span class="font-medium">Actualizado:</span> {{ new Date(cycle.updated_at).toLocaleDateString() }}
                                </p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="border-blue-200 text-blue-600 hover:bg-blue-50"
                                    @click="compareCycle(cycle)"
                                >
                                    Comparar
                                </Button>
                                <Button size="sm" class="bg-[#FF5A1F] text-white hover:bg-[#e04a17]" @click="copyCycle(cycle)"> Copiar </Button>
                            </div>
                        </li>
                    </ul>
                    <div v-else class="py-8 text-center text-slate-500">No hay ciclos guardados en la base de datos aún.</div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Multiple Cycles Selection Modal (Auto-opens when multiple cycles are detected for a service) -->
        <Dialog :open="isServiceCyclesModalOpen" @update:open="isServiceCyclesModalOpen = $event">
            <DialogContent class="overflow-hidden rounded-xl border-0 bg-white p-0 shadow-2xl sm:max-w-[600px]">
                <DialogHeader class="border-b border-slate-100 bg-white p-6 pb-4">
                    <DialogTitle class="flex items-center gap-2 text-xl font-bold text-slate-800">
                        <CalendarDays class="h-5 w-5 text-blue-600" />
                        Seleccionar Ciclo
                    </DialogTitle>
                    <p class="mt-1 text-sm text-slate-500">
                        Se han encontrado múltiples ciclos para este servicio. Por favor, seleccione cuál desea cargar.
                    </p>
                </DialogHeader>
                <div class="max-h-[400px] overflow-y-auto bg-slate-50/30 p-6">
                    <ul class="divide-y divide-slate-200/60">
                        <li
                            v-for="cycle in serviceCyclesToSelect"
                            :key="cycle.id"
                            class="mb-2 flex cursor-pointer items-center justify-between gap-4 rounded-lg border border-transparent bg-white/50 px-4 py-4 transition-all hover:border-blue-100 hover:bg-white hover:shadow-sm"
                            @click="copyCycle(cycle)"
                        >
                            <div class="flex items-center gap-4">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                                    <CalendarDays class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-800">{{ cycle.name || 'Ciclo sin nombre' }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-500">
                                        ID: {{ cycle.id }} &bull; {{ cycle.days }} días &bull; Actualizado:
                                        {{ new Date(cycle.updated_at).toLocaleDateString() }}
                                    </p>
                                </div>
                            </div>
                            <Button
                                size="sm"
                                variant="ghost"
                                class="text-[10px] font-bold tracking-wider text-blue-600 uppercase hover:bg-blue-50 hover:text-blue-700"
                            >
                                Cargar
                            </Button>
                        </li>
                    </ul>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Structure Selection Modal (opens when several structures are saved for the same service, so none is loaded automatically) -->
        <Dialog :open="isStructureSelectModalOpen" @update:open="isStructureSelectModalOpen = $event">
            <DialogContent class="overflow-hidden rounded-xl border-0 bg-white p-0 shadow-2xl sm:max-w-[600px]">
                <DialogHeader class="border-b border-slate-100 bg-white p-6 pb-4">
                    <DialogTitle class="flex items-center gap-2 text-xl font-bold text-slate-800">
                        <FolderOpen class="h-5 w-5 text-[#FF5A1F]" />
                        Seleccionar Estructura
                    </DialogTitle>
                    <p class="mt-1 text-sm text-slate-500">
                        Este servicio tiene {{ structuresToSelect.length }} estructuras guardadas. Seleccione cuál desea usar para el ciclo.
                    </p>
                </DialogHeader>
                <div class="max-h-[400px] overflow-y-auto bg-slate-50/30 p-6">
                    <ul class="divide-y divide-slate-200/60">
                        <li
                            v-for="structure in structuresToSelect"
                            :key="structure.id"
                            class="mb-2 flex cursor-pointer items-center justify-between gap-4 rounded-lg border border-transparent bg-white/50 px-4 py-4 transition-all hover:border-orange-100 hover:bg-white hover:shadow-sm"
                            @click="selectStructure(structure)"
                        >
                            <div class="flex items-center gap-4">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-orange-50 text-[#FF5A1F]">
                                    <FolderOpen class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-800">{{ structure.name || 'Estructura sin nombre' }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-500">
                                        {{ structure.costs?.length || 0 }} categorías
                                        <template v-if="structure.selling_price">
                                            &bull; Precio: S/ {{ Number(structure.selling_price).toFixed(2) }}
                                        </template>
                                    </p>
                                </div>
                            </div>
                            <Button
                                size="sm"
                                variant="ghost"
                                class="text-[10px] font-bold tracking-wider text-[#FF5A1F] uppercase hover:bg-orange-50 hover:text-[#e04a17]"
                            >
                                Cargar
                            </Button>
                        </li>
                    </ul>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Cost-vs-limits range indicator (opens from a row's aggregate semaphore, or a single day cell) -->
        <Dialog :open="isChartModalOpen" @update:open="isChartModalOpen = $event">
            <DialogContent class="overflow-hidden rounded-xl border-0 bg-white p-0 shadow-2xl sm:max-w-[480px]">
                <DialogHeader class="border-b border-slate-100 bg-white p-6 pb-4">
                    <DialogTitle class="flex items-center gap-2 text-xl font-bold text-slate-800">
                        <LineChart class="h-5 w-5 text-[#FF5A1F]" />
                        {{ chartRow?.category }}
                        <span
                            v-if="chartDayIndex !== null"
                            class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-500"
                        >
                            Día {{ chartDayIndex }}
                        </span>
                    </DialogTitle>
                    <p class="mt-1 text-sm text-slate-500">
                        <template v-if="chartDayIndex !== null">
                            {{ chartRow?.days[chartDayIndex]?.dish_name }} — costo de ese día frente al rango de costo de la estructura.
                        </template>
                        <template v-else> Costo promedio del plato frente al rango de costo de la estructura. </template>
                    </p>
                </DialogHeader>

                <div v-if="chartStats" class="p-6 pt-8 pb-12">
                    <div class="mb-10 text-center">
                        <p class="text-[11px] font-semibold tracking-wide text-slate-400 uppercase">
                            {{ chartDayIndex !== null ? 'Costo del Día' : 'Costo Promedio' }}
                        </p>
                        <p class="mt-1 text-3xl font-bold tabular-nums" :class="getSemaphoreTextClass(chartStats.status)">
                            S/ {{ chartStats.value.toFixed(2) }}
                        </p>
                        <p class="mt-1 text-xs font-bold" :class="getSemaphoreTextClass(chartStats.status)">
                            {{ getSemaphoreText(chartStats.status) }}
                        </p>
                    </div>

                    <div class="relative">
                        <div class="relative h-2 rounded-full bg-slate-100">
                            <div
                                class="absolute inset-y-0 rounded-full bg-green-200/70"
                                :style="{ left: chartStats.minPct + '%', width: chartStats.maxPct - chartStats.minPct + '%' }"
                            ></div>
                            <div
                                class="absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-md"
                                :class="getSemaphoreColor(chartStats.status)"
                                :style="{ left: chartStats.valuePct + '%' }"
                            ></div>
                        </div>

                        <div
                            class="absolute top-full mt-1.5 flex -translate-x-1/2 flex-col items-center"
                            :style="{ left: chartStats.minPct + '%' }"
                        >
                            <span class="h-1.5 w-px bg-slate-300"></span>
                            <span class="mt-1 text-center text-[10px] font-semibold whitespace-nowrap text-slate-500"
                                >Mínimo<br />S/ {{ chartStats.min.toFixed(2) }}</span
                            >
                        </div>
                        <div
                            class="absolute top-full mt-1.5 flex -translate-x-1/2 flex-col items-center"
                            :style="{ left: chartStats.maxPct + '%' }"
                        >
                            <span class="h-1.5 w-px bg-slate-300"></span>
                            <span class="mt-1 text-center text-[10px] font-semibold whitespace-nowrap text-slate-500"
                                >Máximo<br />S/ {{ chartStats.max.toFixed(2) }}</span
                            >
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Quebrado (recipe breakdown) quick editor — opens from a day cell's calculator button -->
        <Dialog :open="isQuebradoModalOpen" @update:open="isQuebradoModalOpen = $event">
            <DialogContent class="flex max-h-[85vh] flex-col overflow-hidden rounded-xl border-0 bg-white p-0 shadow-2xl sm:max-w-[1080px]">
                <DialogHeader class="flex-none border-b border-slate-100 bg-white p-6 pb-4">
                    <DialogTitle class="flex items-center gap-2 text-xl font-bold text-slate-800">
                        <Calculator class="h-5 w-5 text-[#FF5A1F]" />
                        Quebrado — {{ quebradoDishName }}
                    </DialogTitle>
                    <p class="mt-1 text-sm text-slate-500">
                        Estás editando la receta maestra de este plato: los cambios se reflejarán en toda la app, pero no alteran los ciclos ya
                        guardados.
                    </p>
                </DialogHeader>

                <div v-if="isQuebradoLoading" class="flex flex-1 flex-col items-center justify-center gap-3 p-16">
                    <Loader2 class="h-6 w-6 animate-spin text-[#FF5A1F]" />
                    <span class="text-sm font-medium text-slate-500">Cargando quebrado...</span>
                </div>

                <div v-else-if="quebradoRecipe" class="flex-1 overflow-y-auto p-6">
                    <div class="overflow-x-auto rounded-lg border border-slate-100">
                        <Table class="w-full text-xs">
                            <TableHeader>
                                <TableRow class="border-b-slate-200 bg-slate-50/80 hover:bg-slate-50/80">
                                    <TableHead class="h-9 text-[10px] font-semibold tracking-wider text-slate-600 uppercase">Insumo</TableHead>
                                    <TableHead class="h-9 text-center text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                        >P. Unit</TableHead
                                    >
                                    <TableHead class="h-9 text-center text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                        >P. x Gr</TableHead
                                    >
                                    <TableHead class="h-9 text-center text-[10px] font-semibold tracking-wider text-slate-600 uppercase"
                                        >Cantidad</TableHead
                                    >
                                    <TableHead class="h-9 text-center text-[10px] font-semibold tracking-wider text-slate-600 uppercase">Und</TableHead>
                                    <TableHead class="h-9 text-center text-[10px] font-semibold tracking-wider text-slate-600 uppercase"
                                        >Costo Base</TableHead
                                    >
                                    <TableHead class="h-9 text-center text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                        >Mat. Prima</TableHead
                                    >
                                    <TableHead class="h-9 text-center text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                        >Desecho</TableHead
                                    >
                                    <TableHead class="h-9 text-center text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                        >Prod. Final</TableHead
                                    >
                                    <TableHead class="h-9 text-center text-[10px] font-semibold tracking-wider text-orange-700 uppercase"
                                        >Calorías</TableHead
                                    >
                                    <TableHead class="h-9 text-center text-[10px] font-semibold tracking-wider text-slate-600 uppercase"
                                        >Ajustes</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="ingredient in quebradoRecipe.ingredients"
                                    :key="ingredient.id"
                                    class="border-b-slate-100 transition-colors hover:bg-slate-50/50"
                                >
                                    <TableCell class="py-2 font-semibold text-slate-800">{{ ingredient.name }}</TableCell>
                                    <TableCell class="py-2 text-center font-mono text-slate-500">
                                        S/ {{ Number(ingredient.unit_price || 0).toFixed(2) }}
                                    </TableCell>
                                    <TableCell class="py-2 text-center font-mono text-[11px] text-slate-400">
                                        S/ {{ Number((ingredient.unit_price || 0) / 1000).toFixed(4) }}
                                    </TableCell>
                                    <TableCell class="py-2 text-center">
                                        <Input
                                            type="number"
                                            v-model="ingredient.input_quantity"
                                            @input="onQuebradoWeightInput(ingredient)"
                                            step="any"
                                            class="mx-auto h-7 w-18 rounded-lg border-slate-200 text-center text-xs font-bold text-slate-800 focus-visible:ring-[#FF5A1F]"
                                        />
                                    </TableCell>
                                    <TableCell class="py-2 text-center">
                                        <select
                                            v-model="ingredient.selected_unit"
                                            @change="onQuebradoWeightInput(ingredient)"
                                            class="h-7 rounded-lg border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 focus:ring-2 focus:ring-[#FF5A1F]/30"
                                        >
                                            <option value="Kg">Kg</option>
                                            <option value="g">Gr</option>
                                        </select>
                                    </TableCell>
                                    <TableCell class="py-2 text-center font-mono font-bold tabular-nums text-slate-900">
                                        S/ {{ Number(ingredient.cost).toFixed(2) }}
                                    </TableCell>
                                    <TableCell class="py-2 text-center font-mono text-slate-500">
                                        {{ Number(ingredient.gross_weight).toFixed(1) }} g
                                    </TableCell>
                                    <TableCell class="py-2 text-center font-mono text-slate-500">
                                        {{ Number(ingredient.solid_waste).toFixed(1) }} g
                                    </TableCell>
                                    <TableCell class="py-2 text-center font-mono font-semibold text-slate-700">
                                        {{ Number(ingredient.final_product).toFixed(1) }} g
                                    </TableCell>
                                    <TableCell class="py-2 text-center font-mono font-semibold text-orange-700">
                                        {{ Number(ingredient.calories).toFixed(1) }} kcal
                                    </TableCell>
                                    <TableCell class="py-2 text-center">
                                        <div class="flex items-center justify-center">
                                            <CalcPopover
                                                :ingredient="ingredient"
                                                :totalMateriaPrima="quebradoRecipe.total_gross_weight"
                                                :totalWasteWeight="quebradoRecipe.total_waste_weight"
                                                :totalCalories="quebradoRecipe.total_calories"
                                                :totalCost="quebradoRecipe.total_cost"
                                                :totalfinalProduct="quebradoRecipe.total_net_weight"
                                                @calcMassiveProperties="calcQuebradoMassiveProperties"
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>

                                <TableRow v-if="quebradoRecipe.ingredients.length === 0">
                                    <TableCell colspan="10" class="h-24 text-center text-sm text-slate-400">
                                        Este plato no tiene ingredientes registrados en su quebrado.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                        <div class="rounded-lg border border-slate-100 bg-slate-50/60 p-3 text-center">
                            <div class="text-[10px] font-semibold tracking-wider text-slate-400 uppercase">Peso Bruto</div>
                            <div class="mt-0.5 font-mono text-sm font-bold text-slate-800">
                                {{ Number(quebradoRecipe.total_gross_weight).toFixed(1) }} g
                            </div>
                        </div>
                        <div class="rounded-lg border border-slate-100 bg-slate-50/60 p-3 text-center">
                            <div class="text-[10px] font-semibold tracking-wider text-slate-400 uppercase">Mermas Totales</div>
                            <div class="mt-0.5 font-mono text-sm font-bold text-slate-800">
                                {{ Number(quebradoRecipe.total_waste_weight).toFixed(1) }} g
                            </div>
                        </div>
                        <div class="rounded-lg border border-slate-100 bg-slate-50/60 p-3 text-center">
                            <div class="text-[10px] font-semibold tracking-wider text-slate-400 uppercase">Calorías</div>
                            <div class="mt-0.5 font-mono text-sm font-bold text-orange-700">
                                {{ Number(quebradoRecipe.total_calories).toFixed(1) }} kcal
                            </div>
                        </div>
                        <div class="rounded-lg border border-orange-100 bg-orange-50/60 p-3 text-center">
                            <div class="text-[10px] font-semibold tracking-wider text-orange-600 uppercase">Costo Receta</div>
                            <div class="mt-0.5 font-mono text-sm font-bold text-orange-700">
                                S/ {{ Number(quebradoRecipe.total_cost).toFixed(2) }}
                            </div>
                        </div>
                        <div class="rounded-lg border border-slate-100 bg-slate-50/60 p-3 text-center">
                            <div class="text-[10px] font-semibold tracking-wider text-slate-400 uppercase">Prod. Final</div>
                            <div class="mt-0.5 font-mono text-sm font-bold text-slate-800">
                                {{ Number(quebradoRecipe.total_net_weight).toFixed(1) }} g
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="quebradoRecipe" class="flex flex-none items-center justify-end gap-2 border-t border-slate-100 bg-white p-4">
                    <Button variant="outline" class="border-slate-200 text-slate-600 hover:bg-slate-50" @click="isQuebradoModalOpen = false">
                        Cancelar
                    </Button>
                    <Button
                        :disabled="isQuebradoSaving"
                        class="flex items-center gap-2 bg-[#FF5A1F] text-white hover:bg-[#e04a17]"
                        @click="saveQuebrado"
                    >
                        <Loader2 v-if="isQuebradoSaving" class="h-4 w-4 animate-spin" />
                        <Save v-else class="h-4 w-4" />
                        Guardar Cambios
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
