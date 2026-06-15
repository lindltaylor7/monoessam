<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Table, TableBody, TableCaption, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { router } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, FolderOpen, Save, Search, Trash } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    categories?: any[];
    structures?: any[];
    serviceableId?: string | null;
}>();

const categoriesSelected = ref<any[]>([]);
const searchQuery = ref('');
const open = ref(false);
const structureName = ref('');
const sellingPrice = ref<number | null>(null);
const loadedStructureId = ref<number | null>(null);

watch(
    () => props.serviceableId,
    async (newId) => {
        if (!newId) return;
        const structure = props.structures?.find((s) => String(s.serviceable_id) === String(newId));
        if (!structure) return;

        // Don't silently wipe work in progress when switching service
        if (categoriesSelected.value.length > 0 && loadedStructureId.value !== structure.id) {
            const result = await Swal.fire({
                title: 'Estructura guardada encontrada',
                text: `Este servicio ya tiene la estructura "${structure.name}". ¿Desea cargarla? Se reemplazará la configuración actual.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, cargar',
                cancelButtonText: 'Mantener actual',
                confirmButtonColor: '#4f46e5',
            });
            if (!result.isConfirmed) return;
        }
        loadStructure(structure);
    },
    { immediate: true },
);

const filteredCategories = computed(() => {
    const categories = props.categories;
    if (!categories || !Array.isArray(categories)) return [];
    if (!searchQuery.value) return categories;
    const query = searchQuery.value.toLowerCase();
    return categories.filter((cat: any) => cat && cat.name && cat.name.toLowerCase().includes(query));
});

const totalBaseCost = computed(() => {
    return categoriesSelected.value
        .reduce((acc, cat) => {
            return acc + (parseFloat(cat.total_cost) || 0);
        }, 0)
        .toFixed(2);
});

const totalSuperiorCost = computed(() => {
    return categoriesSelected.value
        .reduce((acc, cat) => {
            return acc + (parseFloat(cat.total_cost_superior) || 0);
        }, 0)
        .toFixed(2);
});

const costMargin = computed(() => {
    const price = parseFloat(String(sellingPrice.value)) || 0;
    const cost = parseFloat(totalSuperiorCost.value) || 0;
    if (price <= 0) return '0.00';
    return ((cost / price) * 100).toFixed(2);
});

// Color the margin: low cost ratio = healthy, near/over 100% = losing money
const marginClass = computed(() => {
    const m = parseFloat(costMargin.value) || 0;
    if (m === 0) return 'text-slate-900 dark:text-white';
    if (m <= 70) return 'text-emerald-600 dark:text-emerald-400';
    if (m <= 90) return 'text-amber-600 dark:text-amber-400';
    return 'text-rose-600 dark:text-rose-400';
});

const totalRation = computed(() => {
    return categoriesSelected.value.reduce((acc, cat) => acc + (parseFloat(cat.ration) || 0), 0).toFixed(2);
});
watch(
    categoriesSelected,
    (newVal) => {
        newVal.forEach((category) => {
            const ration = parseFloat(category.ration) || 0;
            const unitCost = parseFloat(category.unit_cost) || 0;

            // Función para truncar a dos decimales sin redondear
            const truncate = (num: number) => (Math.floor(num * 100) / 100).toFixed(2);

            // Función para redondear a dos decimales
            const round = (num: number) => (Math.round(num * 100) / 100).toFixed(2);

            // T.Inferior (Costo Total)
            const total = truncate((ration * unitCost) / 100);
            if (category.total_cost !== total) {
                category.total_cost = total;
            }

            // L.Superior = L.Inferior * 1.03 (Redondeado a 2 decimales)
            const unitCostSuperior = round(unitCost * 1.03);
            if (category.unit_cost_superior !== unitCostSuperior) {
                category.unit_cost_superior = unitCostSuperior;
            }

            // T.Superior = L.Superior * % Ración / 100 (Truncado a 2 decimales)
            const totalSuperior = truncate((parseFloat(unitCostSuperior) * ration) / 100);
            if (category.total_cost_superior !== totalSuperior) {
                category.total_cost_superior = totalSuperior;
            }
        });
    },
    { deep: true },
);

const selectCategory = (category: any) => {
    if (categoriesSelected.value.some((c) => c.id === category.id)) {
        Swal.fire({
            title: 'Categoría duplicada',
            text: `"${category.name}" ya está en la estructura.`,
            icon: 'info',
            timer: 1800,
            showConfirmButton: false,
        });
        return;
    }
    // Ensure the unit is properly mapped when adding
    const newCategory = { ...category, measurement_unit: category.mesearument_unit || category.measurement_unit || 'UNID' };
    categoriesSelected.value.push(newCategory);
    open.value = false;
    searchQuery.value = '';
};

const deleteCategory = (index: number) => {
    categoriesSelected.value.splice(index, 1);
};

const moveCategoryUp = (index: number) => {
    if (index > 0) {
        const temp = categoriesSelected.value[index];
        categoriesSelected.value[index] = categoriesSelected.value[index - 1];
        categoriesSelected.value[index - 1] = temp;
    }
};

const moveCategoryDown = (index: number) => {
    if (index < categoriesSelected.value.length - 1) {
        const temp = categoriesSelected.value[index];
        categoriesSelected.value[index] = categoriesSelected.value[index + 1];
        categoriesSelected.value[index + 1] = temp;
    }
};

const loadStructure = (struct: any) => {
    loadedStructureId.value = struct.id;
    structureName.value = struct.name;
    sellingPrice.value = struct.selling_price || null;
    categoriesSelected.value = struct.costs.map((cost: any) => ({
        id: cost.dish_category_id,
        name: cost.name || 'Categoría guardada', // if relations weren't loaded, at least we have the name saved in db
        reference_volume: cost.reference_volume,
        measurement_unit: cost.measurement_unit,
        ration: cost.ration,
        unit_cost: cost.unit_cost,
        total_cost: cost.total_cost,
        unit_cost_superior: cost.unit_cost_superior,
        total_cost_superior: cost.total_cost_superior,
    }));
};

const deleteStructure = (id: number) => {
    Swal.fire({
        title: '¿Estás seguro?',
        text: 'Se eliminará esta estructura guardada',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('food.structure.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    if (loadedStructureId.value === id) {
                        clearStructure();
                    }
                    Swal.fire('¡Eliminado!', 'La estructura ha sido eliminada.', 'success');
                },
            });
        }
    });
};

const clearStructure = () => {
    loadedStructureId.value = null;
    structureName.value = '';
    sellingPrice.value = null;
    categoriesSelected.value = [];
};

const saveStructure = () => {
    if (!props.serviceableId) {
        Swal.fire({
            title: 'Atención',
            text: 'Debe seleccionar mina, unidad, comedor y servicio en la parte superior antes de guardar.',
            icon: 'warning',
            confirmButtonText: 'Entendido',
        });
        return;
    }

    if (!structureName.value.trim()) {
        Swal.fire({
            title: 'Falta el nombre',
            text: 'Por favor, ingrese un nombre para la estructura.',
            icon: 'warning',
            confirmButtonText: 'Entendido',
        });
        return;
    }

    if (categoriesSelected.value.length === 0) {
        Swal.fire({
            title: 'Estructura vacía',
            text: 'Añada al menos una categoría a la estructura antes de guardar.',
            icon: 'warning',
            confirmButtonText: 'Entendido',
        });
        return;
    }

    const categoriesToSave = categoriesSelected.value.map((cat, index) => ({
        ...cat,
        order: index + 1,
    }));

    router.post(
        route('food.structure.store'),
        {
            name: structureName.value,
            serviceable_id: props.serviceableId,
            selling_price: sellingPrice.value,
            categories: categoriesToSave,
        },
        {
            onError: (errors) => {
                if (errors.name) {
                    Swal.fire({
                        title: 'Error de validación',
                        text: errors.name,
                        icon: 'error',
                        confirmButtonText: 'Entendido',
                    });
                }
            },
            onSuccess: () => {
                clearStructure();
                Swal.fire({
                    title: '¡Éxito!',
                    text: 'Estructura guardada correctamente',
                    icon: 'success',
                    confirmButtonText: 'Aceptar',
                });
            },
        },
    );
};
</script>

<template>
    <div
        class="border-sidebar-border/70 dark:border-sidebar-border relative col-span-2 flex aspect-video flex-col gap-4 overflow-hidden rounded-xl border p-4"
    >
        <div
            class="flex w-full flex-wrap items-center justify-between gap-4 rounded-lg border border-slate-200 bg-slate-50/50 p-3 dark:border-slate-800 dark:bg-slate-900/20"
        >
            <div class="flex flex-wrap items-center gap-3">
                <Popover v-model:open="open">
                    <PopoverTrigger as-child>
                        <Button
                            variant="outline"
                            class="h-10 justify-between border-slate-200 bg-white font-semibold shadow-sm transition-all hover:border-indigo-300"
                        >
                            <ChevronDown class="mr-2 h-4 w-4 opacity-50" />
                            Añadir Categoría a Estructura
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent class="w-[300px] p-0 shadow-xl">
                        <div class="flex items-center border-b bg-slate-50/50 px-3">
                            <Search class="mr-2 h-4 w-4 shrink-0 text-indigo-600 opacity-50" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Buscar categoría..."
                                class="placeholder:text-muted-foreground flex h-11 w-full rounded-md border-0 bg-transparent py-3 text-sm outline-none focus-visible:ring-0"
                            />
                        </div>
                        <div class="max-h-[300px] overflow-y-auto p-1">
                            <div
                                v-for="category in filteredCategories || []"
                                :key="category?.id"
                                class="relative flex cursor-default cursor-pointer items-center rounded-sm px-3 py-2.5 text-sm transition-colors select-none hover:bg-indigo-50 hover:text-indigo-900"
                                @click="selectCategory(category)"
                            >
                                {{ category?.name }}
                            </div>
                            <div v-if="filteredCategories?.length === 0" class="text-muted-foreground py-10 text-center text-sm italic">
                                No se encontraron resultados.
                            </div>
                        </div>
                    </PopoverContent>
                </Popover>

                <div
                    class="flex h-10 items-center gap-3 rounded-md border border-slate-200 bg-white px-3 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <div class="flex items-center gap-2 border-r border-slate-100 pr-3 dark:border-slate-800">
                        <span class="text-[10px] font-bold whitespace-nowrap text-slate-500 uppercase">Precio Venta</span>
                        <Input
                            type="number"
                            v-model="sellingPrice"
                            class="h-7 w-20 border-none bg-slate-50/50 text-center text-xs font-bold focus-visible:ring-0"
                            placeholder="0.00"
                        />
                    </div>
                    <div class="flex items-center gap-2" title="Costo superior / Precio de venta. Verde: saludable, ámbar: ajustado, rojo: pérdida.">
                        <span class="text-[10px] font-bold text-indigo-600 uppercase dark:text-indigo-400">Costo/Precio</span>
                        <span class="text-sm font-black" :class="marginClass">{{ costMargin }}%</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <div class="group relative">
                    <Input
                        v-model="structureName"
                        placeholder="Nombre de estructura..."
                        class="h-10 w-[200px] border-slate-200 pl-9 transition-all focus:border-indigo-400"
                    />
                    <Save class="absolute top-3 left-3 h-4 w-4 text-slate-400 transition-colors group-focus-within:text-indigo-500" />
                </div>
                <Button
                    @click="saveStructure"
                    class="flex h-10 items-center gap-2 bg-indigo-600 px-4 font-semibold text-white shadow-md shadow-indigo-200 transition-all hover:bg-indigo-700 dark:shadow-none"
                >
                    Guardar Estructura
                </Button>
            </div>
        </div>

        <!-- Saved structures -->
        <div
            v-if="(structures?.length || 0) > 0"
            class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-950"
        >
            <span class="flex items-center gap-1.5 text-[10px] font-bold text-slate-500 uppercase">
                <FolderOpen class="h-3.5 w-3.5" /> Estructuras guardadas
            </span>
            <div
                v-for="struct in structures"
                :key="struct.id"
                class="group flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-semibold transition-all"
                :class="
                    loadedStructureId === struct.id
                        ? 'border-indigo-600 bg-indigo-600 text-white shadow-sm'
                        : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-indigo-300 hover:bg-indigo-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'
                "
            >
                <button type="button" @click="loadStructure(struct)" :title="`Cargar estructura (${struct.costs?.length || 0} categorías)`">
                    {{ struct.name }}
                    <span v-if="struct.selling_price" class="ml-1 opacity-70">S/. {{ Number(struct.selling_price).toFixed(2) }}</span>
                </button>
                <button
                    type="button"
                    @click.stop="deleteStructure(struct.id)"
                    class="ml-1 opacity-0 transition-opacity group-hover:opacity-100"
                    :class="loadedStructureId === struct.id ? 'text-white/80 hover:text-white' : 'text-slate-400 hover:text-red-500'"
                    title="Eliminar estructura"
                >
                    <Trash class="h-3 w-3" />
                </button>
            </div>
        </div>
        <Table>
            <TableHeader>
                <TableRow class="hover:bg-transparent">
                    <TableHead class="w-[105px]">Orden</TableHead>
                    <TableHead>Nombre de Categoria</TableHead>
                    <TableHead>Volumen Referencial</TableHead>
                    <TableHead>% Ración</TableHead>
                    <TableHead>Costo unitario minimo (L.Inferior)</TableHead>
                    <TableHead>Costo Total (T. Inferior)</TableHead>
                    <TableHead>Costo unitario minimo (L.Superior)</TableHead>
                    <TableHead>Costo Total (T. Superior)</TableHead>
                    <TableHead class="text-right">Opciones</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-if="(categoriesSelected?.length || 0) === 0" class="hover:bg-transparent">
                    <TableCell colspan="9" class="text-muted-foreground h-32 text-center text-sm">
                        <div class="flex flex-col items-center gap-1">
                            <span class="font-medium">La estructura está vacía.</span>
                            <span class="text-xs"
                                >Use "Añadir Categoría a Estructura" para armarla, o cargue una estructura guardada desde la lista superior.</span
                            >
                        </div>
                    </TableCell>
                </TableRow>
                <TableRow
                    v-for="(category, index) in categoriesSelected || []"
                    :key="category?.id"
                    class="odd:bg-muted/30 hover:bg-muted/50 transition-colors even:bg-transparent"
                >
                    <TableCell class="w-[105px]">
                        <div class="flex items-center justify-center gap-1">
                            <Button
                                variant="outline"
                                size="icon"
                                class="text-muted-foreground hover:text-foreground h-7 w-7"
                                :disabled="index === 0"
                                @click="moveCategoryUp(index)"
                            >
                                <ChevronUp class="h-4 w-4" />
                            </Button>
                            <span class="w-6 text-center text-sm font-bold">{{ index + 1 }}</span>
                            <Button
                                variant="outline"
                                size="icon"
                                class="text-muted-foreground hover:text-foreground h-7 w-7"
                                :disabled="index === (categoriesSelected?.length || 0) - 1"
                                @click="moveCategoryDown(index)"
                            >
                                <ChevronDown class="h-4 w-4" />
                            </Button>
                        </div>
                    </TableCell>
                    <TableCell class="font-medium text-indigo-600 dark:text-indigo-400">
                        {{ category?.name }}
                    </TableCell>
                    <TableCell class="text-right">
                        <div class="flex items-center justify-end gap-2">
                            <Input
                                type="number"
                                v-model="category.reference_volume"
                                class="h-9 w-full rounded-md border border-indigo-200 bg-indigo-50/30 text-center font-medium text-indigo-600 shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-indigo-400 dark:border-indigo-900/50 dark:bg-indigo-900/10 dark:text-indigo-400"
                            />
                            <span class="text-muted-foreground w-16 text-left text-xs font-semibold uppercase">{{
                                category?.measurement_unit || category?.mesearument_unit || 'UNID'
                            }}</span>
                        </div>
                    </TableCell>
                    <TableCell class="text-right">
                        <Input
                            type="number"
                            v-model="category.ration"
                            class="h-9 rounded-md border border-sky-200 bg-sky-50/30 text-center font-medium text-sky-600 shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-sky-400 dark:border-sky-900/50 dark:bg-sky-900/10 dark:text-sky-400"
                        />
                    </TableCell>
                    <TableCell class="text-right">
                        <Input
                            type="number"
                            v-model="category.unit_cost"
                            class="h-9 rounded-md border border-emerald-200 bg-emerald-50/30 text-center font-medium text-emerald-600 shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-emerald-400 dark:border-emerald-900/50 dark:bg-emerald-900/10 dark:text-emerald-400"
                        />
                    </TableCell>
                    <TableCell class="text-right">
                        <Input
                            type="number"
                            v-model="category.total_cost"
                            readonly
                            class="h-9 rounded-md border border-rose-200 bg-rose-100 text-center font-semibold text-rose-700 shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-rose-400 dark:border-rose-900/50 dark:bg-rose-900/30 dark:text-rose-400"
                        />
                    </TableCell>
                    <TableCell class="text-right">
                        <Input
                            type="number"
                            v-model="category.unit_cost_superior"
                            readonly
                            class="h-9 rounded-md border border-amber-200 bg-amber-50 text-center font-medium text-amber-600 shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-amber-400 dark:border-amber-900/50 dark:bg-amber-900/30 dark:text-amber-400"
                        />
                    </TableCell>
                    <TableCell class="text-right">
                        <Input
                            type="number"
                            v-model="category.total_cost_superior"
                            readonly
                            class="h-9 rounded-md border border-orange-200 bg-orange-100 text-center font-semibold text-orange-700 shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-orange-400 dark:border-orange-900/50 dark:bg-orange-900/30 dark:text-orange-400"
                        />
                    </TableCell>
                    <TableCell class="text-right">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="text-muted-foreground hover:text-destructive h-8 w-8"
                            @click="deleteCategory(index)"
                        >
                            <Trash class="h-4 w-4" />
                        </Button>
                    </TableCell>
                </TableRow>
            </TableBody>
            <TableFooter v-if="(categoriesSelected?.length || 0) > 0">
                <TableRow class="bg-slate-50/50 hover:bg-transparent dark:bg-slate-900/10">
                    <TableCell colspan="3" class="pr-4 text-right text-xs font-bold text-slate-500 uppercase">Resumen:</TableCell>
                    <TableCell class="p-2 text-right">
                        <div class="flex flex-col items-center" :title="totalRation !== '100.00' ? 'La suma de % ración no llega a 100%' : ''">
                            <span class="mb-0.5 text-[9px] font-black text-sky-600 uppercase">% Ración Total</span>
                            <div
                                class="flex h-8 w-full items-center justify-center rounded border-2 text-center text-sm font-bold shadow-sm"
                                :class="
                                    totalRation === '100.00'
                                        ? 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/50 dark:bg-sky-900/30 dark:text-sky-400'
                                        : 'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-900/50 dark:bg-amber-900/30 dark:text-amber-400'
                                "
                            >
                                {{ totalRation }}%
                            </div>
                        </div>
                    </TableCell>
                    <TableCell></TableCell>
                    <TableCell class="p-2 text-right">
                        <div class="flex flex-col items-center">
                            <span class="mb-0.5 text-[9px] font-black text-rose-600 uppercase">Costo Inferior</span>
                            <div
                                class="flex h-8 w-full items-center justify-center rounded border-2 border-rose-200 bg-rose-50 text-center text-sm font-bold text-rose-700 shadow-sm dark:border-rose-900/50 dark:bg-rose-900/30 dark:text-rose-400"
                            >
                                {{ totalBaseCost }}
                            </div>
                        </div>
                    </TableCell>
                    <TableCell class="text-right"></TableCell>
                    <TableCell class="p-2 text-right">
                        <div class="flex flex-col items-center">
                            <span class="mb-0.5 text-[9px] font-black text-amber-600 uppercase">Costo Superior</span>
                            <div
                                class="flex h-8 w-full items-center justify-center rounded border-2 border-amber-200 bg-amber-50 text-center text-sm font-bold text-amber-700 shadow-sm dark:border-amber-900/50 dark:bg-amber-900/30 dark:text-amber-400"
                            >
                                {{ totalSuperiorCost }}
                            </div>
                        </div>
                    </TableCell>
                    <TableCell></TableCell>
                </TableRow>
            </TableFooter>
            <TableCaption class="pb-4">Lista de categorías de la estructura de menú actual.</TableCaption>
        </Table>
    </div>
</template>
