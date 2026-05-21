<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { Cafe, Dish, DishCategory, MealType, MenuStructure, WeeklyProgram } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import dayjs from 'dayjs';
import 'dayjs/locale/es';
import Swal from 'sweetalert2';
import { computed, ref, watch } from 'vue';

dayjs.locale('es');

interface Props {
    cafes: Cafe[];
    programs: WeeklyProgram[];
    dish_categories: DishCategory[];
    menu_structure: MenuStructure[];
    dishes: Dish[];
    menu_cycles?: any[];
    mines?: any[];
}

const props = defineProps<Props>();

const meals: MealType[] = ['Desayuno', 'Almuerzo', 'Cena', 'Refrigerio'];
const startDate = ref(dayjs().startOf('week').add(1, 'day').format('YYYY-MM-DD'));
const daysCount = ref(0);

const form = useForm({
    cafe_id: '',
    start_date: startDate.value,
    end_date: dayjs(startDate.value).add(6, 'days').format('YYYY-MM-DD'),
    items: [] as any[],
    portions: [] as any[],
});

const selectedMineId = ref<string>('');
const selectedUnitId = ref<string>('');

const availableUnits = computed(() => {
    if (!selectedMineId.value || !props.mines) return [];
    const mine = props.mines.find((m) => String(m.id) === String(selectedMineId.value));
    return mine ? mine.units || [] : [];
});

const availableCafes = computed(() => {
    if (!selectedUnitId.value || !availableUnits.value) return [];
    const unit = availableUnits.value.find((u: any) => String(u.id) === String(selectedUnitId.value));
    return unit ? unit.cafes || [] : [];
});

const handleMineChange = () => {
    selectedUnitId.value = '';
    form.cafe_id = '';
};

const handleUnitChange = () => {
    form.cafe_id = '';
};

const resolveMineAndUnitFromCafe = (cafeId: string) => {
    if (!props.mines || !cafeId) return;
    for (const mine of props.mines) {
        if (!mine.units) continue;
        for (const unit of mine.units) {
            if (!unit.cafes) continue;
            for (const cafe of unit.cafes) {
                if (String(cafe.id) === String(cafeId)) {
                    selectedMineId.value = String(mine.id);
                    selectedUnitId.value = String(unit.id);
                    return;
                }
            }
        }
    }
};

const selectedServiceId = ref<string>('');

const availableServices = computed(() => {
    if (!form.cafe_id || !props.mines) return [];
    for (const mine of props.mines) {
        if (!mine.units) continue;
        for (const unit of mine.units) {
            if (!unit.cafes) continue;
            const cafe = unit.cafes.find((c: any) => String(c.id) === String(form.cafe_id));
            if (cafe) {
                return cafe.services || [];
            }
        }
    }
    return [];
});

const activeMealType = computed(() => {
    if (!selectedServiceId.value || !availableServices.value) return null;
    const srv = availableServices.value.find((s: any) => String(s.id) === String(selectedServiceId.value));
    if (!srv) return null;

    const matchedMeal = meals.find((m) => m.toLowerCase() === srv.name.toLowerCase());
    return matchedMeal || srv.name;
});

watch(
    () => form.cafe_id,
    (newCafeId) => {
        if (newCafeId && (!selectedMineId.value || !selectedUnitId.value)) {
            resolveMineAndUnitFromCafe(newCafeId);
        }
        setTimeout(() => {
            if (availableServices.value && availableServices.value.length > 0) {
                selectedServiceId.value = String(availableServices.value[0].id);
            } else {
                selectedServiceId.value = '';
            }
        }, 50);
    },
);

// Local reactive state for the grid to avoid find() in template
const portionsGrid = ref<Record<string, number>>({});
const itemsGrid = ref<Record<string, string>>({});
const localMenuStructure = ref<MenuStructure[]>(JSON.parse(JSON.stringify(props.menu_structure)));

const dates = computed(() => {
    return Array.from({ length: daysCount.value }, (_, i) => dayjs(form.start_date).add(i, 'days').format('YYYY-MM-DD'));
});

