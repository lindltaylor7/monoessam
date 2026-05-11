<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow, TableFooter } from '@/components/ui/table';
import { Trash, ChevronDown, ChevronUp, Search, Save } from 'lucide-vue-next';
import { ref, computed } from 'vue';
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
    categoriesSelected.value = struct.costs.map((cost: any) => ({
        id: cost.dish_category_id,
        name: cost.name || 'Categoría guardada', // if relations weren't loaded, at least we have the name saved in db
        reference_volume: cost.reference_volume,
        measurement_unit: cost.measurement_unit,
        ration: cost.ration,
        unit_cost: cost.unit_cost,
        total_cost: cost.total_cost
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
        <div class="flex items-center justify-between gap-4 w-full">
            <div class="flex gap-2">
                <Popover v-model:open="openStructureSearch">
                    <PopoverTrigger as-child>
                        <Button variant="outline" class="w-[220px] justify-between text-indigo-600 border-indigo-200 bg-indigo-50/50 hover:bg-indigo-100 dark:bg-indigo-900/20 dark:border-indigo-800">
                            Estructuras Guardadas
                            <Search class="h-4 w-4 opacity-50" />
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent class="w-[250px] p-0">
                        <div class="max-h-[300px] overflow-y-auto p-1">
                            <div v-for="struct in (filteredStructures || [])" :key="struct.id"
                                @click="loadStructure(struct)"
                                class="relative flex cursor-default select-none items-center justify-between rounded-sm px-2 py-1.5 text-sm outline-none hover:bg-indigo-100 hover:text-indigo-900 dark:hover:bg-indigo-900 dark:hover:text-indigo-100 cursor-pointer group">
                                <span class="truncate pr-2">{{ struct.name }}</span>
                                <Button variant="ghost" size="icon" class="h-6 w-6 opacity-0 group-hover:opacity-100 text-rose-500 hover:text-rose-700 hover:bg-rose-100 dark:hover:bg-rose-900/50 transition-opacity" @click.stop="deleteStructure(struct.id)">
                                    <Trash class="h-3 w-3" />
                                </Button>
                            </div>
                            <div v-if="!filteredStructures || filteredStructures.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                                No hay estructuras.
                            </div>
                        </div>
                    </PopoverContent>
                </Popover>

                <Popover v-model:open="open">
                    <PopoverTrigger as-child>
                        <Button variant="outline" class="w-[220px] justify-between">
                            Seleccionar Categoría
                            <ChevronDown class="h-4 w-4 opacity-50" />
                        </Button>
                    </PopoverTrigger>
            <PopoverContent class="w-[300px] p-0">
                <div class="flex items-center border-b px-3">
                    <Search class="mr-2 h-4 w-4 shrink-0 opacity-50" />
                    <Input 
                        v-model="searchQuery" 
                        placeholder="Buscar categoría..." 
                        class="flex h-11 w-full rounded-md bg-transparent py-3 text-sm outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50 border-0 focus-visible:ring-0 focus-visible:ring-offset-0" 
                    />
                </div>
                <div class="max-h-[300px] overflow-y-auto p-1">
                    <div 
                        v-for="category in (filteredCategories || [])" 
                        :key="category?.id"
                        class="relative flex cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none hover:bg-accent hover:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50 cursor-pointer"
                        @click="selectCategory(category)"
                    >
                        {{ category?.name }}
                    </div>
                    <div v-if="filteredCategories?.length === 0" class="py-6 text-center text-sm">
                        No se encontraron resultados.
                    </div>
                </div>
            </PopoverContent>
        </Popover>
        </div>

        <div class="flex items-center gap-2">
            <Input v-model="structureName" placeholder="Nombre de estructura..." class="w-[200px]" />
            <Button @click="saveStructure" class="flex items-center gap-2">
                <Save class="h-4 w-4" />
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
                    <TableHead>Ración</TableHead>
                    <TableHead>Costo unitario</TableHead>
                    <TableHead>Costo Total</TableHead>
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
                        <Input type="number" v-model="category.total_cost" class="h-9 rounded-md border border-rose-200 bg-rose-100 text-center font-semibold text-rose-700 shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-rose-400 dark:border-rose-900/50 dark:bg-rose-900/30 dark:text-rose-400" />
                    </TableCell>
                    <TableCell class="text-right">
                        <Button variant="ghost" size="icon" class="h-8 w-8 text-muted-foreground hover:text-destructive" @click="deleteCategory(index)">
                            <Trash class="h-4 w-4" />
                        </Button>
                    </TableCell>
                </TableRow>
            </TableBody>
            <TableFooter v-if="(categoriesSelected?.length || 0) > 0">
                <TableRow class="hover:bg-transparent">
                    <TableCell colspan="5" class="text-right font-bold text-sm uppercase text-indigo-900 dark:text-indigo-100 pr-4">Costo Total Base</TableCell>
                    <TableCell class="text-right">
                        <div class="h-10 rounded-md border-2 border-rose-300 bg-rose-100/80 text-center font-bold text-rose-800 shadow-sm flex items-center justify-center dark:border-rose-900/80 dark:bg-rose-900/50 dark:text-rose-300 text-base">
                            {{ totalBaseCost }}
                        </div>
                    </TableCell>
                    <TableCell></TableCell>
                </TableRow>
            </TableFooter>
            <TableCaption>Lista de categorías de la nueva estructura.</TableCaption>
        </Table>
    </div>
</template>
