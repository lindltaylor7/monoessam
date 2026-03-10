<script lang="ts" setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useForm } from '@inertiajs/vue3';
import { 
    Building2, 
    PackagePlus, 
    Pencil, 
    Plus, 
    Trash2, 
    Search, 
    Loader2, 
    Upload, 
    MapPin, 
    ChevronLeft, 
    ChevronRight,
    Filter,
    ArrowUpDown
} from 'lucide-vue-next';
import { 
    Table, 
    TableBody, 
    TableCell, 
    TableHead, 
    TableHeader, 
    TableRow 
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { computed, ref, inject, watch } from 'vue';
import axios from 'axios';
import { Head } from '@inertiajs/vue3';

const swal: any = inject('$swal');

const props = defineProps({
    ingredient_city_providers: {
        type: Array as () => any[],
        required: true,
        default: () => [],
    },
    providers: {
        type: Array as () => any[],
        default: () => [],
    },
    ingredients: {
        type: Array as () => any[],
        default: () => [],
    },
    cities: {
        type: Array as () => any[],
        default: () => [],
    },
    measurement_units: {
        type: Array as () => any[],
        default: () => [],
    },
});

// Modals state
const isProviderModalOpen = ref(false);
const isAssignmentModalOpen = ref(false);
const editingProvider = ref<any>(null);
const editingAssignmentId = ref<number | null>(null);
const isImportModalOpen = ref(false);

// Form for Providers
const providerForm = useForm({
    name: '',
    email: '',
    phone: '',
});

// Form for Assignments
const assignmentForm = useForm({
    ingredient_id: '',
    provider_id: '',
    city_id: '',
    cost_price: '',
    measurement_unit_id: '',
});

const importForm = useForm({
    excel_file: null as File | null,
    city_id: '',
});

const importIdsForm = useForm({
    excel_file: null as File | null,
});

// Search state
const ingredientSearch = ref('');
const searchedIngredients = ref<any[]>([]);
const isSearching = ref(false);
const selectedIngredientName = ref('');

// Global Search, Filtering and Pagination
const globalSearch = ref('');
const selectedCityId = ref('all');
const selectedProviderId = ref('all');
const currentPage = ref(1);
const itemsPerPage = 15;

const filteredAssignments = computed(() => {
    return props.ingredient_city_providers.filter(item => {
        if (!item.ingredient || !item.provider || !item.city) return false;
        
        const matchesSearch = `${item.ingredient.name} ${item.provider.name} ${item.city.name}`
            .toLowerCase()
            .includes(globalSearch.value.toLowerCase());
            
        const matchesCity = selectedCityId.value === 'all' || item.city_id.toString() === selectedCityId.value;
        const matchesProvider = selectedProviderId.value === 'all' || item.provider_id.toString() === selectedProviderId.value;
        
        return matchesSearch && matchesCity && matchesProvider;
    });
});

const totalPages = computed(() => Math.ceil(filteredAssignments.value.length / itemsPerPage));

const paginatedAssignments = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return filteredAssignments.value.slice(start, end);
});

watch([globalSearch, selectedCityId, selectedProviderId], () => {
    currentPage.value = 1;
});

// Methods for Providers
const openCreateProviderModal = () => {
    editingProvider.value = null;
    providerForm.reset();
    providerForm.clearErrors();
    isProviderModalOpen.value = true;
};

const openEditProviderModal = (provider: any) => {
    editingProvider.value = provider;
    providerForm.name = provider.name;
    providerForm.email = provider.email || '';
    providerForm.phone = provider.phone || '';
    providerForm.clearErrors();
    isProviderModalOpen.value = true;
};

const submitProviderForm = () => {
    if (editingProvider.value) {
        providerForm.put(route('providers.update', editingProvider.value.id), {
            onSuccess: () => {
                isProviderModalOpen.value = false;
                swal.fire('¡Éxito!', 'Proveedor actualizado correctamente', 'success');
            },
        });
    } else {
        providerForm.post(route('providers.store'), {
            onSuccess: () => {
                isProviderModalOpen.value = false;
                swal.fire('¡Éxito!', 'Proveedor registrado correctamente', 'success');
            },
        });
    }
};

