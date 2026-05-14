<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import MenuDisplay from '@/pages/structure-menu/MenuDisplay.vue';
import { Mine } from '@/types';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    Search,
    Settings2,
    Save,
    CalendarDays,
    Download
} from 'lucide-vue-next';

interface Props {
    mines?: Mine[];
    structures?: any[];
    savedCycles?: any[];
    dishCategories?: any[];
    levels?: any[];
}

const props = defineProps<Props>();

const selectedServiceableId = ref<string | null>(null);

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
const repeatedDishes = ref<{rowId: any, dayIndex: number}[]>([]);

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
    return repeatedDishes.value.some(r => r.rowId === rowId && r.dayIndex === dayIndex);
};

const copyCycle = (cycle: any) => {
    inputDays.value = cycle.days;
    generatedDays.value = cycle.days;
    menuStructureData.value = JSON.parse(JSON.stringify(cycle.cycle_data));
    repeatedDishes.value = [];
    isSavedCyclesModalOpen.value = false;
    Swal.fire({
        icon: 'success',
        title: 'Ciclo copiado',
        text: 'La estructura se ha cargado tal como se guardó.',
        timer: 1500,
        showConfirmButton: false
    });
};

const compareCycle = (cycle: any) => {
    repeatedDishes.value = [];
    let matchCount = 0;
    cycle.cycle_data.forEach((compareRow: any) => {
        const currentRow = menuStructureData.value.find((r: any) => r.id === compareRow.id);
        if (currentRow) {
            Object.keys(currentRow.days).forEach(dayKey => {
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

watch(selectedServiceableId, (newId) => {
    repeatedDishes.value = [];
    if (!newId) {
        menuStructureData.value = [];
        return;
    }

    const structure = props.structures?.find(s => String(s.serviceable_id) === String(newId));

    if (!structure) {
        menuStructureData.value = [];
        Swal.fire({
            icon: 'warning',
            title: 'Sin estructura de menú',
            text: 'Este servicio aún no tiene una estructura asignada. Por favor configúrela primero.',
            confirmButtonColor: '#FF5A1F',
        });
        return;
    }

    // Check if we have a saved cycle for this service
    const savedCycle = props.savedCycles?.find(c => String(c.serviceable_id) === String(newId));

    if (savedCycle) {
        inputDays.value = savedCycle.days;
        generatedDays.value = savedCycle.days;
        
        // Re-inject cost limits from structure in case they changed
        menuStructureData.value = savedCycle.cycle_data.map((row: any) => {
            const currentCostInfo = structure.costs?.find((c: any) => c.id === row.id) || {};
            return {
                ...row,
                costValue: parseFloat(currentCostInfo.total_cost || row.costValue || 0),
                costValueMax: parseFloat(currentCostInfo.total_cost_superior || row.costValueMax || 0),
            };
        });
    } else {
        // Populate the table with the structure costs
        menuStructureData.value = (structure.costs || []).map((cost: any) => {
            return {
                id: cost.id,
                category: cost.name || 'Categoría',
                dishCategoryId: cost.dish_category_id,
                costValue: parseFloat(cost.total_cost || 0),
                costValueMax: parseFloat(cost.total_cost_superior || 0),
                days: {} // Will hold { dayIndex: { dish: 'Name', calories: 100, price: 5.5 } }
            };
        });
    }
});

// Search Dialog State
const isSearchModalOpen = ref(false);
const searchQuery = ref('');
const searchCategory = ref('');
const searchLevel = ref('');
const searchResults = ref<any[]>([]);
const currentSearchTarget = ref<{rowIndex: number, dayIndex: number, categoryId: any}>({ rowIndex: -1, dayIndex: -1, categoryId: null });

const openSearchModal = (rowIndex: number, dayIndex: number, categoryId: any) => {
    currentSearchTarget.value = { rowIndex, dayIndex, categoryId };
    searchQuery.value = '';
    searchCategory.value = categoryId || '';
    searchLevel.value = '';
    searchResults.value = [];
    isSearchModalOpen.value = true;
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
                level_id: searchLevel.value || null
            }
        });
        searchResults.value = response.data;
    } catch (err) {
        console.error("Error searching dish", err);
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
            price: recipe.total_cost || '0.00'
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

// Helper for semaphore colors
const getSemaphoreColor = (status: string) => {
    switch (status) {
        case 'gravisimo': return 'bg-red-500';
        case 'pesimo': return 'bg-yellow-400';
        case 'bueno': return 'bg-green-500';
        default: return 'bg-gray-300';
    }
};

const getSemaphoreText = (status: string) => {
    switch (status) {
        case 'gravisimo': return 'Costos Muy Altos';
        case 'pesimo': return 'Costos Muy Bajos';
        case 'bueno': return 'Costos Óptimos';
        default: return 'Sin Asignar';
    }
};

const getRowStatus = (row: any) => {
    const days = Object.values(row.days || {}) as any[];
    if (days.length === 0) return 'desconocido';

    let totalAssignedCost = 0;
    days.forEach(day => {
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

const saveCycle = () => {
    if (!selectedServiceableId.value) {
        Swal.fire('Atención', 'Seleccione un servicio primero', 'warning');
        return;
    }
    
    if (menuStructureData.value.length === 0) {
        Swal.fire('Atención', 'No hay estructura de menú para guardar', 'warning');
        return;
    }

    router.post('/cycles', {
        serviceable_id: selectedServiceableId.value,
        days: generatedDays.value,
        cycle_data: menuStructureData.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: '¡Guardado!',
                text: 'El ciclo de menú se ha guardado correctamente.',
                timer: 2000,
                showConfirmButton: false
            });
        },
        onError: (errors) => {
            console.error(errors);
            Swal.fire('Error', 'Hubo un problema al guardar el ciclo', 'error');
        }
    });
};

const exportCycle = () => {
    if (!selectedServiceableId.value) {
        Swal.fire('Atención', 'Seleccione un servicio para exportar.', 'warning');
        return;
    }
    const hasSavedCycle = props.savedCycles?.some(c => String(c.serviceable_id) === String(selectedServiceableId.value));
    if (!hasSavedCycle) {
        Swal.fire('Atención', 'No hay un ciclo guardado en base de datos para este servicio. Guárdalo primero.', 'warning');
        return;
    }
    window.location.href = `/cycles/export/${selectedServiceableId.value}`;
};
</script>

<template>
    <Head title="Ciclos de Menú" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 p-6 bg-slate-50/50 overflow-hidden">
            <div class="flex-none flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Configuración de Ciclos</h1>
                    <p class="text-sm text-slate-500 mt-1">Gestione la programación de platos por día para cada servicio.</p>
                </div>
                <div class="flex items-center gap-3">
                    <Button variant="outline" class="flex items-center gap-2 bg-white text-green-700 border-green-200 hover:bg-green-50" @click="exportCycle">
                        <Download class="h-4 w-4" />
                        Exportar Excel
                    </Button>
                    <Button variant="outline" class="flex items-center gap-2 bg-white" @click="isSavedCyclesModalOpen = true">
                        <Settings2 class="h-4 w-4" />
                        Ajustes
                    </Button>
                    <Button @click="saveCycle" class="bg-[#FF5A1F] hover:bg-[#e04a17] text-white flex items-center gap-2 shadow-sm shadow-orange-500/20">
                        <Save class="h-4 w-4" />
                        Guardar Ciclo
                    </Button>
                </div>
            </div>

            <div class="flex-none">
                <MenuDisplay :mines="mines" @update:serviceable="selectedServiceableId = $event" />
            </div>

            <div class="flex-1 min-h-0 bg-white rounded-xl shadow-sm border border-slate-100 flex flex-col overflow-hidden">
                <div class="flex-none p-5 border-b border-slate-100 flex flex-wrap items-center justify-between bg-white gap-4">
                    <div class="flex items-center gap-6">
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-medium text-slate-700 whitespace-nowrap">Número de Días:</span>
                            <div class="flex items-center relative">
                                <Input 
                                    type="number" 
                                    v-model="inputDays" 
                                    min="1" 
                                    max="31"
                                    class="w-24 h-9 border-slate-200 focus-visible:ring-[#FF5A1F]" 
                                />
                            </div>
                            <Button @click="generateColumns" size="sm" variant="secondary" class="bg-slate-100 hover:bg-slate-200 text-slate-700 h-9">
                                <CalendarDays class="w-4 h-4 mr-2" />
                                Generar Columnas
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="flex-1 overflow-auto w-full scrollbar-thin scrollbar-thumb-slate-200 scrollbar-track-transparent pb-4 relative isolate">
                    <Table class="w-full border-collapse">
                        <TableHeader>
                            <TableRow class="bg-slate-50/80 border-b-slate-200">
                                <TableHead class="w-[60px] font-semibold text-slate-600 text-center sticky top-0 left-0 bg-slate-50/95 backdrop-blur z-20 shadow-[1px_1px_0_0_#e2e8f0]">Ord</TableHead>
                                <TableHead class="min-w-[220px] font-semibold text-slate-600 sticky top-0 left-[60px] bg-slate-50/95 backdrop-blur z-20 shadow-[1px_1px_0_0_#e2e8f0]">Estructura del Menú</TableHead>
                                <TableHead class="w-[120px] font-semibold text-slate-600 text-center sticky top-0 left-[280px] bg-slate-50/95 backdrop-blur z-20 shadow-[1px_1px_0_0_#e2e8f0]">Semáforo</TableHead>
                                <TableHead 
                                    v-for="(day, index) in daysColumns" 
                                    :key="index"
                                    class="min-w-[180px] font-semibold text-slate-600 border-l border-slate-200 text-center sticky top-0 bg-slate-50/95 backdrop-blur z-10 shadow-[0_1px_0_0_#e2e8f0]"
                                >
                                    {{ day }}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow 
                                v-for="(row, rowIndex) in menuStructureData" 
                                :key="row.id"
                                class="hover:bg-slate-50/50 transition-colors group/row"
                            >
                                <TableCell class="text-center font-medium text-slate-500 border-r border-slate-100 sticky left-0 bg-white group-hover/row:bg-slate-50/95 backdrop-blur z-10 shadow-[1px_0_0_0_#f1f5f9]">
                                    {{ rowIndex + 1 }}
                                </TableCell>
                                
                                <TableCell class="border-r border-slate-100 sticky left-[60px] bg-white group-hover/row:bg-slate-50/95 backdrop-blur z-10 shadow-[1px_0_0_0_#f1f5f9]">
                                    <div class="flex flex-col gap-1.5 py-1">
                                        <Badge variant="outline" class="w-max border-slate-200 text-slate-600 font-semibold bg-slate-50">
                                            S/ {{ row.costValue.toFixed(2) }}
                                        </Badge>
                                        <span class="font-bold text-slate-800 text-[13px] tracking-tight">{{ row.category }}</span>
                                    </div>
                                </TableCell>
                                
                                <TableCell class="text-center border-r border-slate-100 bg-slate-50/40 sticky left-[280px] group-hover/row:bg-slate-100/50 backdrop-blur z-10 shadow-[1px_0_0_0_#f1f5f9]">
                                    <div class="flex flex-col items-center justify-center gap-2 py-2">
                                        <div 
                                            class="w-5 h-5 rounded-full shadow-sm ring-2 ring-white"
                                            :class="getSemaphoreColor(getRowStatus(row))"
                                        ></div>
                                    </div>
                                </TableCell>

                                <!-- Days Columns -->
                                <TableCell 
                                    v-for="dayIndex in generatedDays" 
                                    :key="dayIndex"
                                    class="border-l border-slate-100 p-2 align-top relative group/cell"
                                >
                                    <div class="h-full border transition-all overflow-hidden bg-white flex flex-col"
                                         :class="{
                                             'border-transparent rounded-md shadow-sm group-hover/cell:border-[#FF5A1F]': !isRepeated(row.id, dayIndex),
                                             'border-red-500 rounded-md shadow-md ring-2 ring-red-200 bg-red-50/80': isRepeated(row.id, dayIndex)
                                         }">
                                        
                                        <div 
                                            v-if="!row.days[dayIndex]"
                                            class="w-full h-full min-h-[80px] flex items-center justify-center cursor-pointer hover:bg-slate-50 transition-colors"
                                            @click="openSearchModal(rowIndex, dayIndex, row.dishCategoryId)"
                                        >
                                            <span class="text-xs font-medium text-slate-400">Vacío</span>
                                        </div>
                                        
                                        <template v-else>
                                            <div class="p-3 cursor-pointer" @click="openSearchModal(rowIndex, dayIndex, row.dishCategoryId)">
                                                <div class="flex items-center justify-between mb-2">
                                                    <div class="bg-white border border-slate-200 rounded px-1.5 py-0.5 text-[10px] font-bold text-slate-700 shadow-sm">
                                                        {{ row.days[dayIndex].calories }} kcal
                                                    </div>
                                                </div>
                                                <span class="text-[12px] font-semibold text-blue-700 leading-snug line-clamp-2">
                                                    {{ row.days[dayIndex].dish_name }}
                                                </span>
                                                <div class="mt-2 text-[12px] font-bold text-red-600">
                                                    S/ {{ Number(row.days[dayIndex].price).toFixed(2) }}
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
            <DialogContent class="sm:max-w-[850px] p-0 border-0 shadow-2xl rounded-xl bg-white overflow-hidden">
                <DialogHeader class="p-6 pb-4 border-b border-slate-100 bg-white">
                    <DialogTitle class="text-xl font-bold text-slate-800 flex items-center gap-2">
                        <Search class="w-5 h-5 text-[#FF5A1F]" />
                        Buscar Plato
                    </DialogTitle>
                </DialogHeader>
                <div class="p-6 pt-2">
                    <div class="flex flex-col sm:flex-row gap-3 mb-6 mt-4">
                        <div class="relative flex-[2] min-w-0">
                            <input 
                                ref="searchInputRef"
                                v-model="searchQuery"
                                type="text"
                                placeholder="Ej. Lomo saltado, arroz..."
                                class="w-full pl-4 pr-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-[#FF5A1F] focus:border-[#FF5A1F] text-slate-800 placeholder:text-slate-400 font-medium shadow-sm transition-all"
                                @keyup.enter="searchDish"
                            />
                        </div>
                        <select v-model="searchCategory" class="flex-1 min-w-0 px-3 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-[#FF5A1F] text-slate-800 font-medium shadow-sm bg-white truncate" @change="searchDish">
                            <option value="">Todas las Categorías</option>
                            <option v-for="cat in props.dishCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                        <select v-model="searchLevel" class="flex-1 min-w-0 px-3 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-[#FF5A1F] text-slate-800 font-medium shadow-sm bg-white truncate" @change="searchDish">
                            <option value="">Todos los Niveles</option>
                            <option v-for="level in props.levels" :key="level.id" :value="level.id">{{ level.name }}</option>
                        </select>
                        <button 
                            class="p-3 bg-[#FF5A1F] hover:bg-[#e04a17] text-white rounded-lg shadow-sm transition-colors flex items-center justify-center shrink-0"
                            @click="searchDish"
                        >
                            <Search class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="border rounded-md border-slate-200 max-h-[400px] overflow-y-auto">
                        <ul v-if="searchResults.length > 0" class="divide-y divide-slate-100">
                            <template v-for="dish in searchResults" :key="dish.id">
                                <template v-if="dish.recipes && dish.recipes.length > 0">
                                    <li v-for="recipe in dish.recipes" :key="recipe.id" 
                                        class="p-4 hover:bg-orange-50/50 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 transition-colors">
                                        <div>
                                            <p class="font-bold text-slate-800 text-sm">{{ dish.name }}</p>
                                            <p class="text-[11px] text-slate-600 mt-1">
                                                Categoría: <span class="font-medium text-slate-800">{{ dish.dish_categories?.[0]?.name || 'Sin Categoría' }}</span>
                                                &bull; Nivel: <span class="font-medium text-slate-800">{{ recipe.level?.name || 'Sin Nivel' }}</span>
                                            </p>
                                            <p class="text-[11px] text-slate-500 mt-1">
                                                Costo: S/ {{ Number(recipe.total_cost || 0).toFixed(2) }} &bull; Calorías: {{ recipe.total_calories || 0 }} kcal
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <Button size="sm" variant="outline" class="text-slate-600 border-slate-200 hover:bg-slate-100" @click="assignDish(dish, recipe, 'all')">
                                                Replicar para todos los días
                                            </Button>
                                            <Button size="sm" class="bg-[#FF5A1F] hover:bg-[#e04a17] text-white" @click="assignDish(dish, recipe, 'single')">
                                                Asignar
                                            </Button>
                                        </div>
                                    </li>
                                </template>
                                <template v-else>
                                    <li class="p-4 hover:bg-orange-50/50 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 transition-colors">
                                        <div>
                                            <p class="font-bold text-slate-800 text-sm">{{ dish.name }}</p>
                                            <p class="text-xs text-slate-600 mt-1">
                                                Categoría: <span class="font-medium text-slate-800">{{ dish.dish_categories?.[0]?.name || 'Sin Categoría' }}</span>
                                                &bull; Nivel: <span class="font-medium text-slate-400">Sin Nivel</span>
                                            </p>
                                            <p class="text-[11px] text-slate-500 mt-1">
                                                Costo: S/ 0.00 &bull; Calorías: 0 kcal
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <Button size="sm" variant="outline" class="text-slate-600 border-slate-200 hover:bg-slate-100" @click="assignDish(dish, {}, 'all')">
                                                Replicar para todos los días
                                            </Button>
                                            <Button size="sm" class="bg-[#FF5A1F] hover:bg-[#e04a17] text-white" @click="assignDish(dish, {}, 'single')">
                                                Asignar
                                            </Button>
                                        </div>
                                    </li>
                                </template>
                            </template>
                        </ul>
                        <div v-else-if="searchQuery && searchResults.length === 0" class="p-8 text-center text-slate-500">
                            No se encontraron resultados para "{{ searchQuery }}".
                        </div>
                        <div v-else class="p-8 text-center text-slate-400">
                            Ingrese el nombre de un plato para buscar...
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Saved Cycles Modal -->
        <Dialog :open="isSavedCyclesModalOpen" @update:open="isSavedCyclesModalOpen = $event">
            <DialogContent class="sm:max-w-[700px] p-0 border-0 shadow-2xl rounded-xl bg-white overflow-hidden">
                <DialogHeader class="p-6 pb-4 border-b border-slate-100 bg-white">
                    <DialogTitle class="text-xl font-bold text-slate-800 flex items-center gap-2">
                        <Settings2 class="w-5 h-5 text-[#FF5A1F]" />
                        Ajustes de Ciclos ({{ props.savedCycles?.length || 0 }} guardados)
                    </DialogTitle>
                </DialogHeader>
                <div class="p-6 max-h-[500px] overflow-y-auto bg-slate-50/30">
                    <ul class="divide-y divide-slate-200/60" v-if="props.savedCycles && props.savedCycles.length > 0">
                        <li v-for="cycle in props.savedCycles" :key="cycle.id" class="py-4 px-2 hover:bg-white rounded transition-colors flex items-center justify-between gap-4">
                            <div>
                                <p class="text-[13px] font-bold text-slate-800 leading-snug">{{ getServiceName(cycle.serviceable_id) }}</p>
                                <p class="text-xs text-slate-500 mt-1">
                                    <span class="font-medium">Días generados:</span> {{ cycle.days }} 
                                    <span class="mx-1">&bull;</span>
                                    <span class="font-medium">Actualizado:</span> {{ new Date(cycle.updated_at).toLocaleDateString() }}
                                </p>
                            </div>
                            <div class="flex gap-2 shrink-0">
                                <Button size="sm" variant="outline" class="text-blue-600 border-blue-200 hover:bg-blue-50" @click="compareCycle(cycle)">
                                    Comparar
                                </Button>
                                <Button size="sm" class="bg-[#FF5A1F] hover:bg-[#e04a17] text-white" @click="copyCycle(cycle)">
                                    Copiar
                                </Button>
                            </div>
                        </li>
                    </ul>
                    <div v-else class="text-center text-slate-500 py-8">
                        No hay ciclos guardados en la base de datos aún.
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>