<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow, TableFooter } from '@/components/ui/table';
import { Trash, ChevronDown, ChevronUp, Search, Save } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
    categories?: any[];
    structures?: any[];
    serviceableId?: string | null;
}>();

const categoriesSelected = ref<any[]>([]);
const searchQuery = ref('');
const open = ref(false);
const openStructureSearch = ref(false);
const structureName = ref('');

const filteredCategories = computed(() => {
    const categories = props.categories;
    if (!categories || !Array.isArray(categories)) return [];
    if (!searchQuery.value) return categories;
    const query = searchQuery.value.toLowerCase();
    return categories.filter((cat: any) => 
        cat && cat.name && cat.name.toLowerCase().includes(query)
    );
});

const filteredStructures = computed(() => {
    if (!props.structures) return [];
    if (!props.serviceableId) return [];
    return props.structures.filter((s: any) => String(s.serviceable_id) === String(props.serviceableId));
});

const totalBaseCost = computed(() => {
    return categoriesSelected.value.reduce((acc, cat) => {
        return acc + (parseFloat(cat.total_cost) || 0);
    }, 0).toFixed(2);
});

const totalSuperiorCost = computed(() => {
    return categoriesSelected.value.reduce((acc, cat) => {
        return acc + (parseFloat(cat.total_cost_superior) || 0);
    }, 0).toFixed(2);
});

const sellingPrice = ref<number | null>(null);

const costMargin = computed(() => {
    const price = parseFloat(String(sellingPrice.value)) || 0;
    const cost = parseFloat(totalSuperiorCost.value) || 0;
    if (price <= 0) return '0.00';
    return ((cost / price) * 100).toFixed(2);
});
watch(categoriesSelected, (newVal) => {
    newVal.forEach(category => {
        const ration = parseFloat(category.ration) || 0;
        const unitCost = parseFloat(category.unit_cost) || 0;
        
        // Función para truncar a dos decimales sin redondear
        const truncate = (num: number) => (Math.floor(num * 100) / 100).toFixed(2);
        
        // Función para redondear a dos decimales
        const round = (num: number) => (Math.round(num * 100) / 100).toFixed(2);

        // T.Inferior (Costo Total)
        const total = truncate(ration * unitCost / 100);
        if (category.total_cost !== total) {
            category.total_cost = total;
        }

        // L.Superior = L.Inferior * 1.03 (Redondeado a 2 decimales)
        const unitCostSuperior = round(unitCost * 1.03);
        if (category.unit_cost_superior !== unitCostSuperior) {
            category.unit_cost_superior = unitCostSuperior;
        }

        // T.Superior = L.Superior * % Ración / 100 (Truncado a 2 decimales)
        const totalSuperior = truncate(parseFloat(unitCostSuperior) * ration / 100);
        if (category.total_cost_superior !== totalSuperior) {
            category.total_cost_superior = totalSuperior;
        }
    });
}, { deep: true });

const selectCategory = (category: any) => {
    console.log(category);
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
        total_cost_superior: cost.total_cost_superior
    }));
    openStructureSearch.value = false;
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
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('food.structure.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire('¡Eliminado!', 'La estructura ha sido eliminada.', 'success');
                }
            });
        }
    });
};

const saveStructure = () => {
    if (!props.serviceableId) {
        Swal.fire({
            title: 'Atención',
            text: 'Debe seleccionar un servicio primero.',
            icon: 'warning',
            confirmButtonText: 'Entendido'
        });
        return;
    }

    if (!structureName.value) {
        alert('Por favor, ingrese un nombre para la estructura.');
        return;
    }

    const categoriesToSave = categoriesSelected.value.map((cat, index) => ({
        ...cat,
        order: index + 1
    }));

    router.post(route('food.structure.store'), {
        name: structureName.value,
        serviceable_id: props.serviceableId,
        selling_price: sellingPrice.value,
        categories: categoriesToSave
    }, {
        onError: (errors) => {
            if (errors.name) {
                Swal.fire({
                    title: 'Error de validación',
                    text: errors.name,
                    icon: 'error',
                    confirmButtonText: 'Entendido'
                });
            }
        },
        onSuccess: () => {
            structureName.value = '';
            sellingPrice.value = null;
            categoriesSelected.value = [];
            Swal.fire({
                title: '¡Éxito!',
                text: 'Estructura guardada correctamente',
                icon: 'success',
                confirmButtonText: 'Aceptar'
            });
        }
    });
};
</script>