const deleteProvider = (id: number) => {
    swal.fire({
        title: '¿Estás seguro?',
        text: 'Esta acción no se puede deshacer y podría eliminar las relaciones con ingredientes.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
    }).then((result: any) => {
        if (result.isConfirmed) {
            useForm({}).delete(route('providers.destroy', id), {
                onSuccess: () => {
                    swal.fire('Eliminado', 'El proveedor ha sido eliminado.', 'success');
                },
            });
        }
    });
};

// Methods for Assignments
const openCreateAssignmentModal = (providerId?: number) => {
    editingAssignmentId.value = null;
    assignmentForm.reset();
    if (providerId) assignmentForm.provider_id = providerId.toString();
    assignmentForm.clearErrors();
    ingredientSearch.value = '';
    searchedIngredients.value = [];
    selectedIngredientName.value = '';
    isAssignmentModalOpen.value = true;
};

const openEditAssignmentModal = (assignment: any, providerId: number, cityId: number) => {
    editingAssignmentId.value = assignment.assignment_id;
    assignmentForm.ingredient_id = assignment.id.toString();
    assignmentForm.provider_id = providerId.toString();
    assignmentForm.city_id = cityId.toString();
    assignmentForm.cost_price = assignment.cost_price.toString();
    assignmentForm.measurement_unit_id = (assignment.measurement_unit_id || '').toString();
    selectedIngredientName.value = assignment.name;
    ingredientSearch.value = assignment.name;
    assignmentForm.clearErrors();
    isAssignmentModalOpen.value = true;
};

// Search Logic
let searchTimeout: any = null;
const searchIngredients = async (query: string) => {
    if (!query || query.length < 2) {
        searchedIngredients.value = [];
        return;
    }

    isSearching.value = true;
    try {
        const response = await axios.get(route('ingredients.search', { word: query }));
        searchedIngredients.value = response.data;
    } catch (error) {
        console.error('Error searching ingredients:', error);
    } finally {
        isSearching.value = false;
    }
};

watch(ingredientSearch, (newVal) => {
    if (newVal === selectedIngredientName.value) return;
    
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        searchIngredients(newVal);
    }, 300);
});

const selectIngredient = (ingredient: any) => {
    assignmentForm.ingredient_id = ingredient.id.toString();
    selectedIngredientName.value = ingredient.name;
    ingredientSearch.value = ingredient.name;
    searchedIngredients.value = [];
};

const submitAssignmentForm = () => {
    if (editingAssignmentId.value) {
        assignmentForm.put(route('providers.assign.update', editingAssignmentId.value), {
            onSuccess: () => {
                isAssignmentModalOpen.value = false;
                swal.fire('¡Éxito!', 'Asignación actualizada correctamente', 'success');
            },
        });
    } else {
        assignmentForm.post(route('providers.assign'), {
            onSuccess: () => {
                isAssignmentModalOpen.value = false;
                swal.fire('¡Éxito!', 'Producto asignado correctamente', 'success');
            },
        });
    }
};

const deleteAssignment = (id: number) => {
    swal.fire({
        title: '¿Eliminar asignación?',
        text: 'Se removerá el producto de la lista de este proveedor en esta ciudad.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar',
    }).then((result: any) => {
        if (result.isConfirmed) {
            useForm({}).delete(route('providers.assign.destroy', id), {
                onSuccess: () => {
                    swal.fire('Eliminado', 'La asignación ha sido eliminada.', 'success');
                },
            });
        }
    });
};

const fileInput = ref<HTMLInputElement | null>(null);
const triggerImport = () => {
    isImportModalOpen.value = true;
};

const handleImport = () => {
    if (!importForm.city_id) {
        swal.fire('Atención', 'Seleccione una ciudad para la importación.', 'warning');
        return;
    }
    fileInput.value?.click();
};

const onFileChange = (e: any) => {
    const file = e.target.files[0];
    if (file) {
        importForm.excel_file = file;
        importForm.post(route('providers.import'), {
            onSuccess: () => {
                isImportModalOpen.value = false;
                importForm.reset();
                swal.fire('¡Éxito!', 'Los datos se han importado correctamente.', 'success');
            },
            onError: (err) => {
                console.error(err);
                swal.fire('Error', 'Hubo un problema al importar el archivo.', 'error');
            }
        });
    }
};