// Initialize items and portions
const initializeGrid = () => {
    portionsGrid.value = {};
    itemsGrid.value = {};

    dates.value.forEach((date) => {
        meals.forEach((meal) => {
            const portKey = `${date}_${meal}`;
            portionsGrid.value[portKey] = 0;

            const structureForMeal = localMenuStructure.value.filter((s) => s.meal_type === meal);
            structureForMeal.forEach((s) => {
                const itemKey = `${date}_${meal}_${s.id}`;
                itemsGrid.value[itemKey] = '';
            });
        });
    });
};

// Reset grid when date changes
const handleDateChange = () => {
    form.end_date = dayjs(form.start_date)
        .add(daysCount.value - 1, 'days')
        .format('YYYY-MM-DD');
    initializeGrid();
};

const handleDaysCountChange = () => {
    if (daysCount.value < 1) daysCount.value = 1;
    if (daysCount.value > 31) daysCount.value = 31;
    form.end_date = dayjs(form.start_date)
        .add(daysCount.value - 1, 'days')
        .format('YYYY-MM-DD');
    initializeGrid();
};

const increaseDays = () => {
    if (daysCount.value < 31) {
        daysCount.value++;
        handleDaysCountChange();
    }
};

const decreaseDays = () => {
    if (daysCount.value > 1) {
        daysCount.value--;
        handleDaysCountChange();
    }
};

initializeGrid();

const submit = () => {
    // Sync local grid to form arrays before posting
    form.end_date = dayjs(form.start_date)
        .add(daysCount.value - 1, 'days')
        .format('YYYY-MM-DD');
    form.portions = [];
    form.items = [];

    for (const [key, val] of Object.entries(portionsGrid.value)) {
        const [date, meal] = key.split('_');
        form.portions.push({
            date,
            meal_type: meal as MealType,
            portions_count: val,
        });
    }

    for (const [key, val] of Object.entries(itemsGrid.value)) {
        const [date, meal, structId] = key.split('_');
        const struct = localMenuStructure.value.find((s) => s.id === parseInt(structId));

        form.items.push({
            date,
            meal_type: meal as MealType,
            dish_category_id: struct ? struct.dish_category_id : null,
            dish_id: val || null,
        });
    }

    form.post(route('planning.store'));
};

const importForm = useForm({
    file: null as File | null,
});

const fileInput = ref<HTMLInputElement | null>(null);

const triggerImport = () => {
    fileInput.value?.click();
};

const handleFileImport = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        importForm.file = file;
        importForm.post(route('dish-categories.import'), {
            onSuccess: () => {
                importForm.reset();
                if (fileInput.value) fileInput.value.value = '';
            },
        });
    }
};

const relForm = useForm({
    file: null as File | null,
});

const relFileInput = ref<HTMLInputElement | null>(null);

const triggerRelImport = () => {
    relFileInput.value?.click();
};

const handleRelFileImport = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        relForm.file = file;
        relForm.post(route('dish-categories.import-relationships'), {
            onSuccess: () => {
                relForm.reset();
                if (relFileInput.value) relFileInput.value.value = '';
            },
        });
    }
};

const getDishesForCell = (date: string, meal: string, struct: any) => {
    const categoryId = struct.dish_category_id;
    const filtered = props.dishes.filter((d) => d.dish_categories?.some((c: any) => c.id === categoryId));

    const assignedIdStr = itemsGrid.value[`${date}_${meal}_${struct.id}`];
    if (assignedIdStr) {
        const assignedId = parseInt(assignedIdStr);
        if (!filtered.some((d) => d.id === assignedId)) {
            const assignedDish = props.dishes.find((d) => d.id === assignedId);
            if (assignedDish) {
                return [...filtered, assignedDish];
            }
        }
    }
    return filtered;
};

const filteredCycles = computed(() => {
    if (!props.menu_cycles) return [];
    // Muestra los ciclos que pertenecen a este café o no están asignados a ningún café
    return props.menu_cycles.filter((c) => !c.cafe_id || String(c.cafe_id) === String(form.cafe_id));
});