<template>
    <div class="border-sidebar-border/70 dark:border-sidebar-border relative col-span-2 aspect-video overflow-hidden rounded-xl border flex flex-col p-4 gap-4">
        <div class="flex flex-wrap items-center justify-between gap-4 w-full bg-slate-50/50 dark:bg-slate-900/20 p-3 rounded-lg border border-slate-200 dark:border-slate-800">
            <div class="flex flex-wrap items-center gap-3">
                <Popover v-model:open="openStructureSearch">
                    <PopoverTrigger as-child>
                        <Button variant="outline" class="h-10 justify-between text-indigo-600 border-indigo-200 bg-white hover:bg-indigo-50 dark:bg-slate-950 dark:border-indigo-900 shadow-sm">
                            <Search class="h-4 w-4 mr-2 opacity-70" />
                            Estructuras Guardadas
                            <ChevronDown class="h-4 w-4 ml-2 opacity-50" />
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent class="w-[250px] p-0 shadow-xl border-indigo-100">
                        <div class="max-h-[300px] overflow-y-auto p-1">
                            <div v-for="struct in (filteredStructures || [])" :key="struct.id"
                                @click="loadStructure(struct)"
                                class="relative flex cursor-default select-none items-center justify-between rounded-sm px-2 py-2 text-sm outline-none hover:bg-indigo-50 hover:text-indigo-900 dark:hover:bg-indigo-900/50 dark:hover:text-indigo-100 cursor-pointer group transition-colors">
                                <span class="truncate pr-2 font-medium">{{ struct.name }}</span>
                                <Button variant="ghost" size="icon" class="h-7 w-7 opacity-0 group-hover:opacity-100 text-rose-500 hover:text-rose-700 hover:bg-rose-100 transition-opacity" @click.stop="deleteStructure(struct.id)">
                                    <Trash class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                            <div v-if="!filteredStructures || filteredStructures.length === 0" class="py-8 text-center text-sm text-muted-foreground italic">
                                No hay estructuras.
                            </div>
                        </div>
                    </PopoverContent>
                </Popover>

                <Popover v-model:open="open">
                    <PopoverTrigger as-child>
                        <Button variant="outline" class="h-10 justify-between border-slate-200 bg-white shadow-sm hover:border-indigo-300 transition-all">
                            <ChevronDown class="h-4 w-4 mr-2 opacity-50" />
                            Seleccionar Categoría
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent class="w-[300px] p-0 shadow-xl">
                        <div class="flex items-center border-b px-3 bg-slate-50/50">
                            <Search class="mr-2 h-4 w-4 shrink-0 opacity-50 text-indigo-600" />
                            <Input 
                                v-model="searchQuery" 
                                placeholder="Buscar categoría..." 
                                class="flex h-11 w-full rounded-md bg-transparent py-3 text-sm outline-none placeholder:text-muted-foreground border-0 focus-visible:ring-0" 
                            />
                        </div>
                        <div class="max-h-[300px] overflow-y-auto p-1">
                            <div 
                                v-for="category in (filteredCategories || [])" 
                                :key="category?.id"
                                class="relative flex cursor-default select-none items-center rounded-sm px-3 py-2.5 text-sm hover:bg-indigo-50 hover:text-indigo-900 cursor-pointer transition-colors"
                                @click="selectCategory(category)"
                            >
                                {{ category?.name }}
                            </div>
                            <div v-if="filteredCategories?.length === 0" class="py-10 text-center text-sm text-muted-foreground italic">
                                No se encontraron resultados.
                            </div>
                        </div>
                    </PopoverContent>
                </Popover>

                <div class="flex items-center gap-3 h-10 px-3 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-md shadow-sm">
                    <div class="flex items-center gap-2 border-r pr-3 border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] font-bold uppercase text-slate-500 whitespace-nowrap">Precio Venta</span>
                        <Input type="number" v-model="sellingPrice" class="w-20 h-7 text-xs font-bold border-none bg-slate-50/50 focus-visible:ring-0 text-center" placeholder="0.00" />
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-bold uppercase text-indigo-600 dark:text-indigo-400">Margen</span>
                        <span class="text-sm font-black text-slate-900 dark:text-white">{{ costMargin }}%</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <div class="relative group">
                    <Input v-model="structureName" placeholder="Nombre de estructura..." class="h-10 w-[200px] border-slate-200 focus:border-indigo-400 transition-all pl-9" />
                    <Save class="absolute left-3 top-3 h-4 w-4 text-slate-400 group-focus-within:text-indigo-500 transition-colors" />
                </div>
                <Button @click="saveStructure" class="h-10 px-4 bg-indigo-600 hover:bg-indigo-700 text-white shadow-md shadow-indigo-200 dark:shadow-none transition-all flex items-center gap-2 font-semibold">
                    Guardar Estructura
                </Button>
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
                <TableRow v-for="(category, index) in (categoriesSelected || [])" :key="category?.id" class="odd:bg-muted/30 even:bg-transparent hover:bg-muted/50 transition-colors">
                    <TableCell class="w-[105px]">
                        <div class="flex items-center justify-center gap-1">
                            <Button variant="outline" size="icon" class="h-7 w-7 text-muted-foreground hover:text-foreground" :disabled="index === 0" @click="moveCategoryUp(index)">
                                <ChevronUp class="h-4 w-4" />
                            </Button>
                            <span class="font-bold w-6 text-center text-sm">{{ index + 1 }}</span>
                            <Button variant="outline" size="icon" class="h-7 w-7 text-muted-foreground hover:text-foreground" :disabled="index === (categoriesSelected?.length || 0) - 1" @click="moveCategoryDown(index)">
                                <ChevronDown class="h-4 w-4" />
                            </Button>
                        </div>
                    </TableCell>
                    <TableCell class="font-medium text-indigo-600 dark:text-indigo-400">
                        {{ category?.name }}
                    </TableCell>
                    <TableCell class="text-right"> 
                        <div class="flex items-center justify-end gap-2">
                            <Input type="number" v-model="category.reference_volume" class="h-9 w-full rounded-md border border-indigo-200 bg-indigo-50/30 text-center font-medium text-indigo-600 shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-indigo-400 dark:border-indigo-900/50 dark:bg-indigo-900/10 dark:text-indigo-400" />
                            <span class="text-xs font-semibold text-muted-foreground uppercase w-16 text-left">{{ category?.measurement_unit || category?.mesearument_unit || 'UNID' }}</span>
                        </div>
                    </TableCell>
                    <TableCell class="text-right">
                        <Input type="number" v-model="category.ration" class="h-9 rounded-md border border-sky-200 bg-sky-50/30 text-center font-medium text-sky-600 shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-sky-400 dark:border-sky-900/50 dark:bg-sky-900/10 dark:text-sky-400" />
                    </TableCell>
                    <TableCell class="text-right"> 
                        <Input type="number" v-model="category.unit_cost" class="h-9 rounded-md border border-emerald-200 bg-emerald-50/30 text-center font-medium text-emerald-600 shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-emerald-400 dark:border-emerald-900/50 dark:bg-emerald-900/10 dark:text-emerald-400" />
                    </TableCell>
                    <TableCell class="text-right"> 
                        <Input type="number" v-model="category.total_cost" readonly class="h-9 rounded-md border border-rose-200 bg-rose-100 text-center font-semibold text-rose-700 shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-rose-400 dark:border-rose-900/50 dark:bg-rose-900/30 dark:text-rose-400" />
                    </TableCell>
                    <TableCell class="text-right"> 
                        <Input type="number" v-model="category.unit_cost_superior" readonly class="h-9 rounded-md border border-amber-200 bg-amber-50 text-center font-medium text-amber-600 shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-amber-400 dark:border-amber-900/50 dark:bg-amber-900/30 dark:text-amber-400" />
                    </TableCell>
                    <TableCell class="text-right"> 
                        <Input type="number" v-model="category.total_cost_superior" readonly class="h-9 rounded-md border border-orange-200 bg-orange-100 text-center font-semibold text-orange-700 shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-orange-400 dark:border-orange-900/50 dark:bg-orange-900/30 dark:text-orange-400" />
                    </TableCell>
                    <TableCell class="text-right">
                        <Button variant="ghost" size="icon" class="h-8 w-8 text-muted-foreground hover:text-destructive" @click="deleteCategory(index)">
                            <Trash class="h-4 w-4" />
                        </Button>
                    </TableCell>
                </TableRow>
            </TableBody>
            <TableFooter v-if="(categoriesSelected?.length || 0) > 0">
                <TableRow class="hover:bg-transparent bg-slate-50/50 dark:bg-slate-900/10">
                    <TableCell colspan="5" class="text-right font-bold text-xs uppercase text-slate-500 pr-4">Resumen de Costos:</TableCell>
                    <TableCell class="text-right p-2">
                        <div class="flex flex-col items-center">
                            <span class="text-[9px] font-black uppercase text-rose-600 mb-0.5">Costo Inferior</span>
                            <div class="h-8 w-full rounded border-2 border-rose-200 bg-rose-50 text-center font-bold text-rose-700 shadow-sm flex items-center justify-center dark:border-rose-900/50 dark:bg-rose-900/30 dark:text-rose-400 text-sm">
                                {{ totalBaseCost }}
                            </div>
                        </div>
                    </TableCell>
                    <TableCell class="text-right"></TableCell>
                    <TableCell class="text-right p-2">
                        <div class="flex flex-col items-center">
                            <span class="text-[9px] font-black uppercase text-amber-600 mb-0.5">Costo Superior</span>
                            <div class="h-8 w-full rounded border-2 border-amber-200 bg-amber-50 text-center font-bold text-amber-700 shadow-sm flex items-center justify-center dark:border-amber-900/50 dark:bg-amber-900/30 dark:text-amber-400 text-sm">
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