const fileInputIds = ref<HTMLInputElement | null>(null);
const triggerImportIds = () => {
    fileInputIds.value?.click();
};

const onFileIdsChange = (e: any) => {
    const file = e.target.files[0];
    if (file) {
        importIdsForm.excel_file = file;
        importIdsForm.post(route('providers.import-ids'), {
            onSuccess: () => {
                importIdsForm.reset();
                swal.fire('¡Éxito!', 'Importación por IDs completada.', 'success');
            },
            onError: (err) => {
                console.error(err);
                swal.fire('Error', 'Hubo un problema al importar con IDs.', 'error');
            }
        });
    }
};
</script>

<template>
    <Head title="Proveedores" />
    <AppLayout>
        <div class="p-6">
            <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Gestión de Proveedores</h1>
                    <p class="text-gray-500">Administra tus proveedores y los productos que ofrecen por ciudad.</p>
                </div>
                <div class="flex gap-3">
                    <Button @click="openCreateProviderModal" variant="outline" class="flex gap-2">
                        <Building2 class="h-4 w-4" />
                        Nuevo Proveedor
                    </Button>
                    <input type="file" ref="fileInput" class="hidden" @change="onFileChange" accept=".xlsx, .xls, .csv" />
                    <Button @click="triggerImport" variant="outline" class="flex gap-2">
                        <Upload class="h-4 w-4" />
                        Importar Excel (Nombres)
                    </Button>
                    <input type="file" ref="fileInputIds" class="hidden" @change="onFileIdsChange" accept=".xlsx, .xls, .csv" />
                    <Button @click="triggerImportIds" variant="outline" class="flex gap-2 bg-indigo-50 border-indigo-200 text-indigo-700 hover:bg-indigo-100">
                        <Upload class="h-4 w-4" />
                        Importar Excel (IDs)
                    </Button>
                    <Button @click="openCreateAssignmentModal()">
                        <Plus class="mr-2 h-4 w-4" />
                        Asignar Producto
                    </Button>
                </div>
            </div>

            <div class="mb-6 flex flex-col xl:flex-row gap-4 items-center bg-white/80 backdrop-blur-sm p-4 rounded-2xl border border-gray-200 shadow-sm">
                <!-- Barra de Búsqueda -->
                <div class="relative w-full xl:max-w-md group">
                    <Search class="absolute left-3.5 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-gray-400 group-focus-within:text-blue-500 transition-colors" />
                    <Input 
                        v-model="globalSearch" 
                        placeholder="Buscar ingredientes, proveedores..." 
                        class="pl-11 h-11 bg-gray-50/50 border-gray-200 focus:bg-white transition-all rounded-xl focus:ring-4 focus:ring-blue-500/10"
                    />
                </div>
                
                <!-- Filtros Selectores -->
                <div class="flex flex-wrap items-center gap-3 w-full xl:w-auto">
                    <!-- Filtro Ciudad -->
                    <div class="flex items-center gap-2.5 bg-gray-50/50 border border-gray-200 rounded-xl px-3 hover:bg-white hover:border-blue-300 transition-all focus-within:ring-4 focus-within:ring-blue-500/10">
                        <MapPin class="h-4 w-4 text-blue-500 shrink-0" />
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Ciudad</span>
                        <Separator orientation="vertical" class="h-4 bg-gray-200" />
                        <Select v-model="selectedCityId">
                            <SelectTrigger class="border-0 bg-transparent shadow-none focus:ring-0 p-0 h-10 w-[140px] text-gray-700 font-medium">
                                <SelectValue placeholder="Seleccionar" />
                            </SelectTrigger>
                            <SelectContent class="rounded-xl border-gray-200 shadow-xl">
                                <SelectItem value="all" class="font-medium text-blue-600">Todas las ciudades</SelectItem>
                                <Separator class="my-1" />
                                <SelectItem v-for="city in cities" :key="city.id" :value="city.id.toString()">
                                    {{ city.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Filtro Proveedor -->
                    <div class="flex items-center gap-2.5 bg-gray-50/50 border border-gray-200 rounded-xl px-3 hover:bg-white hover:border-indigo-300 transition-all focus-within:ring-4 focus-within:ring-indigo-500/10">
                        <Building2 class="h-4 w-4 text-indigo-500 shrink-0" />
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Prov</span>
                        <Separator orientation="vertical" class="h-4 bg-gray-200" />
                        <Select v-model="selectedProviderId">
                            <SelectTrigger class="border-0 bg-transparent shadow-none focus:ring-0 p-0 h-10 w-[160px] text-gray-700 font-medium">
                                <SelectValue placeholder="Seleccionar" />
                            </SelectTrigger>
                            <SelectContent class="rounded-xl border-gray-200 shadow-xl">
                                <SelectItem value="all" class="font-medium text-indigo-600">Todos proveedores</SelectItem>
                                <Separator class="my-1" />
                                <SelectItem v-for="provider in providers" :key="provider.id" :value="provider.id.toString()">
                                    {{ provider.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Botón Limpiar Filtros -->
                    <Button 
                        v-if="selectedCityId !== 'all' || selectedProviderId !== 'all' || globalSearch"
                        variant="ghost" 
                        size="sm"
                        @click="() => { globalSearch = ''; selectedCityId = 'all'; selectedProviderId = 'all'; }"
                        class="text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg text-xs"
                    >
                        Limpiar filtros
                    </Button>
                </div>

                <div class="flex-1 hidden xl:block"></div>

                <div class="flex items-center gap-3 px-4 py-2 bg-blue-50/50 rounded-xl border border-blue-100 whitespace-nowrap">
                    <Filter class="h-4 w-4 text-blue-500" />
                    <span class="text-sm font-semibold text-blue-700">{{ filteredAssignments.length }}</span>
                    <span class="text-xs text-blue-600/70 font-medium uppercase tracking-tighter">Resultados</span>
                </div>
            </div>

            <Card class="overflow-hidden border-gray-200 shadow-xl">
                <CardContent class="p-0">
                    <Table>
                        <TableHeader class="bg-gray-50 border-b border-gray-200">
                            <TableRow>
                                <TableHead class="font-bold text-gray-700">Ingrediente</TableHead>
                                <TableHead class="font-bold text-gray-700">Proveedor</TableHead>
                                <TableHead class="font-bold text-gray-700">Ciudad</TableHead>
                                <TableHead class="font-bold text-gray-700 text-center">UM</TableHead>
                                <TableHead class="font-bold text-gray-700 text-right">Precio Costo</TableHead>
                                <TableHead class="font-bold text-gray-700 text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="item in paginatedAssignments" :key="item.id" class="hover:bg-blue-50/30 transition-colors group">
                                <TableCell>
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-900">{{ item.ingredient.name }}</span>
                                        <span class="text-xs text-gray-500 truncate max-w-[200px]">{{ item.ingredient.description }}</span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-2">
                                        <Badge variant="outline" class="bg-slate-100 text-slate-700 font-medium">
                                            {{ item.provider.name }}
                                        </Badge>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-1.5 text-gray-600 font-medium">
                                        <MapPin class="h-3.5 w-3.5 text-blue-500" />
                                        {{ item.city.name }}
                                    </div>
                                </TableCell>
                                <TableCell class="text-center">
                                    <Badge variant="secondary" class="bg-gray-100 text-gray-600">
                                        {{ item.measurement_unit ? item.measurement_unit.name : (item.ingredient.measurement_unit || '-') }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right">
                                    <span class="text-lg font-black text-blue-600">S/ {{ parseFloat(item.cost_price).toFixed(2) }}</span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <Button 
                                            variant="ghost" 
                                            size="icon" 
                                            @click="openEditAssignmentModal(item, item.provider_id, item.city_id)"
                                            class="h-8 w-8 text-blue-600 hover:bg-blue-50"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button 
                                            variant="ghost" 
                                            size="icon" 
                                            @click="deleteAssignment(item.id)"
                                            class="h-8 w-8 text-red-600 hover:bg-red-50"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="filteredAssignments.length === 0">
                                <TableCell colspan="6" class="h-64 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-400">
                                        <Search class="h-12 w-12 mb-2 opacity-20" />
                                        <p class="text-lg font-medium">No se encontraron resultados</p>
                                        <p class="text-sm">Intenta ajustar tu búsqueda o filtros</p>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Pagination -->
            <div v-if="totalPages > 1" class="mt-6 flex items-center justify-between p-4 bg-white rounded-xl border border-gray-200">
                <div class="text-sm text-gray-500">
                    Mostrando <span class="font-bold text-gray-900">{{ (currentPage - 1) * itemsPerPage + 1 }}</span> a 
                    <span class="font-bold text-gray-900">{{ Math.min(currentPage * itemsPerPage, filteredAssignments.length) }}</span> de 
                    <span class="font-bold text-gray-900">{{ filteredAssignments.length }}</span> registros
                </div>
                <div class="flex gap-2">
                    <Button 
                        variant="outline" 
                        size="sm" 
                        :disabled="currentPage === 1" 
                        @click="currentPage--"
                        class="gap-1 rounded-lg"
                    >
                        <ChevronLeft class="h-4 w-4" /> Anterior
                    </Button>
                    <div class="flex items-center gap-1 mx-2">
                        <Button 
                            v-for="page in totalPages" 
                            :key="page"
                            v-show="page === 1 || page === totalPages || (page >= currentPage - 1 && page <= currentPage + 1)"
                            size="sm"
                            :variant="currentPage === page ? 'default' : 'ghost'"
                            @click="currentPage = page"
                            class="h-8 w-8 p-0 rounded-md"
                        >
                            {{ page }}
                        </Button>
                    </div>
                    <Button 
                        variant="outline" 
                        size="sm" 
                        :disabled="currentPage === totalPages" 
                        @click="currentPage++"
                        class="gap-1 rounded-lg"
                    >
                        Siguiente <ChevronRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="!ingredient_city_providers.length" class="flex flex-col items-center justify-center py-24 text-center">
                <div class="mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-slate-100 text-slate-300">
                    <PackagePlus class="h-10 w-10" />
                </div>
                <h3 class="text-xl font-semibold text-gray-900">No hay proveedores con productos</h3>
                <p class="mt-1 max-w-xs text-gray-500">Comienza registrando un proveedor y asignándole ingredientes por ciudad.</p>
                <div class="mt-6 flex gap-4">
                    <Button @click="openCreateProviderModal" variant="outline">Nuevo Proveedor</Button>
                    <Button @click="openCreateAssignmentModal()">Asignar Producto</Button>
                </div>
            </div>
        </div>

        <!-- Provider Modal -->
        <Dialog :open="isProviderModalOpen" @update:open="isProviderModalOpen = $event">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>{{ editingProvider ? 'Editar Proveedor' : 'Nuevo Proveedor' }}</DialogTitle>
                </DialogHeader>
                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="provider_name">Nombre del Proveedor</Label>
                        <Input id="provider_name" v-model="providerForm.name" placeholder="Ej. Alimentos del Norte S.A.C." />
                        <span v-if="providerForm.errors.name" class="text-xs text-red-500">{{ providerForm.errors.name }}</span>
                    </div>
                    <div class="grid gap-2">
                        <Label for="provider_email">Email (Opcional)</Label>
                        <Input id="provider_email" v-model="providerForm.email" type="email" placeholder="contacto@proveedor.com" />
                        <span v-if="providerForm.errors.email" class="text-xs text-red-500">{{ providerForm.errors.email }}</span>
                    </div>
                    <div class="grid gap-2">
                        <Label for="provider_phone">Teléfono (Opcional)</Label>
                        <Input id="provider_phone" v-model="providerForm.phone" placeholder="999 888 777" />
                        <span v-if="providerForm.errors.phone" class="text-xs text-red-500">{{ providerForm.errors.phone }}</span>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="ghost" @click="isProviderModalOpen = false">Cancelar</Button>
                    <Button @click="submitProviderForm" :disabled="providerForm.processing">
                        {{ editingProvider ? 'Guardar Cambios' : 'Registrar Proveedor' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Assignment Modal -->
        <Dialog :open="isAssignmentModalOpen" @update:open="isAssignmentModalOpen = $event">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>{{ editingAssignmentId ? 'Editar Asignación' : 'Asignar Producto a Proveedor' }}</DialogTitle>
                </DialogHeader>
                <div class="grid gap-5 py-4">
                    <div class="grid gap-2">
                        <Label>Proveedor</Label>
                        <Select v-model="assignmentForm.provider_id" :disabled="!!editingAssignmentId">
                            <SelectTrigger>
                                <SelectValue placeholder="Seleccionar proveedor" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="provider in providers" :key="provider.id" :value="provider.id.toString()">
                                    {{ provider.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <span v-if="assignmentForm.errors.provider_id" class="text-xs text-red-500">{{ assignmentForm.errors.provider_id }}</span>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Ciudad</Label>
                            <Select v-model="assignmentForm.city_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar ciudad" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="city in cities" :key="city.id" :value="city.id.toString()">
                                        {{ city.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <span v-if="assignmentForm.errors.city_id" class="text-xs text-red-500">{{ assignmentForm.errors.city_id }}</span>
                        </div>
                        <div class="relative grid gap-2">
                            <Label>Ingrediente / Producto</Label>
                            <div class="relative">
                                <Search class="absolute top-2.5 left-2.5 h-4 w-4 text-gray-400" />
                                <Input 
                                    v-model="ingredientSearch" 
                                    placeholder="Buscar ingrediente..." 
                                    class="pl-9"
                                    autocomplete="off"
                                />
                                <Loader2 v-if="isSearching" class="absolute top-2.5 right-2.5 h-4 w-4 animate-spin text-gray-400" />
                            </div>
                            
                            <!-- Search Results -->
                            <div v-if="searchedIngredients.length > 0" class="absolute top-full z-50 mt-1 max-h-48 w-full overflow-y-auto rounded-md border bg-white shadow-lg">
                                <div 
                                    v-for="ing in searchedIngredients" 
                                    :key="ing.id" 
                                    @click="selectIngredient(ing)"
                                    class="cursor-pointer px-4 py-2 text-sm hover:bg-slate-100"
                                >
                                    <div class="font-medium">{{ ing.name }}</div>
                                    <div class="text-xs text-gray-500">{{ ing.description || 'Sin descripción' }}</div>
                                </div>
                            </div>

                            <span v-if="assignmentForm.errors.ingredient_id" class="text-xs text-red-500">{{ assignmentForm.errors.ingredient_id }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="cost_price">Precio de Costo (S/)</Label>
                            <Input id="cost_price" v-model="assignmentForm.cost_price" type="number" step="0.01" placeholder="0.00" />
                            <span v-if="assignmentForm.errors.cost_price" class="text-xs text-red-500">{{ assignmentForm.errors.cost_price }}</span>
                        </div>
                        <div class="grid gap-2">
                            <Label>Unidad de Medida</Label>
                            <Select v-model="assignmentForm.measurement_unit_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Opcional" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="um in measurement_units" :key="um.id" :value="um.id.toString()">
                                        {{ um.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <span v-if="assignmentForm.errors.measurement_unit_id" class="text-xs text-red-500">{{ assignmentForm.errors.measurement_unit_id }}</span>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="ghost" @click="isAssignmentModalOpen = false">Cancelar</Button>
                    <Button @click="submitAssignmentForm" :disabled="assignmentForm.processing">
                        {{ editingAssignmentId ? 'Guardar Cambios' : 'Asignar Producto' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Import Modal -->
        <Dialog :open="isImportModalOpen" @update:open="isImportModalOpen = $event">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Importar Proveedores e Ingredientes</DialogTitle>
                </DialogHeader>
                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label>Ciudad de Importación</Label>
                        <Select v-model="importForm.city_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Seleccionar ciudad" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="city in cities" :key="city.id" :value="city.id.toString()">
                                    {{ city.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Nota: El Excel debe contener en las primeras 4 columnas los nombres de proveedores, la 5ta el ingrediente y los precios en las últimas 4 columnas (si no hay precio, la fila se ignora).
                    </p>
                </div>
                <DialogFooter>
                    <Button variant="ghost" @click="isImportModalOpen = false">Cancelar</Button>
                    <Button @click="handleImport" :disabled="importForm.processing">
                        <template v-if="importForm.processing">
                            <Loader2 class="mr-2 h-4 w-4 animate-spin" />
                            Importando...
                        </template>
                        <template v-else>
                            Seleccionar Archivo
                        </template>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>


