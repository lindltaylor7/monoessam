<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, ref } from 'vue';
import ServiceModal from './ServiceModal.vue';
import { Head } from '@inertiajs/vue3';

// Definición de tipos
interface Service {
    id: number;
    code: string;
    name: string;
    description: string;
    type: string;
    created_at: string;
    updated_at: string;
}

// Estado del componente
const services = ref<Service[]>([]);
const showModal = ref(false);
const editingService = ref<Service | null>(null);
const deleteServiceId = ref<number | null>(null);
const showDeleteModal = ref(false);

// Formulario para crear/editar servicios
const form = useForm({
    code: '',
    name: '',
    description: '',
    type: '',
});

// Tipos de servicio disponibles
const serviceTypes = [
    { value: '1', label: 'Desayuno' },
    { value: '2', label: 'Almuerzo' },
    { value: '3', label: 'Cena' },
    { value: '4', label: 'Refrigerio' },
    { value: '5', label: 'Descartables' },
];

// Cargar servicios al montar el componente
onMounted(() => {
    fetchServices();
});

// Obtener todos los servicios
const fetchServices = async () => {
    try {
        const response = await axios.get('services/list');
        services.value = response.data;
    } catch (error) {
        console.error('Error fetching services:', error);
    }
};

// Abrir modal para crear/editar servicio
const openServiceModal = (service: Service | null = null) => {
    editingService.value = service;

    if (service) {
        form.code = service.code;
        form.name = service.name;
        form.description = service.description;
        form.type = service.type;
    } else {
        form.reset();
    }

    showModal.value = true;
};

// Guardar servicio (crear o actualizar)
const saveService = async () => {
    try {
        if (editingService.value) {
            await axios.put(`/api/services/${editingService.value.id}`, form);
        } else {
            await axios.post('/api/services', form);
        }

        showModal.value = false;
        form.reset();
        await fetchServices();
    } catch (error) {
        console.error('Error saving service:', error);
    }
};

// Confirmar eliminación de servicio
const confirmDelete = (id: number) => {
    if (confirm('¿Estás seguro de que deseas eliminar este servicio?')) {
        axios
            .delete('/services/' + id)
            .then((result) => {
                fetchServices();
            })
            .catch((err) => {
                console.error(err);
            });
    }
};
</script>

<template>
    <Head title="Servicios" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="md:col-span-3">
                    <div class="flex items-center justify-between">
                        <h1 class="text-2xl font-bold">Servicios</h1>
                        <ServiceModal :serviceTypes="serviceTypes" @fetchServices="fetchServices" />
                    </div>
                </div>

                <!-- Tabla de servicios -->
                <div class="md:col-span-3">
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">Código</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">Descripción</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">Tipo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="service in services" :key="service.id">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ service.code }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ service.name }}
                                    </td>
                                    <td class="px-6 py-4">
                                        {{ service.description }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800">
                                            {{ service.type }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap">
                                        <button @click="openServiceModal(service)" class="mr-2 text-blue-600 hover:text-blue-900">Editar</button>
                                        <button @click="confirmDelete(service.id)" class="text-red-600 hover:text-red-900">Eliminar</button>
                                    </td>
                                </tr>
                                <tr v-if="services.length === 0">
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">No hay servicios registrados</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para crear/editar servicio -->

        <!-- Modal de confirmación para eliminar -->
        <div v-if="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex min-h-screen items-center justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-gray-500 opacity-75" @click="showDeleteModal = false"></div>
                </div>
                <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>
                <div
                    class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle"
                >
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div
                                class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10"
                            >
                                <svg
                                    class="h-6 w-6 text-red-600"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                    />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Eliminar servicio</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        ¿Estás seguro de que deseas eliminar este servicio? Esta acción no se puede deshacer.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                        <button
                            type="button"
                            @click="deleteService"
                            class="inline-flex w-full justify-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-red-700 focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Eliminar
                        </button>
                        <button
                            type="button"
                            @click="showDeleteModal = false"
                            class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
