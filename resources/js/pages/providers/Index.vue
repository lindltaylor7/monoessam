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
import { Building2, PackagePlus, Pencil, Plus, Trash2, Search, Loader2 } from 'lucide-vue-next';
import { computed, ref, inject, watch } from 'vue';
import axios from 'axios';

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

// Search state
const ingredientSearch = ref('');
const searchedIngredients = ref<any[]>([]);
const isSearching = ref(false);
const selectedIngredientName = ref('');

// Agrupar por proveedor
const groupedByProvider = computed(() => {
    const grouped: Record<number, any> = {};

    props.ingredient_city_providers.forEach((item) => {
        const providerId = item.provider_id;
        if (!grouped[providerId]) {
            grouped[providerId] = {
                provider: item.provider,
                cities: {},
            };
        }

        const cityId = item.city_id;
        if (!grouped[providerId].cities[cityId]) {
            grouped[providerId].cities[cityId] = {
                city: item.city,
                ingredients: [],
            };
        }

        grouped[providerId].cities[cityId].ingredients.push({
            ...item.ingredient,
            assignment_id: item.id,
            cost_price: item.cost_price,
            presentation: item.ingredient.presentation,
            calories: item.ingredient.calories,
            measurement_unit: item.measurement_unit ? item.measurement_unit.name : item.ingredient.measurement_unit,
            measurement_unit_id: item.measurement_unit_id,
            liquid_waste: item.ingredient.liquid_waste,
            solid_waste: item.ingredient.solid_waste,
        });
    });

    return grouped;
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
</script>

<template>
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
                    <Button @click="openCreateAssignmentModal()">
                        <Plus class="mr-2 h-4 w-4" />
                        Asignar Producto
                    </Button>
                </div>
            </div>

            <div v-for="(providerData, providerId) in groupedByProvider" :key="providerId" class="mb-10 last:mb-0">
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl">
                    <!-- Provider Header -->
                    <div class="bg-gradient-to-r from-slate-800 to-slate-900 px-6 py-5 text-white">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/10 text-xl font-bold">
                                    {{ providerData.provider.name.charAt(0) }}
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold">{{ providerData.provider.name }}</h2>
                                    <div class="mt-1 flex gap-4 text-xs text-slate-300">
                                        <span v-if="providerData.provider.email" class="flex items-center gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                            {{ providerData.provider.email }}
                                        </span>
                                        <span v-if="providerData.provider.phone" class="flex items-center gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                            </svg>
                                            {{ providerData.provider.phone }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <Button @click="openEditProviderModal(providerData.provider)" variant="ghost" size="icon" class="h-9 w-9 text-slate-300 hover:bg-white/10 hover:text-white">
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button @click="deleteProvider(providerData.provider.id)" variant="ghost" size="icon" class="h-9 w-9 text-red-300 hover:bg-red-500/20 hover:text-red-400">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                                <Button @click="openCreateAssignmentModal(providerData.provider.id)" variant="secondary" size="sm" class="ml-2 gap-1.5 px-3">
                                    <Plus class="h-3.5 w-3.5" />
                                    Agregar Producto
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Cities & Ingredients -->
                    <div class="p-6">
                        <div v-for="(cityData, cityId) in providerData.cities" :key="cityId" class="mb-8 last:mb-0">
                            <div class="mb-4 flex items-center gap-3">
                                <div class="h-2 w-10 rounded-full bg-blue-500"></div>
                                <h3 class="text-lg font-bold text-gray-800">{{ cityData.city.name }}</h3>
                            </div>

                            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                <div v-for="ingredient in cityData.ingredients" :key="ingredient.assignment_id" class="group relative flex items-start justify-between rounded-xl border border-gray-100 bg-slate-50 p-4 transition-all hover:border-blue-200 hover:bg-white hover:shadow-md">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-gray-900">{{ ingredient.name }}</span>
                                            <span class="rounded-lg bg-green-100 px-2 py-0.5 text-xs font-bold text-green-700">S/ {{ ingredient.cost_price }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">{{ ingredient.description }}</p>
                                        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600">
                                            <span><strong class="text-gray-400">Pres:</strong> {{ ingredient.presentation }}</span>
                                            <span><strong class="text-gray-400">UM:</strong> {{ ingredient.measurement_unit }}</span>
                                            <span><strong class="text-gray-400">Cal:</strong> {{ ingredient.calories }} kcal</span>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex flex-col gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                        <Button @click="openEditAssignmentModal(ingredient, providerData.provider.id, cityData.city.id)" variant="ghost" size="icon" class="h-8 w-8 text-gray-400 hover:text-blue-600">
                                            <Pencil class="h-3.5 w-3.5" />
                                        </Button>
                                        <Button @click="deleteAssignment(ingredient.assignment_id)" variant="ghost" size="icon" class="h-8 w-8 text-gray-400 hover:text-red-500">
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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
    </AppLayout>
</template>


