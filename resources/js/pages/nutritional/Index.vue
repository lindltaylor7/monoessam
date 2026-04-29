<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps<{
    groupedIngredients: any;
    ingredients: any[];
    filters: any;
}>();

const isModalOpen = ref(false);
const isEditing = ref(false);

const ingredientSearch = ref('');
const showIngredientDropdown = ref(false);

const tableSearch = ref(props.filters.search || '');

// Buscador de la tabla con debounce
let searchTimeout: any = null;
watch(tableSearch, (value) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('nutritional.index'), { search: value }, {
            preserveState: true,
            replace: true
        });
    }, 300);
});

const form = useForm({
    id: null as number | null,
    ingredient_id: '' as number | string,
    nfactorcal: '',
    composition: '',
});

const filteredIngredients = computed(() => {
    if (!ingredientSearch.value) return props.ingredients.slice(0, 50);
    const search = ingredientSearch.value.toLowerCase();
    return props.ingredients.filter(ing => ing.name.toLowerCase().includes(search)).slice(0, 50);
});

const selectIngredient = (ing: any) => {
    form.ingredient_id = ing.id;
    ingredientSearch.value = ing.name;
    showIngredientDropdown.value = false;
};

const hideIngredientDropdownDelay = () => {
    setTimeout(() => {
        showIngredientDropdown.value = false;
        const found = props.ingredients.find(i => i.id === form.ingredient_id);
        if (found) {
            ingredientSearch.value = found.name;
        } else {
            ingredientSearch.value = '';
            form.ingredient_id = '';
        }
    }, 200);
};

const openCreateModal = () => {
    form.reset();
    form.clearErrors();
    ingredientSearch.value = '';
    isEditing.value = false;
    isModalOpen.value = true;
};

const openEditModal = (factor: any) => {
    form.reset();
    form.clearErrors();
    form.id = factor.id;
    form.ingredient_id = factor.ingredient_id;
    form.nfactorcal = factor.nfactorcal || '';
    form.composition = factor.composition || '';
    const found = props.ingredients.find(i => i.id === factor.ingredient_id);
    ingredientSearch.value = found ? found.name : '';
    isEditing.value = true;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
};

const submitForm = () => {
    if (!form.ingredient_id) {
        form.setError('ingredient_id', 'Por favor, selecciona un ingrediente válido de la lista.');
        return;
    }
    
    if (isEditing.value && form.id) {
        form.put(route('nutritional.update', form.id), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('nutritional.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

const deleteFactor = (id: number) => {
    if (confirm('¿Estás seguro de que deseas eliminar este factor nutricional?')) {
        form.delete(route('nutritional.destroy', id));
    }
};

const fileInput = ref<HTMLInputElement | null>(null);

const importData = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        router.post(route('nutritional.import'), {
            file: target.files[0]
        }, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                if (fileInput.value) fileInput.value.value = '';
            },
            onError: () => {
                if (fileInput.value) fileInput.value.value = '';
            }
        });
    }
};
</script>

