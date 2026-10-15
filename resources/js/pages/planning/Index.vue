<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { Cafe, DishCategory, MealType, MenuStructure, WeeklyProgram } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import dayjs from 'dayjs';
import 'dayjs/locale/es';
import {
    Building2,
    CalendarDays,
    Check,
    ChevronDown,
    Clock,
    Coffee,
    ConciergeBell,
    FileSpreadsheet,
    Loader2,
    Minus,
    Mountain,
    Plus,
    Search,
    Settings2,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, onMounted, ref, watch } from 'vue';

dayjs.locale('es');

interface Props {
    cafes: Cafe[];
    programs_total: number;
    dish_categories: DishCategory[];
    menu_structure: MenuStructure[];
    mines?: any[];
    levels?: any[];
    cities?: any[];
}

const props = defineProps<Props>();

const meals: MealType[] = ['Desayuno', 'Almuerzo', 'Cena', 'Refrigerio'];
const startDate = ref(dayjs().startOf('week').add(1, 'day').format('YYYY-MM-DD'));
const daysCount = ref(7);
const activeTab = ref<'planificar' | 'programaciones'>('planificar');

// El panel de Configuración es retráctil; se colapsa solo en pantallas chicas (tras montar,
// para no romper la hidratación SSR) y queda abierto en escritorio.
const configOpen = ref(true);
onMounted(() => {
    if (window.innerWidth < 1024) configOpen.value = false;
});