const loadMenuCycle = (cycleIdStr: string) => {
    const cycleId = parseInt(cycleIdStr);
    const cycle = props.menu_cycles?.find((c) => c.id === cycleId);
    if (!cycle) return;

    const mealTypeRaw = cycle.meal_type;
    // Búsqueda insensible a mayúsculas/minúsculas para normalizar el tipo de comida
    const mealType = meals.find((m) => m.toLowerCase() === mealTypeRaw.toLowerCase());

    if (!mealType) {
        Swal.fire({
            icon: 'error',
            title: 'Servicio No Válido',
            text: `El tipo de servicio de este ciclo (${mealTypeRaw}) no es válido en esta planificación.`,
            confirmButtonColor: '#FF5A1F',
        });
        return;
    }

    // Validar que el número de días de la planificación sea mayor o igual al del ciclo
    if (daysCount.value < cycle.days) {
        Swal.fire({
            icon: 'warning',
            title: 'Días Insuficientes',
            text: `El ciclo seleccionado tiene ${cycle.days} días, pero su planificación actual solo tiene ${daysCount.value} días. Por favor, aumente los días de la planificación primero.`,
            confirmButtonColor: '#FF5A1F',
        });
        return;
    }

    Swal.fire({
        title: '¿Cargar Ciclo de Menú?',
        text: `¿Desea cargar la estructura y los platos de "${cycle.name}" para el servicio de "${mealType}"? Esto reemplazará los platos actuales de ese servicio.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, cargar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#FF5A1F',
    }).then((result) => {
        if (result.isConfirmed) {
            // 1. Reconstruir localMenuStructure para este mealType basándose exactamente en el ciclo
            const otherMeals = localMenuStructure.value.filter((s) => s.meal_type !== mealType);
            const newMealStructure = cycle.cycle_data.map((cycleRow: any, index: number) => {
                const categoryObj = props.dish_categories?.find((c) => c.id === cycleRow.dishCategoryId);
                return {
                    id: cycleRow.id || 20000 + index, // ID único temporal
                    meal_type: mealType,
                    dish_category_id: cycleRow.dishCategoryId,
                    dish_category: categoryObj || { id: cycleRow.dishCategoryId, name: cycleRow.category },
                };
            });
            localMenuStructure.value = [...otherMeals, ...newMealStructure];

            // 2. Inicializar la cuadrícula local preservando valores de otras comidas
            const oldPortions = { ...portionsGrid.value };
            const oldItems = { ...itemsGrid.value };

            portionsGrid.value = {};
            itemsGrid.value = {};

            dates.value.forEach((date) => {
                meals.forEach((meal) => {
                    const portKey = `${date}_${meal}`;
                    portionsGrid.value[portKey] = oldPortions[portKey] !== undefined ? oldPortions[portKey] : 0;

                    const structureForMeal = localMenuStructure.value.filter((s) => s.meal_type === meal);
                    structureForMeal.forEach((s) => {
                        const itemKey = `${date}_${meal}_${s.id}`;
                        if (meal === mealType) {
                            itemsGrid.value[itemKey] = '';
                        } else {
                            itemsGrid.value[itemKey] = oldItems[itemKey] || '';
                        }
                    });
                });
            });

            // 3. Poblar la cuadrícula con los platos del ciclo
            let assignedCount = 0;
            cycle.cycle_data.forEach((cycleRow: any, index: number) => {
                const tempId = cycleRow.id || 20000 + index;
                for (let dayNum = 1; dayNum <= cycle.days; dayNum++) {
                    const dateIndex = dayNum - 1;
                    if (dateIndex < dates.value.length) {
                        const date = dates.value[dateIndex];
                        const dayData = cycleRow.days?.[dayNum];
                        if (dayData && dayData.dish_id) {
                            const itemKey = `${date}_${mealType}_${tempId}`;
                            itemsGrid.value[itemKey] = String(dayData.dish_id);
                            assignedCount++;
                        }
                    }
                }
            });

            Swal.fire({
                icon: 'success',
                title: 'Ciclo Cargado',
                text: `Se han configurado las categorías y cargado con éxito ${assignedCount} platos en el servicio de "${mealType}".`,
                timer: 2000,
                showConfirmButton: false,
            });
        }
    });
};
</script>

<template>
    <Head title="Planificación Semanal" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Planificación Semanal</h1>
                    <p class="mt-0.5 text-xs text-slate-500">Gestione y programe platos para múltiples días con total flexibilidad.</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('purchase_orders.index')">
                        <Button variant="outline" class="rounded-xl">Ver Órdenes de Compra</Button>
                    </Link>
                    <Button
                        @click="submit"
                        :disabled="form.processing"
                        class="rounded-xl bg-[#FF5A1F] text-white shadow-sm shadow-orange-500/20 hover:bg-[#e04a17]"
                    >
                        Guardar Planificación
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-4">
                <!-- Sidebar de Configuración -->
                <Card class="overflow-hidden rounded-2xl border-none bg-white shadow-sm lg:col-span-1">
                    <CardHeader class="border-b border-slate-100 pb-4">
                        <CardTitle class="flex items-center gap-2 text-lg font-bold text-slate-800">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="20"
                                height="20"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="text-slate-500"
                            >
                                <circle cx="12" cy="12" r="3" />
                                <path
                                    d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"
                                />
                            </svg>
                            <span>Configuración</span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4 p-5">
                        <div class="flex flex-col gap-2">
                            <label class="flex items-center gap-1.5 text-sm font-medium text-slate-700">
                                <svg
                                    class="h-4 w-4 text-slate-400"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M3 21l8-16 8 16M9.5 12h5"></path>
                                </svg>
                                <span>Mina</span>
                            </label>
                            <Select v-model="selectedMineId" @update:modelValue="handleMineChange">
                                <SelectTrigger class="h-10 rounded-xl border-slate-200 focus:ring-[#FF5A1F]">
                                    <SelectValue placeholder="Seleccionar Mina" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="mine in mines" :key="mine.id" :value="mine.id.toString()">
                                        {{ mine.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="flex items-center gap-1.5 text-sm font-medium text-slate-700">
                                <svg
                                    class="h-4 w-4 text-slate-400"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                                    ></path>
                                </svg>
                                <span>Unidad</span>
                            </label>
                            <Select v-model="selectedUnitId" @update:modelValue="handleUnitChange" :disabled="!selectedMineId">
                                <SelectTrigger class="h-10 rounded-xl border-slate-200 focus:ring-[#FF5A1F]">
                                    <SelectValue placeholder="Seleccionar Unidad" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="unit in availableUnits" :key="unit.id" :value="unit.id.toString()">
                                        {{ unit.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="flex items-center gap-1.5 text-sm font-medium text-slate-700">
                                <svg
                                    class="h-4 w-4 text-slate-400"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M6 14a6 6 0 0012 0V4H6v10zM18 8h2a2 2 0 012 2v2a2 2 0 01-2 2h-2m-12 4h12"
                                    ></path>
                                </svg>
                                <span>Café / Comedor</span>
                            </label>
                            <Select v-model="form.cafe_id" :disabled="!selectedUnitId">
                                <SelectTrigger class="h-10 rounded-xl border-slate-200 focus:ring-[#FF5A1F]">
                                    <SelectValue placeholder="Seleccionar Café" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="cafe in availableCafes" :key="cafe.id" :value="cafe.id.toString()">
                                        {{ cafe.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="flex items-center gap-1.5 text-sm font-medium text-slate-700">
                                <svg
                                    class="h-4 w-4 text-slate-400"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                                    ></path>
                                </svg>
                                <span>Servicio</span>
                            </label>
                            <Select v-model="selectedServiceId" :disabled="!form.cafe_id">
                                <SelectTrigger class="h-10 rounded-xl border-slate-200 focus:ring-[#FF5A1F]">
                                    <SelectValue placeholder="Seleccionar Servicio" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="service in availableServices" :key="service.id" :value="service.id.toString()">
                                        {{ service.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="flex items-center gap-1.5 text-sm font-medium text-slate-700">
                                <svg
                                    class="h-4 w-4 text-slate-400"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                    ></path>
                                </svg>
                                <span>Fecha de Inicio</span>
                            </label>
                            <Input
                                type="date"
                                v-model="form.start_date"
                                @change="handleDateChange"
                                class="h-10 rounded-xl border-slate-200 focus-visible:ring-[#FF5A1F]"
                            />
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="flex items-center gap-1.5 text-sm font-medium text-slate-700">
                                <svg
                                    class="h-4 w-4 text-slate-400"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span>Días de Planificación</span>
                            </label>
                            <div class="flex items-center gap-1">
                                <Button
                                    variant="outline"
                                    size="icon"
                                    class="h-10 w-10 shrink-0 rounded-xl border-slate-200 text-slate-600 hover:bg-slate-50"
                                    @click="decreaseDays"
                                    :disabled="daysCount <= 1"
                                >
                                    -
                                </Button>
                                <Input
                                    type="number"
                                    v-model="daysCount"
                                    min="1"
                                    max="31"
                                    class="h-10 w-full rounded-xl border-slate-200 text-center font-bold focus-visible:ring-[#FF5A1F]"
                                    @change="handleDaysCountChange"
                                />
                                <Button
                                    variant="outline"
                                    size="icon"
                                    class="h-10 w-10 shrink-0 rounded-xl border-slate-200 text-slate-600 hover:bg-slate-50"
                                    @click="increaseDays"
                                    :disabled="daysCount >= 31"
                                >
                                    +
                                </Button>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="flex items-center gap-1.5 text-sm font-medium text-slate-700">
                                <svg
                                    class="h-4 w-4 text-slate-400"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18"></path>
                                </svg>
                                <span>Cargar Ciclo de Menú</span>
                            </label>
                            <Select @update:modelValue="loadMenuCycle($event)" :disabled="!form.cafe_id">
                                <SelectTrigger class="h-10 rounded-xl border-slate-200 focus:ring-[#FF5A1F]">
                                    <SelectValue placeholder="Elegir un ciclo" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="cycle in filteredCycles" :key="cycle.id" :value="cycle.id.toString()">
                                        {{ cycle.name }} ({{ cycle.meal_type }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                <!-- Área principal de Planificación -->
                <div class="flex flex-col gap-6 lg:col-span-3">
                    <!-- Cabecera de la Matriz y Acciones de Importación -->
                    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-100/50 bg-white p-4 px-6 shadow-sm">
                        <div class="flex flex-col">
                            <h2 class="text-lg font-bold text-slate-800">Matriz de Programación</h2>
                            <p class="text-xs text-slate-500">Asigne platos y defina raciones por día</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="file" ref="fileInput" class="hidden" accept=".xlsx,.xls,.csv" @change="handleFileImport" />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="triggerImport"
                                :disabled="importForm.processing"
                                class="h-9 rounded-xl border-dashed border-slate-200 text-xs text-slate-600 hover:bg-slate-50"
                                title="Importar Categorías de Platos"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="14"
                                    height="14"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="mr-1.5"
                                >
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                    <polyline points="17 8 12 3 7 8" />
                                    <line x1="12" x2="12" y1="3" y2="15" />
                                </svg>
                                {{ importForm.processing ? 'Importando...' : 'Importar Categorías' }}
                            </Button>

                            <input type="file" ref="relFileInput" class="hidden" accept=".xlsx,.xls,.csv" @change="handleRelFileImport" />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="triggerRelImport"
                                :disabled="relForm.processing"
                                class="h-9 rounded-xl border-dashed border-slate-200 text-xs text-slate-600 hover:bg-slate-50"
                                title="Importar Relación Platos-Categorías"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="14"
                                    height="14"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="mr-1.5"
                                >
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                    <line x1="19" x2="19" y1="8" y2="14" />
                                    <line x1="22" x2="16" y1="11" y2="11" />
                                </svg>
                                {{ relForm.processing ? 'Importando...' : 'Importar Relación' }}
                            </Button>
                        </div>
                    </div>

                    <div
                        class="scrollbar-thin scrollbar-thumb-slate-200 scrollbar-track-transparent overflow-x-auto rounded-2xl border border-slate-100 bg-white shadow-sm"
                    >
                        <Table>
                            <TableHeader>
                                <TableRow class="bg-slate-50/50">
                                    <TableHead class="w-[150px] font-semibold text-slate-700">Comida / Día</TableHead>
                                    <TableHead
                                        v-for="date in dates"
                                        :key="date"
                                        class="min-w-[160px] border-l border-slate-100 text-center font-semibold text-slate-700"
                                    >
                                        <div class="text-xs font-bold text-slate-800 capitalize">{{ dayjs(date).format('dddd') }}</div>
                                        <div class="mt-0.5 text-[10px] text-slate-400">{{ dayjs(date).format('DD/MM') }}</div>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <template v-for="meal in [activeMealType].filter(Boolean)" :key="meal">
                                    <TableRow class="bg-slate-50/30">
                                        <TableCell class="font-bold text-slate-700">Raciones del Servicio</TableCell>
                                        <TableCell v-for="date in dates" :key="date" class="min-w-[160px] border-l border-slate-100/55 p-2">
                                            <div class="mx-auto flex max-w-[140px] flex-col gap-1">
                                                <label class="text-center text-[9px] font-bold tracking-wider text-slate-400 uppercase"
                                                    >Raciones</label
                                                >
                                                <Input
                                                    type="number"
                                                    v-model="portionsGrid[`${date}_${meal}`]"
                                                    class="h-8 rounded-lg border-slate-200 text-center focus-visible:ring-[#FF5A1F]"
                                                />
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow
                                        v-for="struct in localMenuStructure.filter((s) => s.meal_type === meal)"
                                        :key="struct.id"
                                        class="hover:bg-slate-50/40"
                                    >
                                        <TableCell class="pl-6 text-xs font-medium text-slate-600">
                                            <Select v-model="struct.dish_category_id">
                                                <SelectTrigger
                                                    class="h-8 border-none bg-transparent p-0 text-xs font-bold text-slate-500 shadow-none focus:ring-0"
                                                >
                                                    <SelectValue :placeholder="struct.dish_category?.name || 'Categoría'" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="cat in dish_categories" :key="cat.id" :value="cat.id">
                                                        {{ cat.name }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </TableCell>
                                        <TableCell v-for="date in dates" :key="date" class="min-w-[160px] border-l border-slate-100/55 p-2">
                                            <Select v-model="itemsGrid[`${date}_${meal}_${struct.id}`]">
                                                <SelectTrigger class="h-9 rounded-xl border-slate-200 text-xs focus:ring-[#FF5A1F]">
                                                    <SelectValue placeholder="Elegir plato" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="dish in getDishesForCell(date, meal, struct)"
                                                        :key="dish.id"
                                                        :value="dish.id.toString()"
                                                    >
                                                        {{ dish.name }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </TableCell>
                                    </TableRow>
                                </template>
                                <tr v-if="!activeMealType">
                                    <td :colspan="dates.length + 1" class="py-10 text-center text-sm text-slate-400">
                                        Seleccione un café y un servicio para comenzar la planificación.
                                    </td>
                                </tr>
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </div>

            <div class="mt-8">
                <h2 class="mb-4 text-xl font-bold">Programaciones Guardadas</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="program in programs" :key="program.id" class="rounded-2xl border-none shadow-sm">
                        <CardHeader>
                            <CardTitle class="text-lg">{{ program.cafe?.name }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-col gap-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Periodo:</span>
                                    <span>{{ dayjs(program.start_date).format('DD/MM') }} - {{ dayjs(program.end_date).format('DD/MM') }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Estado:</span>
                                    <span class="capitalize">{{ program.status }}</span>
                                </div>
                                <Button @click="router.post(route('planning.generate-po', program.id))" class="mt-4 rounded-xl" variant="secondary">
                                    Generar Quebrado (PO)
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