<template>
    <Head title="Factores Nutricionales" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div class="md:col-span-3">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Factores Nutricionales</h1>
                        <p class="mt-1 text-sm text-gray-500">Gestión de factores nutricionales agrupados por ingrediente</p>
                    </div>
                    <div class="flex gap-2">
                        <input type="file" ref="fileInput" class="hidden" accept=".xlsx,.xls,.csv" @change="importData" />
                        <button @click="$refs.fileInput.click()" class="flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-white transition-colors hover:bg-green-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                            Importar Excel
                        </button>
                        <button @click="openCreateModal" class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-white transition-colors hover:bg-blue-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                            </svg>
                            Nuevo Factor
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filtro de Búsqueda -->
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div class="grid grid-cols-1 md:grid-cols-3">
                    <div class="relative">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Buscar Ingrediente</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <input 
                                type="text" 
                                v-model="tableSearch" 
                                placeholder="Escriba para buscar..." 
                                class="w-full rounded-md border border-gray-300 py-2 pl-10 pr-3 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition-colors sm:text-sm"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla Agrupada -->
            <div class="md:col-span-3">
                <div class="overflow-hidden rounded-lg border border-gray-200 shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase w-1/3">Ingrediente</th>
                                <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">NFactorCal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">Composición</th>
                                <th class="px-6 py-3 text-right text-xs font-medium tracking-wider text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <template v-for="(ingredient, iIndex) in groupedIngredients.data" :key="ingredient.id">
                                <tr v-for="(factor, fIndex) in ingredient.nutritional_factors" :key="factor.id" :class="[fIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50/30', 'hover:bg-blue-50/40 transition-colors border-b border-gray-100']">
                                    
                                    <td v-if="fIndex === 0" :rowspan="ingredient.nutritional_factors.length" class="px-6 py-4 text-sm font-semibold text-gray-800 border-r border-gray-200 align-middle bg-white shadow-[inset_-2px_0_4px_rgba(0,0,0,0.01)]">
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 w-2 rounded-full bg-blue-500"></div>
                                            {{ ingredient.name }}
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-3 text-sm text-gray-600 font-medium">
                                        {{ factor.nfactorcal !== null ? Number(factor.nfactorcal).toFixed(2) : '-' }}
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-600 font-medium">
                                        {{ factor.composition !== null ? Number(factor.composition).toFixed(2) : '-' }}
                                    </td>
                                    <td class="px-6 py-3 text-right text-sm font-medium whitespace-nowrap">
                                        <div class="flex justify-end gap-2">
                                            <button @click="openEditModal(factor)" class="rounded p-1.5 text-blue-600 hover:bg-blue-100 hover:text-blue-800 transition-colors" title="Editar">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                </svg>
                                            </button>
                                            <button @click="deleteFactor(factor.id)" class="rounded p-1.5 text-red-600 hover:bg-red-100 hover:text-red-800 transition-colors" title="Eliminar">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="groupedIngredients.data.length === 0">
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                        <p class="text-base font-medium">No se encontraron registros</p>
                                        <p class="text-sm mt-1">Intente buscar con otro término o agregue nuevos factores.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginación -->
                <div v-if="groupedIngredients.data.length > 0" class="mt-4 flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm sm:px-6">
                    <div class="flex flex-1 justify-between sm:hidden">
                        <Link v-if="groupedIngredients.prev_page_url" :href="groupedIngredients.prev_page_url" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Anterior</Link>
                        <span v-else class="relative inline-flex items-center rounded-md border border-gray-300 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-400 cursor-not-allowed">Anterior</span>
                        
                        <Link v-if="groupedIngredients.next_page_url" :href="groupedIngredients.next_page_url" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Siguiente</Link>
                        <span v-else class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-400 cursor-not-allowed">Siguiente</span>
                    </div>
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Mostrando <span class="font-medium">{{ groupedIngredients.from }}</span> a <span class="font-medium">{{ groupedIngredients.to }}</span> de
                                <span class="font-medium">{{ groupedIngredients.total }}</span> resultados
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                <Link 
                                    v-for="(link, index) in groupedIngredients.links" 
                                    :key="index" 
                                    :href="link.url || '#'"
                                    class="relative inline-flex items-center px-4 py-2 text-sm font-medium transition-colors border"
                                    :class="[
                                        link.active ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50',
                                        !link.url ? 'cursor-not-allowed text-gray-300' : '',
                                        index === 0 ? 'rounded-l-md' : '',
                                        index === groupedIngredients.links.length - 1 ? 'rounded-r-md' : ''
                                    ]"
                                    v-html="link.label"
                                ></Link>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal CRUD -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <!-- Fondo transparente con blur elegante -->
                <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-opacity" aria-hidden="true" @click="closeModal"></div>
                <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>
                
                <!-- Contenedor del Modal -->
                <div class="inline-block transform overflow-visible rounded-2xl bg-white text-left align-bottom shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle border border-gray-100">
                    <form @submit.prevent="submitForm">
                        <div class="px-8 pt-8 pb-6">
                            <div class="flex items-center gap-3 border-b border-gray-100 pb-5">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                    </svg>
                                </div>
                                <h3 class="text-xl font-bold text-gray-800 tracking-tight" id="modal-title">
                                    {{ isEditing ? 'Editar Factor Nutricional' : 'Nuevo Factor Nutricional' }}
                                </h3>
                            </div>
                            
                            <div class="mt-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
                                <div class="relative sm:col-span-2">
                                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Ingrediente</label>
                                    <div class="relative">
                                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <input 
                                            type="text" 
                                            v-model="ingredientSearch" 
                                            @focus="showIngredientDropdown = true" 
                                            @blur="hideIngredientDropdownDelay" 
                                            placeholder="Buscar ingrediente..." 
                                            class="block w-full rounded-xl border border-gray-200 bg-gray-50/50 py-3 pl-10 pr-4 text-sm text-gray-900 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10 transition-all duration-200" 
                                            required 
                                        />
                                    </div>
                                    
                                    <!-- Dropdown Autocomplete -->
                                    <div v-if="showIngredientDropdown" class="absolute z-50 w-full mt-2 bg-white border border-gray-100 rounded-xl shadow-[0_8px_30px_rgb(0,0,0,0.12)] max-h-60 overflow-y-auto overflow-x-hidden transform origin-top transition-all">
                                        <div 
                                            v-for="ing in filteredIngredients" 
                                            :key="ing.id" 
                                            @click="selectIngredient(ing)" 
                                            class="cursor-pointer px-4 py-3 hover:bg-blue-50/80 hover:text-blue-700 text-sm font-medium text-gray-600 transition-colors border-b border-gray-50 last:border-0"
                                        >
                                            {{ ing.name }}
                                        </div>
                                        <div v-if="filteredIngredients.length === 0" class="px-4 py-4 text-sm text-gray-500 text-center flex flex-col items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span>No se encontraron ingredientes</span>
                                        </div>
                                    </div>
                                    <p v-if="form.errors.ingredient_id" class="mt-2 text-sm text-red-500 font-medium flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                        {{ form.errors.ingredient_id }}
                                    </p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">NFactorCal</label>
                                    <div class="relative">
                                        <input type="number" step="0.01" v-model="form.nfactorcal" class="block w-full rounded-xl border border-gray-200 bg-gray-50/50 py-3 px-4 text-sm text-gray-900 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10 transition-all duration-200" placeholder="0.00" />
                                    </div>
                                    <p v-if="form.errors.nfactorcal" class="mt-2 text-sm text-red-500 font-medium">{{ form.errors.nfactorcal }}</p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Composición</label>
                                    <div class="relative">
                                        <input type="number" step="0.01" v-model="form.composition" class="block w-full rounded-xl border border-gray-200 bg-gray-50/50 py-3 px-4 text-sm text-gray-900 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10 transition-all duration-200" placeholder="0.00" />
                                    </div>
                                    <p v-if="form.errors.composition" class="mt-2 text-sm text-red-500 font-medium">{{ form.errors.composition }}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50/80 px-8 py-5 sm:flex sm:flex-row-reverse rounded-b-2xl border-t border-gray-100">
                            <button type="submit" :disabled="form.processing" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-transparent bg-blue-600 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-500/20 sm:ml-3 sm:w-auto transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg v-if="!form.processing" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                <svg v-else class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ isEditing ? 'Actualizar' : 'Guardar' }}
                            </button>
                            <button type="button" @click="closeModal" class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-6 py-3 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-4 focus:ring-gray-200/50 sm:mt-0 sm:ml-3 sm:w-auto transition-all">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>