const form = useForm({
    cafe_id: '',
    structure_id: null as number | null,
    meal_type: null as string | null,
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

// serviceable_id (fila de la tabla `serviceables`, id del pivot café↔servicio) — mismo dato que usa
// structure-menu/MenuDisplay.vue para relacionar una Structure con un café+servicio específico.
const selectedServiceableId = computed(() => {
    if (!selectedServiceId.value || !availableServices.value) return null;
    const srv = availableServices.value.find((s: any) => String(s.id) === String(selectedServiceId.value));
    return srv?.pivot?.id ?? null;
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
// Percentage of diners that take each dish (per date + meal + structure row). 100 = every
// diner of that day's service. Effective rations of a dish = portions * percentage / 100.
const percentagesGrid = ref<Record<string, number>>({});

const clampPct = (val: unknown): number => {
    const n = Number(val);
    if (!Number.isFinite(n)) return 100;
    return Math.min(100, Math.max(0, Math.round(n * 100) / 100));
};

const effectiveRations = (date: string, meal: string, structId: number | string): number => {
    const base = Number(portionsGrid.value[`${date}_${meal}`]) || 0;
    const pct = clampPct(percentagesGrid.value[`${date}_${meal}_${structId}`] ?? 100);
    return Math.round((base * pct) / 100);
};

// Copy the first day's percentage of a dish row to every day of the active service
const replicatePercentages = (meal: string, structId: number | string) => {
    if (dates.value.length === 0) return;
    const firstKey = `${dates.value[0]}_${meal}_${structId}`;
    const firstVal = clampPct(percentagesGrid.value[firstKey] ?? 100);
    dates.value.forEach((date) => {
        percentagesGrid.value[`${date}_${meal}_${structId}`] = firstVal;
    });
};
const localMenuStructure = ref<MenuStructure[]>(JSON.parse(JSON.stringify(props.menu_structure)));

const dates = computed(() => {
    return Array.from({ length: daysCount.value }, (_, i) => dayjs(form.start_date).add(i, 'days').format('YYYY-MM-DD'));
});

// Initialize items and portions, preserving values already entered for dates that remain
const initializeGrid = () => {
    const oldPortions = { ...portionsGrid.value };
    const oldItems = { ...itemsGrid.value };
    const oldPercentages = { ...percentagesGrid.value };
    portionsGrid.value = {};
    itemsGrid.value = {};
    percentagesGrid.value = {};

    dates.value.forEach((date) => {
        meals.forEach((meal) => {
            const portKey = `${date}_${meal}`;
            portionsGrid.value[portKey] = oldPortions[portKey] !== undefined ? oldPortions[portKey] : 0;

            const structureForMeal = localMenuStructure.value.filter((s) => s.meal_type === meal);
            structureForMeal.forEach((s) => {
                const itemKey = `${date}_${meal}_${s.id}`;
                itemsGrid.value[itemKey] = oldItems[itemKey] || '';
                percentagesGrid.value[itemKey] = oldPercentages[itemKey] !== undefined ? oldPercentages[itemKey] : 100;
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

const submit = async () => {
    if (!form.cafe_id) {
        Swal.fire({
            icon: 'warning',
            title: 'Falta el comedor',
            text: 'Seleccione mina, unidad y café/comedor en el panel de configuración antes de guardar.',
            confirmButtonColor: '#FF5A1F',
        });
        return;
    }

    // Sync local grid to form arrays before posting
    form.end_date = dayjs(form.start_date)
        .add(daysCount.value - 1, 'days')
        .format('YYYY-MM-DD');
    form.meal_type = (activeMealType.value as string | null) ?? null;
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
            percentage: clampPct(percentagesGrid.value[key] ?? 100),
        });
    }

    if (form.items.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Matriz vacía',
            text: 'No hay categorías en la estructura del menú para planificar. Cargue un ciclo de menú o configure la estructura primero.',
            confirmButtonColor: '#FF5A1F',
        });
        return;
    }

    const assignedDishes = form.items.filter((i) => i.dish_id).length;
    if (assignedDishes === 0) {
        const result = await Swal.fire({
            icon: 'question',
            title: 'Sin platos asignados',
            text: 'No ha asignado ningún plato en la matriz. ¿Desea guardar la planificación de todas formas?',
            showCancelButton: true,
            confirmButtonText: 'Sí, guardar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#FF5A1F',
        });
        if (!result.isConfirmed) return;
    }

    form.post(route('planning.store'), {
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: 'Planificación guardada',
                text: `Se guardó el plan con ${assignedDishes} platos asignados.`,
                timer: 2000,
                showConfirmButton: false,
            });
        },
        onError: (errors) => {
            const msg = Object.values(errors).flat().join('\n') || 'Revise los datos ingresados.';
            Swal.fire({
                icon: 'error',
                title: 'No se pudo guardar',
                text: msg,
                confirmButtonColor: '#FF5A1F',
            });
        },
    });
};

// Copy the first day's portions to every day of the active service
const replicatePortions = (meal: string) => {
    if (dates.value.length === 0) return;
    const firstVal = portionsGrid.value[`${dates.value[0]}_${meal}`] || 0;
    dates.value.forEach((date) => {
        portionsGrid.value[`${date}_${meal}`] = firstVal;
    });
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

const generatePO = async (program: WeeklyProgram) => {
    // El quebrado resuelve la receta de cada plato por nivel (igual que los PDF del módulo),
    // así que se pide el nivel antes de generar la orden.
    const levelId = await promptForLevel(
        '¿Generar Quebrado (PO)?',
        `Se generará la orden de compra para "${program.cafe?.name}" del ${dayjs(program.start_date).format('DD/MM')} al ${dayjs(program.end_date).format('DD/MM')}. Elija el nivel de receta:`,
    );
    if (!levelId) return;

    router.post(route('planning.generate-po', program.id), { level_id: levelId });
};

// Un solo quebrado (PO) que consolida los insumos de todas las programaciones marcadas.
const generatePOForSelection = async () => {
    const ids = requireSelection();
    if (!ids) return;
    const levelId = await promptForLevel(
        '¿Generar Quebrado (PO) consolidado?',
        `Se generará una sola orden de compra con los insumos de las ${ids.length} programación${ids.length === 1 ? '' : 'es'} seleccionada${ids.length === 1 ? '' : 's'}. Elija el nivel de receta:`,
    );
    if (!levelId) return;

    router.post(route('planning.generate-po-multiple'), { program_ids: ids, level_id: levelId });
};

// The planning grid never records which recipe "nivel" (Master/Staff/Empleado/Obrero) a dish
// was assigned under — only dish_id — so it's asked here and applied uniformly to every dish
// in the week's PDF. Shared by both PDF reports below.
const promptForLevel = async (title: string, prompt: string): Promise<string | null> => {
    if (!props.levels || props.levels.length === 0) {
        Swal.fire('Atención', 'No hay niveles de receta configurados en el sistema.', 'warning');
        return null;
    }

    const { value: levelId } = await Swal.fire({
        title,
        html:
            `<p class="mb-3 text-left text-sm text-gray-600">${prompt}</p>` +
            '<select id="swal-level" class="swal2-select" style="display:block;width:100%;">' +
            props.levels.map((l: any) => `<option value="${l.id}">${l.name}</option>`).join('') +
            '</select>',
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Generar PDF',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#FF5A1F',
        preConfirm: () => (document.getElementById('swal-level') as HTMLSelectElement)?.value,
    });

    return levelId || null;
};

// ── Programaciones guardadas: selección múltiple + filtros ───────────────────────────────
// Todos los reportes (no solo el Menú Semanal) se generan sobre las programaciones marcadas
// en la tabla, enviando program_ids[] al backend.
const selectedProgramIds = ref<number[]>([]);

// La tabla se pide paginada a planning/programs (con miles de programaciones, traerlas todas
// en la carga de la pantalla la volvía lenta). Los filtros se aplican en el servidor y las
// opciones de Mina/Unidad/Comedor salen del árbol `mines` que ya viene en las props.
const programFilters = ref({
    mine: '',
    unit: '',
    cafe: '',
    savedFrom: '',
    savedTo: '',
});

const programRow = (p: any) => ({
    id: p.id as number,
    cafe: p.cafe?.name ?? '—',
    unit: p.cafe?.unit?.name ?? '—',
    mine: p.cafe?.unit?.mine?.name ?? '—',
    service: (p.service ?? null) as string | null,
    savedAt: p.created_at as string | null,
});

const byName = (a: any, b: any) => String(a.name).localeCompare(String(b.name));
const programMineOptions = computed(() => [...(props.mines ?? [])].sort(byName));
const programUnitOptions = computed(() =>
    programMineOptions.value
        .filter((m: any) => !programFilters.value.mine || String(m.id) === programFilters.value.mine)
        .flatMap((m: any) => m.units ?? [])
        .sort(byName),
);
const programCafeOptions = computed(() =>
    programUnitOptions.value
        .filter((u: any) => !programFilters.value.unit || String(u.id) === programFilters.value.unit)
        .flatMap((u: any) => u.cafes ?? [])
        .sort(byName),
);

// Al cambiar la mina/unidad, se limpian los filtros hijos que ya no pertenecen a ella.
watch(
    () => programFilters.value.mine,
    () => {
        if (programFilters.value.unit && !programUnitOptions.value.some((u: any) => String(u.id) === programFilters.value.unit)) {
            programFilters.value.unit = '';
        }
    },
);
watch(
    () => programFilters.value.unit,
    () => {
        if (programFilters.value.cafe && !programCafeOptions.value.some((c: any) => String(c.id) === programFilters.value.cafe)) {
            programFilters.value.cafe = '';
        }
    },
);

const programsPage = ref<any[]>([]);
const programsMeta = ref({ current_page: 1, last_page: 1, total: 0, from: 0 as number | null, to: 0 as number | null });
const programsLoading = ref(false);
const programsLoaded = ref(false);
let programsRequest = 0;

const loadPrograms = async (page = 1) => {
    const f = programFilters.value;
    const requestId = ++programsRequest;
    programsLoading.value = true;
    try {
        const { data } = await axios.get(route('planning.programs'), {
            params: {
                page,
                mine_id: f.mine || undefined,
                unit_id: f.unit || undefined,
                cafe_id: f.cafe || undefined,
                saved_from: f.savedFrom || undefined,
                saved_to: f.savedTo || undefined,
            },
        });
        // Una respuesta vieja (filtros cambiados mientras cargaba) no pisa la más reciente.
        if (requestId !== programsRequest) return;
        programsPage.value = data.data;
        programsMeta.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            total: data.total,
            from: data.from,
            to: data.to,
        };
        programsLoaded.value = true;
    } catch {
        if (requestId === programsRequest) Swal.fire('Error', 'No se pudieron cargar las programaciones guardadas.', 'error');
    } finally {
        if (requestId === programsRequest) programsLoading.value = false;
    }
};

const goToProgramsPage = (page: number) => {
    if (page < 1 || page > programsMeta.value.last_page || page === programsMeta.value.current_page) return;
    loadPrograms(page);
};

// Se carga la primera vez que se abre la pestaña, no en la carga de la pantalla.
watch(activeTab, (tab) => {
    if (tab === 'programaciones' && !programsLoaded.value) loadPrograms();
});

// Tras guardar una planificación Inertia recarga las props: refrescar la tabla si ya se mostró.
watch(
    () => props.programs_total,
    () => {
        if (programsLoaded.value) loadPrograms(programsMeta.value.current_page);
    },
);

// Cambiar un filtro vuelve a la página 1. La selección se descarta para que el contador de la
// barra de acciones no incluya programaciones que ya no coinciden con el filtro.
watch(
    programFilters,
    () => {
        selectedProgramIds.value = [];
        loadPrograms(1);
    },
    { deep: true },
);

const hasProgramFilters = computed(() => Object.values(programFilters.value).some(Boolean));
const clearProgramFilters = () => {
    programFilters.value = { mine: '', unit: '', cafe: '', savedFrom: '', savedTo: '' };
};

// La selección se conserva al cambiar de página, así se pueden combinar programaciones de varias.
const visibleProgramIds = computed<number[]>(() => programsPage.value.map((p: any) => p.id));
const allVisibleSelected = computed(
    () => visibleProgramIds.value.length > 0 && visibleProgramIds.value.every((id) => selectedProgramIds.value.includes(id)),
);
const toggleSelectAllVisible = (checked: boolean) => {
    if (checked) {
        selectedProgramIds.value = [...new Set([...selectedProgramIds.value, ...visibleProgramIds.value])];
    } else {
        selectedProgramIds.value = selectedProgramIds.value.filter((id) => !visibleProgramIds.value.includes(id));
    }
};
const toggleProgram = (id: number, checked: boolean) => {
    if (checked) {
        if (!selectedProgramIds.value.includes(id)) selectedProgramIds.value = [...selectedProgramIds.value, id];
    } else {
        selectedProgramIds.value = selectedProgramIds.value.filter((x) => x !== id);
    }
};

const requireSelection = (): number[] | null => {
    if (!selectedProgramIds.value.length) {
        Swal.fire('Sin selección', 'Marque al menos una programación en la tabla para generar el reporte.', 'info');
        return null;
    }
    return [...selectedProgramIds.value];
};

const openReport = (routeName: string, params: Record<string, unknown>) => {
    window.open(route(routeName, params), '_blank');
};

const generateWeeklyQuebradoPdf = async () => {
    const ids = requireSelection();
    if (!ids) return;
    const levelId = await promptForLevel(
        'Quebrado Semanal (PDF)',
        `Seleccione el nivel de receta a usar para calcular los ingredientes de cada plato (${ids.length} programación/es).`,
    );
    if (levelId) openReport('planning.quebrado-pdf', { program_ids: ids, level_id: levelId });
};

const generateWeeklyRequirementPdf = async () => {
    const ids = requireSelection();
    if (!ids) return;
    const levelId = await promptForLevel(
        'Requerimiento x Producto (PDF)',
        `Seleccione el nivel de receta a usar para consolidar cuánto de cada insumo se necesita (${ids.length} programación/es).`,
    );
    if (levelId) openReport('planning.requerimiento-pdf', { program_ids: ids, level_id: levelId });
};

const generateWeeklyDosificacionPdf = async () => {
    const ids = requireSelection();
    if (!ids) return;
    const levelId = await promptForLevel(
        'Dosificación Nutricional (PDF)',
        `Seleccione el nivel de receta a usar para calcular los valores nutricionales de cada plato (${ids.length} programación/es).`,
    );
    if (levelId) openReport('planning.dosificacion-pdf', { program_ids: ids, level_id: levelId });
};

// Una hoja por programación seleccionada.
const generateWeeklyMenuExcel = () => {
    const ids = requireSelection();
    if (!ids) return;
    openReport('planning.menu-excel', { program_ids: ids });
};

// Prices only exist per (insumo, ciudad, proveedor) — there's no relation from
// Mina/Unidad/Comedor to Ciudad in the system — so the city used to price this order is also
// chosen manually here, same reasoning as the recipe nivel above.
const generateWeeklyPurchaseOrderExcel = async () => {
    const ids = requireSelection();
    if (!ids) return;
    if (!props.levels?.length || !props.cities?.length) {
        Swal.fire('Atención', 'Faltan niveles o ciudades configuradas en el sistema.', 'warning');
        return;
    }

    const { value: formValues } = await Swal.fire({
        title: 'Orden de Pedido Semanal (Excel)',
        html:
            `<p class="mb-3 text-left text-sm text-gray-600">Se consolidará el pedido de ${ids.length} programación/es. Seleccione el nivel de receta y la ciudad de precios.</p>` +
            '<label class="mb-1 block text-left text-xs font-semibold text-gray-500">Nivel de receta</label>' +
            '<select id="swal-level" class="swal2-select" style="display:block;width:100%;margin-bottom:12px;">' +
            props.levels.map((l: any) => `<option value="${l.id}">${l.name}</option>`).join('') +
            '</select>' +
            '<label class="mb-1 block text-left text-xs font-semibold text-gray-500">Ciudad (precios)</label>' +
            '<select id="swal-city" class="swal2-select" style="display:block;width:100%;">' +
            props.cities.map((c: any) => `<option value="${c.id}">${c.name}</option>`).join('') +
            '</select>',
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Generar Excel',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#FF5A1F',
        preConfirm: () => ({
            levelId: (document.getElementById('swal-level') as HTMLSelectElement)?.value,
            cityId: (document.getElementById('swal-city') as HTMLSelectElement)?.value,
        }),
    });

    if (formValues?.levelId && formValues?.cityId) {
        openReport('planning.orden-pedido-excel', { program_ids: ids, level_id: formValues.levelId, city_id: formValues.cityId });
    }
};

// Mismo cálculo que la Orden de Pedido, pero abierto como página interactiva: desglosado por
// comedor de destino y con todos los proveedores registrados (no solo el más barato).
const generatePurchaseReport = async () => {
    const ids = requireSelection();
    if (!ids) return;
    if (!props.levels?.length || !props.cities?.length) {
        Swal.fire('Atención', 'Faltan niveles o ciudades configuradas en el sistema.', 'warning');
        return;
    }

    const { value: formValues } = await Swal.fire({
        title: 'Reporte de Compras Semanales',
        html:
            `<p class="mb-3 text-left text-sm text-gray-600">Se consolidará el requerimiento de ${ids.length} programación/es por comedor de destino, con los precios de todos los proveedores disponibles. Seleccione el nivel de receta y la ciudad de precios.</p>` +
            '<label class="mb-1 block text-left text-xs font-semibold text-gray-500">Nivel de receta</label>' +
            '<select id="swal-report-level" class="swal2-select" style="display:block;width:100%;margin-bottom:12px;">' +
            props.levels.map((l: any) => `<option value="${l.id}">${l.name}</option>`).join('') +
            '</select>' +
            '<label class="mb-1 block text-left text-xs font-semibold text-gray-500">Ciudad (precios)</label>' +
            '<select id="swal-report-city" class="swal2-select" style="display:block;width:100%;">' +
            props.cities.map((c: any) => `<option value="${c.id}">${c.name}</option>`).join('') +
            '</select>',
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Generar Reporte',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#FF5A1F',
        preConfirm: () => ({
            levelId: (document.getElementById('swal-report-level') as HTMLSelectElement)?.value,
            cityId: (document.getElementById('swal-report-city') as HTMLSelectElement)?.value,
        }),
    });

    if (formValues?.levelId && formValues?.cityId) {
        router.get(route('planning.reporte-compras'), {
            program_ids: ids,
            level_id: formValues.levelId,
            city_id: formValues.cityId,
        });
    }
};

// Dishes are fetched on demand per category (instead of shipping the entire catalog on page
// load) and cached here, keyed by dish_category_id.
const dishesByCategory = ref<Record<number, { id: number; name: string }[]>>({});
const knownDishNames = ref<Record<number, string>>({});
const loadingDishCategories = new Set<number>();

const fetchDishesForCategory = async (categoryId: number | null | undefined) => {
    if (!categoryId || dishesByCategory.value[categoryId] || loadingDishCategories.has(categoryId)) return;
    loadingDishCategories.add(categoryId);
    try {
        const response = await axios.get('/dishes/search', { params: { category_id: categoryId } });
        const list = (response.data || []).map((d: any) => ({ id: d.id, name: d.name }));
        dishesByCategory.value[categoryId] = list;
        list.forEach((d: { id: number; name: string }) => {
            knownDishNames.value[d.id] = d.name;
        });
    } catch (err) {
        console.error('Error fetching dishes for category', categoryId, err);
    } finally {
        loadingDishCategories.delete(categoryId);
    }
};

watch(
    localMenuStructure,
    (structs) => {
        const categoryIds = new Set(structs.map((s) => s.dish_category_id).filter((id): id is number => !!id));
        categoryIds.forEach((id) => fetchDishesForCategory(id));
    },
    { immediate: true, deep: true },
);

const getDishesForCell = (date: string, meal: string, struct: any) => {
    const categoryId = struct.dish_category_id;
    const filtered = dishesByCategory.value[categoryId] || [];

    const assignedIdStr = itemsGrid.value[`${date}_${meal}_${struct.id}`];
    if (assignedIdStr) {
        const assignedId = parseInt(assignedIdStr);
        if (!filtered.some((d) => d.id === assignedId)) {
            const name = knownDishNames.value[assignedId];
            if (name) {
                return [...filtered, { id: assignedId, name }];
            }
        }
    }
    return filtered;
};

// --- Ciclos de menú ---------------------------------------------------------
// La matriz se arma a partir de un ciclo del comedor+servicio elegidos: se piden solo los
// ciclos de ese serviceable (antes llegaban los ~8 mil ciclos de todos los comedores).
interface CycleOption {
    id: number;
    name: string;
    days: number;
    legacy_cycle_number: number | null;
    is_current: boolean | number;
    updated_at?: string;
}

const cycleOptions = ref<CycleOption[]>([]);
const cyclesLoading = ref(false);
const cycleSearch = ref('');
const loadedCycleId = ref<number | null>(null);
const loadingCycleId = ref<number | null>(null);
const cyclesByServiceable = new Map<number, CycleOption[]>();

const cycleLabel = (cycle: CycleOption) => {
    if (cycle.legacy_cycle_number != null) return `Ciclo ${cycle.legacy_cycle_number}`;
    const match = cycle.name.match(/ciclo\s*\d+/i);
    return match ? match[0].replace(/^c/, 'C') : cycle.name;
};

const fetchCycleOptions = async (serviceableId: number | null) => {
    loadedCycleId.value = null;
    cycleSearch.value = '';
    if (!serviceableId) {
        cycleOptions.value = [];
        return;
    }
    const cached = cyclesByServiceable.get(serviceableId);
    if (cached) {
        cycleOptions.value = cached;
        return;
    }
    cyclesLoading.value = true;
    try {
        const { data } = await axios.get(route('planning.cycles.index'), { params: { serviceable_id: serviceableId } });
        cyclesByServiceable.set(serviceableId, data);
        // Si el usuario cambió de servicio mientras cargaba, no pisar la lista nueva.
        if (selectedServiceableId.value === serviceableId) cycleOptions.value = data;
    } catch {
        cycleOptions.value = [];
        Swal.fire('Error', 'No se pudieron cargar los ciclos de este servicio.', 'error');
    } finally {
        cyclesLoading.value = false;
    }
};

watch(selectedServiceableId, (id) => fetchCycleOptions(id), { immediate: true });

const visibleCycles = computed(() => {
    const q = cycleSearch.value.trim().toLowerCase();
    if (!q) return cycleOptions.value;
    return cycleOptions.value.filter((c) => cycleLabel(c).toLowerCase().includes(q) || c.name.toLowerCase().includes(q));
});

const loadedCycle = computed(() => cycleOptions.value.find((c) => c.id === loadedCycleId.value) ?? null);

const selectedCafeName = computed(() => availableCafes.value.find((c: any) => String(c.id) === String(form.cafe_id))?.name ?? 'Este comedor');

// Resumen visible con el panel colapsado, para no tener que abrirlo para saber qué se planifica.
const configSummary = computed(() => {
    if (configOpen.value) return 'Ocultar panel';
    if (!form.cafe_id) return 'Sin comedor seleccionado';
    return [selectedCafeName.value, activeMealType.value, loadedCycle.value ? cycleLabel(loadedCycle.value) : null].filter(Boolean).join(' · ');
});

const cycleDataCache = new Map<number, any[]>();

const fetchCycleData = async (cycleId: number): Promise<any[] | null> => {
    const cached = cycleDataCache.get(cycleId);
    if (cached) return cached;

    try {
        const { data } = await axios.get(`/planning/cycles/${cycleId}`);
        const rows = Array.isArray(data?.cycle_data) ? data.cycle_data : [];
        cycleDataCache.set(cycleId, rows);
        return rows;
    } catch {
        Swal.fire('Error', 'No se pudo cargar el detalle del ciclo.', 'error');
        return null;
    }
};

const loadMenuCycle = async (cycle: CycleOption) => {
    const mealType = activeMealType.value;
    if (!mealType) return;

    const hasDishes = localMenuStructure.value
        .filter((s) => s.meal_type === mealType)
        .some((s) => dates.value.some((date) => itemsGrid.value[`${date}_${mealType}_${s.id}`]));

    // Solo se pide confirmación cuando hay platos que se van a perder.
    if (hasDishes) {
        const result = await Swal.fire({
            title: `¿Cargar ${cycleLabel(cycle)}?`,
            text: `Se reemplazarán los platos actuales de ${mealType}.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, cargar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#FF5A1F',
        });
        if (!result.isConfirmed) return;
    }

    loadingCycleId.value = cycle.id;
    const cycleData = await fetchCycleData(cycle.id);
    loadingCycleId.value = null;
    if (!cycleData) return;

    // La planificación cubre al menos los días del ciclo.
    if (daysCount.value < cycle.days) {
        daysCount.value = Math.min(cycle.days, 31);
        form.end_date = dayjs(form.start_date)
            .add(daysCount.value - 1, 'days')
            .format('YYYY-MM-DD');
    }

    // 1. Las filas de la matriz son las del ciclo, con sus techos de costo (L.Inf/L.Sup).
    const otherMeals = localMenuStructure.value.filter((s) => s.meal_type !== mealType);
    const newMealStructure = cycleData.map((cycleRow: any, index: number) => {
        const categoryObj = props.dish_categories?.find((c) => c.id === cycleRow.dishCategoryId);
        return {
            id: cycleRow.id || 20000 + index, // ID único temporal
            meal_type: mealType,
            dish_category_id: cycleRow.dishCategoryId,
            dish_category: categoryObj || { id: cycleRow.dishCategoryId, name: cycleRow.category },
            sort_order: index,
            total_cost: cycleRow.costValue ?? null,
            total_cost_superior: cycleRow.costValueMax ?? null,
        } as MenuStructure;
    });
    localMenuStructure.value = [...otherMeals, ...newMealStructure];

    // 2. Inicializar la cuadrícula local preservando valores de otras comidas
    const oldPortions = { ...portionsGrid.value };
    const oldItems = { ...itemsGrid.value };
    const oldPercentages = { ...percentagesGrid.value };

    portionsGrid.value = {};
    itemsGrid.value = {};
    percentagesGrid.value = {};

    dates.value.forEach((date) => {
        meals.forEach((meal) => {
            const portKey = `${date}_${meal}`;
            portionsGrid.value[portKey] = oldPortions[portKey] !== undefined ? oldPortions[portKey] : 0;

            const structureForMeal = localMenuStructure.value.filter((s) => s.meal_type === meal);
            structureForMeal.forEach((s) => {
                const itemKey = `${date}_${meal}_${s.id}`;
                if (meal === mealType) {
                    itemsGrid.value[itemKey] = '';
                    percentagesGrid.value[itemKey] = 100;
                } else {
                    itemsGrid.value[itemKey] = oldItems[itemKey] || '';
                    percentagesGrid.value[itemKey] = oldPercentages[itemKey] !== undefined ? oldPercentages[itemKey] : 100;
                }
            });
        });
    });

    // 3. Poblar la cuadrícula con los platos del ciclo
    let assignedCount = 0;
    cycleData.forEach((cycleRow: any, index: number) => {
        const tempId = cycleRow.id || 20000 + index;
        for (let dayNum = 1; dayNum <= cycle.days; dayNum++) {
            const dateIndex = dayNum - 1;
            if (dateIndex < dates.value.length) {
                const date = dates.value[dateIndex];
                const dayData = cycleRow.days?.[dayNum];
                if (dayData && dayData.dish_id) {
                    const itemKey = `${date}_${mealType}_${tempId}`;
                    itemsGrid.value[itemKey] = String(dayData.dish_id);
                    if (dayData.dish_name) {
                        knownDishNames.value[dayData.dish_id] = dayData.dish_name;
                    }
                    assignedCount++;
                }
            }
        }
    });

    loadedCycleId.value = cycle.id;

    Swal.fire({
        icon: 'success',
        title: `${cycleLabel(cycle)} cargado`,
        text: `${newMealStructure.length} categorías y ${assignedCount} platos en ${mealType}.`,
        timer: 1800,
        showConfirmButton: false,
    });
};
</script>

<template>
    <Head title="Planificación Semanal" />
    <AppLayout>
        <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Planificación Semanal y Orden de Servicio</h1>
                    <p class="mt-0.5 text-xs text-slate-500">Gestione y programe platos para múltiples días con total flexibilidad.</p>
                </div>
                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                    <Link :href="route('purchase_orders.index')" class="w-full sm:w-auto">
                        <Button variant="outline" class="w-full rounded-xl sm:w-auto">Ver Órdenes de Compra</Button>
                    </Link>
                    <Button
                        @click="submit"
                        :disabled="form.processing"
                        class="w-full rounded-xl bg-[#FF5A1F] text-white shadow-sm shadow-orange-500/20 hover:bg-[#e04a17] sm:w-auto"
                    >
                        {{ form.processing ? 'Guardando...' : 'Guardar Planificación' }}
                    </Button>
                </div>
            </div>

            <Tabs v-model="activeTab">
                <TabsList class="grid w-full grid-cols-2 bg-orange-50 p-1 sm:inline-flex sm:w-auto">
                    <TabsTrigger
                        value="planificar"
                        class="rounded-lg data-[state=active]:bg-white data-[state=active]:font-semibold data-[state=active]:text-[#FF5A1F] data-[state=active]:shadow-sm"
                    >
                        Planificar
                    </TabsTrigger>
                    <TabsTrigger
                        value="programaciones"
                        class="rounded-lg data-[state=active]:bg-white data-[state=active]:font-semibold data-[state=active]:text-[#FF5A1F] data-[state=active]:shadow-sm"
                    >
                        <span class="truncate">Programaciones Guardadas</span>
                        <span
                            class="ml-1.5 rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                            :class="activeTab === 'programaciones' ? 'bg-[#FF5A1F]/15 text-[#FF5A1F]' : 'bg-slate-200 text-slate-600'"
                            >{{ programs_total }}</span
                        >
                    </TabsTrigger>
                </TabsList>
            </Tabs>

            <div v-if="activeTab === 'planificar'" class="grid grid-cols-1 items-start gap-4 sm:gap-6 lg:grid-cols-4">
                <!-- Sidebar de Configuración (retráctil) -->
                <Card
                    class="gap-0 overflow-hidden rounded-2xl border border-orange-100/70 bg-white py-0 shadow-sm"
                    :class="configOpen ? 'lg:col-span-1' : 'lg:col-span-4'"
                >
                    <button
                        type="button"
                        @click="configOpen = !configOpen"
                        :aria-expanded="configOpen"
                        class="flex w-full items-center justify-between gap-2 bg-gradient-to-r from-orange-50 to-white px-4 py-3.5 text-left transition-colors hover:from-orange-100/70"
                    >
                        <span class="flex min-w-0 items-center gap-2.5">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#FF5A1F] text-white shadow-sm shadow-orange-500/30">
                                <Settings2 class="h-[18px] w-[18px]" />
                            </span>
                            <span class="flex min-w-0 flex-col">
                                <span class="text-base font-bold text-slate-800">Configuración</span>
                                <span class="truncate text-[11px] text-slate-400">{{ configSummary }}</span>
                            </span>
                        </span>
                        <ChevronDown class="h-5 w-5 shrink-0 text-[#FF5A1F] transition-transform" :class="configOpen ? '' : '-rotate-90'" />
                    </button>
                    <CardContent v-show="configOpen" class="flex flex-col gap-5 border-t border-orange-100/70 p-4 sm:p-5">
                        <!-- Paso 1: comedor -->
                        <section class="flex flex-col gap-3">
                            <h3 class="flex items-center gap-2 text-xs font-bold tracking-wide text-slate-500 uppercase">
                                <span
                                    class="grid h-5 w-5 place-items-center rounded-full text-[10px]"
                                    :class="form.cafe_id ? 'bg-[#FF5A1F] text-white' : 'bg-slate-100 text-slate-500'"
                                >
                                    <Check v-if="form.cafe_id" class="h-3 w-3" />
                                    <template v-else>1</template>
                                </span>
                                Comedor
                            </h3>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-3 lg:grid-cols-1">
                                <div class="flex flex-col gap-1.5">
                                    <label class="flex items-center gap-1.5 text-xs font-medium text-slate-600">
                                        <Mountain class="h-3.5 w-3.5 text-[#FF5A1F]" /> Mina
                                    </label>
                                    <Select v-model="selectedMineId" @update:modelValue="handleMineChange">
                                        <SelectTrigger class="h-10 w-full rounded-xl border-slate-200 focus:ring-[#FF5A1F]">
                                            <SelectValue placeholder="Seleccionar mina" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="mine in mines" :key="mine.id" :value="mine.id.toString()">
                                                {{ mine.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label class="flex items-center gap-1.5 text-xs font-medium text-slate-600">
                                        <Building2 class="h-3.5 w-3.5 text-[#FF5A1F]" /> Unidad
                                    </label>
                                    <Select v-model="selectedUnitId" @update:modelValue="handleUnitChange" :disabled="!selectedMineId">
                                        <SelectTrigger class="h-10 w-full rounded-xl border-slate-200 focus:ring-[#FF5A1F]">
                                            <SelectValue placeholder="Seleccionar unidad" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="unit in availableUnits" :key="unit.id" :value="unit.id.toString()">
                                                {{ unit.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label class="flex items-center gap-1.5 text-xs font-medium text-slate-600">
                                        <Coffee class="h-3.5 w-3.5 text-[#FF5A1F]" /> Café / Comedor
                                    </label>
                                    <Select v-model="form.cafe_id" :disabled="!selectedUnitId">
                                        <SelectTrigger class="h-10 w-full rounded-xl border-slate-200 focus:ring-[#FF5A1F]">
                                            <SelectValue placeholder="Seleccionar comedor" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="cafe in availableCafes" :key="cafe.id" :value="cafe.id.toString()">
                                                {{ cafe.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </section>

                        <!-- Paso 2: servicio y periodo -->
                        <section class="flex flex-col gap-3 border-t border-slate-100 pt-4">
                            <h3 class="flex items-center gap-2 text-xs font-bold tracking-wide text-slate-500 uppercase">
                                <span
                                    class="grid h-5 w-5 place-items-center rounded-full text-[10px]"
                                    :class="activeMealType ? 'bg-[#FF5A1F] text-white' : 'bg-slate-100 text-slate-500'"
                                >
                                    <Check v-if="activeMealType" class="h-3 w-3" />
                                    <template v-else>2</template>
                                </span>
                                Servicio y periodo
                            </h3>
                            <div class="flex flex-col gap-1.5">
                                <label class="flex items-center gap-1.5 text-xs font-medium text-slate-600">
                                    <ConciergeBell class="h-3.5 w-3.5 text-[#FF5A1F]" /> Servicio
                                </label>
                                <div v-if="availableServices.length" class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="service in availableServices"
                                        :key="service.id"
                                        type="button"
                                        @click="selectedServiceId = String(service.id)"
                                        class="rounded-full border px-3 py-1.5 text-xs font-semibold capitalize transition-colors"
                                        :class="
                                            String(service.id) === selectedServiceId
                                                ? 'border-[#FF5A1F] bg-[#FF5A1F] text-white shadow-sm shadow-orange-500/20'
                                                : 'border-slate-200 bg-white text-slate-600 hover:border-[#FF5A1F] hover:text-[#FF5A1F]'
                                        "
                                    >
                                        {{ service.name.toLowerCase() }}
                                    </button>
                                </div>
                                <p v-else class="rounded-xl border border-dashed border-slate-200 px-3 py-2 text-xs text-slate-400">
                                    {{ form.cafe_id ? 'Este comedor no tiene servicios asignados.' : 'Elija un comedor para ver sus servicios.' }}
                                </p>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="flex flex-col gap-1.5">
                                    <label class="flex items-center gap-1.5 text-xs font-medium text-slate-600">
                                        <CalendarDays class="h-3.5 w-3.5 text-[#FF5A1F]" /> Inicio
                                    </label>
                                    <Input
                                        type="date"
                                        v-model="form.start_date"
                                        @change="handleDateChange"
                                        class="h-10 rounded-xl border-slate-200 px-2 text-sm focus-visible:ring-[#FF5A1F]"
                                    />
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label class="flex items-center gap-1.5 text-xs font-medium text-slate-600">
                                        <Clock class="h-3.5 w-3.5 text-[#FF5A1F]" /> Días
                                    </label>
                                    <div class="flex h-10 items-center rounded-xl border border-slate-200">
                                        <button
                                            type="button"
                                            class="grid h-full w-8 shrink-0 place-items-center text-slate-500 hover:text-[#FF5A1F] disabled:opacity-40"
                                            @click="decreaseDays"
                                            :disabled="daysCount <= 1"
                                            aria-label="Menos días"
                                        >
                                            <Minus class="h-3.5 w-3.5" />
                                        </button>
                                        <input
                                            type="number"
                                            v-model.number="daysCount"
                                            min="1"
                                            max="31"
                                            class="h-full w-full min-w-0 [appearance:textfield] bg-transparent text-center text-sm font-bold focus:outline-none [&::-webkit-inner-spin-button]:appearance-none"
                                            @change="handleDaysCountChange"
                                        />
                                        <button
                                            type="button"
                                            class="grid h-full w-8 shrink-0 place-items-center text-slate-500 hover:text-[#FF5A1F] disabled:opacity-40"
                                            @click="increaseDays"
                                            :disabled="daysCount >= 31"
                                            aria-label="Más días"
                                        >
                                            <Plus class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <p class="text-[11px] text-slate-400">
                                Del <span class="font-semibold text-slate-600">{{ dayjs(dates[0]).format('ddd DD/MM') }}</span> al
                                <span class="font-semibold text-slate-600">{{ dayjs(dates[dates.length - 1]).format('ddd DD/MM/YYYY') }}</span>
                            </p>
                        </section>

                        <!-- Paso 3: ciclo de menú (filtrado por comedor + servicio) -->
                        <section class="flex flex-col gap-3 border-t border-slate-100 pt-4">
                            <h3 class="flex items-center gap-2 text-xs font-bold tracking-wide text-slate-500 uppercase">
                                <span
                                    class="grid h-5 w-5 place-items-center rounded-full text-[10px]"
                                    :class="loadedCycle ? 'bg-[#FF5A1F] text-white' : 'bg-slate-100 text-slate-500'"
                                >
                                    <Check v-if="loadedCycle" class="h-3 w-3" />
                                    <template v-else>3</template>
                                </span>
                                Ciclo de menú
                                <span
                                    v-if="cycleOptions.length"
                                    class="ml-auto rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-500 normal-case"
                                >
                                    {{ cycleOptions.length }} disponibles
                                </span>
                            </h3>

                            <p
                                v-if="!selectedServiceableId"
                                class="rounded-xl border border-dashed border-slate-200 px-3 py-4 text-center text-xs text-slate-400"
                            >
                                Elija comedor y servicio para ver sus ciclos.
                            </p>
                            <div v-else-if="cyclesLoading" class="flex flex-col gap-1.5">
                                <div v-for="n in 4" :key="n" class="h-11 animate-pulse rounded-xl bg-slate-100"></div>
                            </div>
                            <p
                                v-else-if="!cycleOptions.length"
                                class="rounded-xl border border-dashed border-slate-200 px-3 py-4 text-center text-xs text-slate-400"
                            >
                                {{ selectedCafeName }} no tiene ciclos de {{ activeMealType?.toLowerCase() }}. Créelos en Ciclos de Menú.
                            </p>
                            <template v-else>
                                <div v-if="cycleOptions.length > 6" class="relative">
                                    <Search class="absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                                    <Input
                                        v-model="cycleSearch"
                                        placeholder="Buscar ciclo (ej. 12)"
                                        class="h-9 rounded-xl border-slate-200 pl-8 text-xs focus-visible:ring-[#FF5A1F]"
                                    />
                                </div>
                                <div class="-mx-1 flex max-h-72 flex-col gap-1 overflow-y-auto px-1">
                                    <button
                                        v-for="cycle in visibleCycles"
                                        :key="cycle.id"
                                        type="button"
                                        :disabled="loadingCycleId !== null"
                                        @click="loadMenuCycle(cycle)"
                                        :title="cycle.name"
                                        class="group flex items-center justify-between gap-2 rounded-xl border px-3 py-2 text-left transition-colors disabled:cursor-wait"
                                        :class="
                                            loadedCycleId === cycle.id
                                                ? 'border-[#FF5A1F] bg-orange-50'
                                                : 'border-slate-100 bg-white hover:border-orange-200 hover:bg-orange-50/50'
                                        "
                                    >
                                        <span class="flex min-w-0 flex-col">
                                            <span
                                                class="text-sm font-semibold"
                                                :class="loadedCycleId === cycle.id ? 'text-[#FF5A1F]' : 'text-slate-700'"
                                            >
                                                {{ cycleLabel(cycle) }}
                                            </span>
                                            <span class="text-[11px] text-slate-400">{{ cycle.days }} días</span>
                                        </span>
                                        <span class="flex shrink-0 items-center gap-1.5">
                                            <span
                                                v-if="cycle.is_current"
                                                class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-600"
                                                >Vigente</span
                                            >
                                            <Loader2 v-if="loadingCycleId === cycle.id" class="h-4 w-4 animate-spin text-[#FF5A1F]" />
                                            <Check v-else-if="loadedCycleId === cycle.id" class="h-4 w-4 text-[#FF5A1F]" />
                                            <span
                                                v-else
                                                class="text-[11px] font-semibold text-[#FF5A1F] opacity-0 transition-opacity group-hover:opacity-100"
                                                >Cargar</span
                                            >
                                        </span>
                                    </button>
                                    <p v-if="!visibleCycles.length" class="py-3 text-center text-xs text-slate-400">
                                        Sin ciclos que coincidan con "{{ cycleSearch.trim() }}".
                                    </p>
                                </div>
                            </template>
                        </section>
                    </CardContent>
                </Card>

                <!-- Área principal de Planificación -->
                <div class="flex flex-col gap-4 sm:gap-6" :class="configOpen ? 'lg:col-span-3' : 'lg:col-span-4'">
                    <!-- Cabecera de la Matriz y Acciones de Importación -->
                    <div
                        class="flex flex-col gap-3 rounded-2xl border border-l-4 border-slate-100 border-l-[#FF5A1F] bg-white p-4 shadow-sm sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:px-6"
                    >
                        <div class="flex flex-col">
                            <h2 class="text-base font-bold text-slate-800 sm:text-lg">Matriz de Programación</h2>
                            <p class="text-xs text-slate-500">Asigne platos, ajuste el % de comensales y defina raciones por día</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <input type="file" ref="fileInput" class="hidden" accept=".xlsx,.xls,.csv" @change="handleFileImport" />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="triggerImport"
                                :disabled="importForm.processing"
                                class="h-9 flex-1 rounded-xl border-dashed border-slate-200 text-xs text-slate-600 hover:bg-slate-50 sm:flex-none"
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
                                class="h-9 flex-1 rounded-xl border-dashed border-slate-200 text-xs text-slate-600 hover:bg-slate-50 sm:flex-none"
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
                                <TableRow class="bg-orange-50 hover:bg-orange-50">
                                    <TableHead
                                        class="sticky left-0 z-20 w-[120px] bg-orange-50 font-semibold text-slate-700 shadow-[1px_0_0_0_rgb(254,215,170)] sm:w-[150px]"
                                        >Comida / Día</TableHead
                                    >
                                    <TableHead
                                        v-for="date in dates"
                                        :key="date"
                                        class="min-w-[140px] border-l border-orange-100/70 text-center font-semibold text-slate-700 sm:min-w-[160px]"
                                    >
                                        <div class="text-xs font-bold text-slate-800 capitalize">{{ dayjs(date).format('dddd') }}</div>
                                        <div class="mt-0.5 text-[10px] font-semibold text-[#FF5A1F]/80">{{ dayjs(date).format('DD/MM') }}</div>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <template v-for="meal in [activeMealType].filter(Boolean)" :key="meal">
                                    <TableRow class="bg-orange-50/50 hover:bg-orange-50/50">
                                        <TableCell
                                            class="sticky left-0 z-10 bg-[#fef2ec] font-bold text-slate-700 shadow-[1px_0_0_0_rgb(254,215,170)]"
                                        >
                                            <div class="flex flex-col gap-1">
                                                <span class="text-xs sm:text-sm">Raciones del Servicio</span>
                                                <button
                                                    type="button"
                                                    @click="replicatePortions(meal)"
                                                    class="w-fit rounded-md border border-dashed border-slate-300 px-1.5 py-0.5 text-[9px] font-bold tracking-wide text-slate-500 uppercase transition-colors hover:border-[#FF5A1F] hover:text-[#FF5A1F]"
                                                    title="Copia las raciones del primer día a todos los días"
                                                >
                                                    Replicar 1er día →
                                                </button>
                                            </div>
                                        </TableCell>
                                        <TableCell
                                            v-for="date in dates"
                                            :key="date"
                                            class="min-w-[140px] border-l border-slate-100/55 p-2 sm:min-w-[160px]"
                                        >
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
                                        <TableCell
                                            class="sticky left-0 z-10 bg-white pl-3 text-xs font-medium text-slate-600 shadow-[1px_0_0_0_rgb(226,232,240)] sm:pl-6"
                                        >
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
                                            <p v-if="struct.total_cost != null" class="mt-1 text-[10px] font-normal text-slate-400">
                                                L.Inf S/ {{ Number(struct.total_cost).toFixed(2) }} · L.Sup S/
                                                {{ Number(struct.total_cost_superior).toFixed(2) }}
                                            </p>
                                            <button
                                                type="button"
                                                @click="replicatePercentages(meal, struct.id)"
                                                class="mt-1.5 w-fit rounded-md border border-dashed border-slate-300 px-1.5 py-0.5 text-[9px] font-bold tracking-wide text-slate-500 uppercase transition-colors hover:border-[#FF5A1F] hover:text-[#FF5A1F]"
                                                title="Copia el % del primer día a todos los días de esta fila"
                                            >
                                                Replicar % 1er día →
                                            </button>
                                        </TableCell>
                                        <TableCell
                                            v-for="date in dates"
                                            :key="date"
                                            class="min-w-[150px] border-l border-slate-100/55 p-2 align-top sm:min-w-[184px]"
                                        >
                                            <div class="flex flex-col gap-1.5">
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
                                                <div
                                                    v-if="itemsGrid[`${date}_${meal}_${struct.id}`]"
                                                    class="flex items-center justify-between gap-1.5 pl-0.5"
                                                >
                                                    <div class="relative">
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            max="100"
                                                            step="1"
                                                            v-model.number="percentagesGrid[`${date}_${meal}_${struct.id}`]"
                                                            @blur="
                                                                percentagesGrid[`${date}_${meal}_${struct.id}`] = clampPct(
                                                                    percentagesGrid[`${date}_${meal}_${struct.id}`],
                                                                )
                                                            "
                                                            title="Porcentaje de comensales que toma este plato"
                                                            class="h-6 w-[60px] [appearance:textfield] rounded-md border border-slate-200 bg-slate-50 pr-4 pl-1.5 text-right text-[11px] font-semibold text-slate-600 tabular-nums transition-colors focus:border-[#FF5A1F] focus:bg-white focus:ring-1 focus:ring-[#FF5A1F]/25 focus:outline-none [&::-webkit-inner-spin-button]:[appearance:none] [&::-webkit-outer-spin-button]:[appearance:none]"
                                                        />
                                                        <span
                                                            class="pointer-events-none absolute top-1/2 right-1 -translate-y-1/2 text-[10px] text-slate-400"
                                                            >%</span
                                                        >
                                                    </div>
                                                    <span
                                                        class="text-[10px] font-semibold tracking-tight tabular-nums"
                                                        :class="
                                                            Number(percentagesGrid[`${date}_${meal}_${struct.id}`]) < 100
                                                                ? 'text-[#FF5A1F]'
                                                                : 'text-slate-400'
                                                        "
                                                    >
                                                        {{ effectiveRations(date, meal, struct.id).toLocaleString('es-PE') }} rac.
                                                    </span>
                                                </div>
                                            </div>
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

            <div v-if="activeTab === 'programaciones'" class="flex flex-col gap-4">
                <div
                    v-if="programs_total === 0"
                    class="rounded-2xl border border-dashed border-slate-200 bg-white p-10 text-center text-sm text-slate-400"
                >
                    Aún no hay programaciones guardadas. Complete la matriz en la pestaña "Planificar" y presione "Guardar Planificación".
                </div>

                <template v-else>
                    <!-- Filtros -->
                    <div
                        class="grid grid-cols-2 items-end gap-3 rounded-2xl border border-l-4 border-slate-100 border-l-[#FF5A1F] bg-white p-4 shadow-sm sm:flex sm:flex-wrap"
                    >
                        <div class="flex flex-col gap-1 sm:min-w-[140px]">
                            <label class="text-[10px] font-bold tracking-wider text-[#FF5A1F]/75 uppercase">Mina</label>
                            <select
                                v-model="programFilters.mine"
                                class="h-9 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 focus:border-[#FF5A1F] focus:ring-1 focus:ring-[#FF5A1F]/25 focus:outline-none sm:w-auto sm:min-w-[150px]"
                            >
                                <option value="">Todas</option>
                                <option v-for="m in programMineOptions" :key="m.id" :value="String(m.id)">{{ m.name }}</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1 sm:min-w-[140px]">
                            <label class="text-[10px] font-bold tracking-wider text-[#FF5A1F]/75 uppercase">Unidad</label>
                            <select
                                v-model="programFilters.unit"
                                class="h-9 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 focus:border-[#FF5A1F] focus:ring-1 focus:ring-[#FF5A1F]/25 focus:outline-none sm:w-auto sm:min-w-[150px]"
                            >
                                <option value="">Todas</option>
                                <option v-for="u in programUnitOptions" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1 sm:min-w-[140px]">
                            <label class="text-[10px] font-bold tracking-wider text-[#FF5A1F]/75 uppercase">Café / Comedor</label>
                            <select
                                v-model="programFilters.cafe"
                                class="h-9 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 focus:border-[#FF5A1F] focus:ring-1 focus:ring-[#FF5A1F]/25 focus:outline-none sm:w-auto sm:min-w-[140px]"
                            >
                                <option value="">Todos</option>
                                <option v-for="c in programCafeOptions" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] font-bold tracking-wider text-[#FF5A1F]/75 uppercase">Guardado desde</label>
                            <Input
                                type="date"
                                v-model="programFilters.savedFrom"
                                class="h-9 w-full rounded-lg border-slate-200 text-xs focus-visible:ring-[#FF5A1F] sm:w-[150px]"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] font-bold tracking-wider text-[#FF5A1F]/75 uppercase">Guardado hasta</label>
                            <Input
                                type="date"
                                v-model="programFilters.savedTo"
                                class="h-9 w-full rounded-lg border-slate-200 text-xs focus-visible:ring-[#FF5A1F] sm:w-[150px]"
                            />
                        </div>
                        <button
                            v-if="hasProgramFilters"
                            type="button"
                            @click="clearProgramFilters"
                            class="col-span-2 h-9 rounded-lg px-2.5 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-700 sm:col-auto"
                        >
                            Limpiar filtros
                        </button>
                        <span class="col-span-2 self-center text-xs text-slate-400 sm:col-auto sm:ml-auto">
                            {{ programsLoading ? 'Cargando…' : `${programsMeta.total} de ${programs_total}` }}
                        </span>
                    </div>

                    <!-- Barra de acciones sobre la selección -->
                    <div
                        class="flex flex-wrap items-center gap-3 rounded-2xl border p-3 px-4 transition-colors"
                        :class="selectedProgramIds.length ? 'border-[#FF5A1F]/30 bg-orange-50/60' : 'border-slate-100 bg-white'"
                    >
                        <span class="text-sm font-semibold" :class="selectedProgramIds.length ? 'text-[#FF5A1F]' : 'text-slate-400'">
                            {{ selectedProgramIds.length }} seleccionada{{ selectedProgramIds.length === 1 ? '' : 's' }}
                        </span>
                        <span v-if="!selectedProgramIds.length" class="text-xs text-slate-400">
                            — marque programaciones para generar reportes combinados
                        </span>
                        <button
                            v-if="selectedProgramIds.length"
                            type="button"
                            @click="selectedProgramIds = []"
                            class="text-xs font-medium text-slate-500 underline-offset-2 hover:underline"
                        >
                            Quitar selección
                        </button>
                        <div class="flex w-full items-center gap-2 sm:ml-auto sm:w-auto">
                            <Button
                                size="sm"
                                :disabled="!selectedProgramIds.length"
                                @click="generateWeeklyMenuExcel"
                                class="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-40 sm:flex-none"
                            >
                                <FileSpreadsheet class="h-4 w-4 shrink-0" />
                                <span class="truncate">Menú Semanal (Excel)</span>
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="!selectedProgramIds.length"
                                @click="generatePOForSelection"
                                class="flex flex-1 items-center justify-center rounded-lg border-[#FF5A1F]/40 text-[#FF5A1F] hover:bg-orange-50 disabled:opacity-40 sm:flex-none"
                            >
                                <span class="truncate">Quebrado (PO)</span>
                            </Button>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        size="sm"
                                        :disabled="!selectedProgramIds.length"
                                        class="flex flex-1 items-center justify-center gap-1 rounded-lg bg-[#FF5A1F] text-white hover:bg-[#e04a17] disabled:opacity-40 sm:flex-none"
                                    >
                                        Reportes
                                        <ChevronDown class="h-3.5 w-3.5" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-72">
                                    <DropdownMenuItem class="cursor-pointer" @select="generateWeeklyQuebradoPdf">
                                        <span class="flex-1">Quebrado Semanal</span>
                                        <span class="text-[10px] font-semibold text-slate-400">PDF</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem class="cursor-pointer" @select="generateWeeklyRequirementPdf">
                                        <span class="flex-1">Requerimiento x Producto</span>
                                        <span class="text-[10px] font-semibold text-slate-400">PDF · consolidado</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem class="cursor-pointer" @select="generateWeeklyDosificacionPdf">
                                        <span class="flex-1">Dosificación Nutricional</span>
                                        <span class="text-[10px] font-semibold text-slate-400">PDF</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem class="cursor-pointer" @select="generateWeeklyPurchaseOrderExcel">
                                        <span class="flex-1">Orden de Pedido Semanal</span>
                                        <span class="text-[10px] font-semibold text-slate-400">Excel · consolidado</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem class="cursor-pointer" @select="generatePurchaseReport">
                                        <span class="flex-1">Reporte de Compras Semanales</span>
                                        <span class="text-[10px] font-semibold text-slate-400">Interactivo · por comedor</span>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-2xl border border-l-4 border-slate-100 border-l-[#FF5A1F] bg-white shadow-sm">
                        <div class="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow class="bg-orange-50 hover:bg-orange-50">
                                        <TableHead class="w-[44px]">
                                            <Checkbox
                                                :model-value="allVisibleSelected"
                                                @update:model-value="toggleSelectAllVisible(Boolean($event))"
                                                aria-label="Seleccionar todas"
                                            />
                                        </TableHead>
                                        <TableHead class="hidden font-semibold text-slate-600 sm:table-cell">Servicio</TableHead>
                                        <TableHead class="font-semibold text-slate-600">Comedor</TableHead>
                                        <TableHead class="hidden font-semibold text-slate-600 md:table-cell">Periodo</TableHead>
                                        <TableHead class="hidden font-semibold text-slate-600 lg:table-cell">Guardado</TableHead>
                                        <TableHead class="hidden font-semibold text-slate-600 xl:table-cell">Estructura</TableHead>
                                        <TableHead class="hidden font-semibold text-slate-600 md:table-cell">Estado</TableHead>
                                        <TableHead class="text-right font-semibold text-slate-600">Acciones</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="program in programsPage"
                                        :key="program.id"
                                        class="transition-colors hover:bg-slate-50/50"
                                        :class="selectedProgramIds.includes(program.id) ? 'bg-orange-50/50' : ''"
                                    >
                                        <TableCell>
                                            <Checkbox
                                                :model-value="selectedProgramIds.includes(program.id)"
                                                @update:model-value="toggleProgram(program.id, Boolean($event))"
                                                :aria-label="`Seleccionar programación ${program.id}`"
                                            />
                                        </TableCell>
                                        <TableCell class="hidden sm:table-cell">
                                            <Badge
                                                v-if="programRow(program).service"
                                                variant="outline"
                                                class="border-[#FF5A1F]/25 bg-orange-50 font-semibold text-[#FF5A1F]"
                                            >
                                                {{ programRow(program).service }}
                                            </Badge>
                                            <span v-else class="text-xs text-slate-300">—</span>
                                        </TableCell>
                                        <TableCell class="whitespace-normal">
                                            <div class="font-semibold text-slate-800">{{ programRow(program).cafe }}</div>
                                            <div class="text-xs text-slate-400">{{ programRow(program).unit }} · {{ programRow(program).mine }}</div>
                                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5 md:hidden">
                                                <Badge
                                                    v-if="programRow(program).service"
                                                    variant="outline"
                                                    class="border-[#FF5A1F]/25 bg-orange-50 text-[10px] font-semibold text-[#FF5A1F] sm:hidden"
                                                >
                                                    {{ programRow(program).service }}
                                                </Badge>
                                                <span class="text-[11px] text-slate-500">
                                                    {{ dayjs(program.start_date).format('DD/MM') }}–{{ dayjs(program.end_date).format('DD/MM/YY') }}
                                                </span>
                                                <Badge
                                                    variant="outline"
                                                    class="border-slate-200 bg-slate-50 text-[10px] font-medium text-slate-600 capitalize"
                                                >
                                                    {{ program.status }}
                                                </Badge>
                                            </div>
                                        </TableCell>
                                        <TableCell class="hidden text-slate-600 md:table-cell">
                                            {{ dayjs(program.start_date).format('DD/MM/YYYY') }} –
                                            {{ dayjs(program.end_date).format('DD/MM/YYYY') }}
                                        </TableCell>
                                        <TableCell class="hidden text-slate-500 tabular-nums lg:table-cell">
                                            <template v-if="programRow(program).savedAt">
                                                {{ dayjs(programRow(program).savedAt).format('DD/MM/YYYY') }}
                                                <span class="text-xs text-slate-400">{{ dayjs(programRow(program).savedAt).format('HH:mm') }}</span>
                                            </template>
                                            <span v-else class="text-slate-300">—</span>
                                        </TableCell>
                                        <TableCell class="hidden text-slate-600 xl:table-cell">{{ program.structure?.name || '—' }}</TableCell>
                                        <TableCell class="hidden md:table-cell">
                                            <Badge variant="outline" class="border-slate-200 bg-slate-50 font-medium text-slate-600 capitalize">
                                                {{ program.status }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex items-center justify-end">
                                                <Button
                                                    size="sm"
                                                    @click="generatePO(program)"
                                                    variant="outline"
                                                    class="rounded-lg border-slate-200 text-slate-600 hover:bg-slate-50"
                                                >
                                                    Quebrado (PO)
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                    <tr v-if="programsPage.length === 0">
                                        <td colspan="8" class="py-10 text-center text-sm text-slate-400">
                                            {{ programsLoading ? 'Cargando programaciones…' : 'Ninguna programación coincide con los filtros.' }}
                                        </td>
                                    </tr>
                                </TableBody>
                            </Table>
                        </div>
                        <div
                            v-if="programsMeta.total > 0"
                            class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-xs text-slate-500"
                        >
                            <span> Mostrando {{ programsMeta.from }}–{{ programsMeta.to }} de {{ programsMeta.total }} </span>
                            <div class="flex items-center gap-1.5">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-8 rounded-lg border-slate-200 px-2.5 text-xs"
                                    :disabled="programsLoading || programsMeta.current_page <= 1"
                                    @click="goToProgramsPage(1)"
                                >
                                    «
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-8 rounded-lg border-slate-200 px-2.5 text-xs"
                                    :disabled="programsLoading || programsMeta.current_page <= 1"
                                    @click="goToProgramsPage(programsMeta.current_page - 1)"
                                >
                                    Anterior
                                </Button>
                                <span class="px-2 tabular-nums">Página {{ programsMeta.current_page }} de {{ programsMeta.last_page }}</span>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-8 rounded-lg border-slate-200 px-2.5 text-xs"
                                    :disabled="programsLoading || programsMeta.current_page >= programsMeta.last_page"
                                    @click="goToProgramsPage(programsMeta.current_page + 1)"
                                >
                                    Siguiente
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-8 rounded-lg border-slate-200 px-2.5 text-xs"
                                    :disabled="programsLoading || programsMeta.current_page >= programsMeta.last_page"
                                    @click="goToProgramsPage(programsMeta.last_page)"
                                >
                                    »
                                </Button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
