<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
    showModal: {
        type: Boolean,
        default: false,
    },
    dealerships: {
        type: Array,
        default: () => [],
    },
    editingSubdealership: {
        type: Object
    },
});

const emit = defineEmits(['closeModal', 'submitCreate']);

const form = useForm({
    name: '',
    ruc: '',
    dealership_id: '',
    phone: '',
    email: '',
    fiscal_address: '',
    legal_address: '',
    errors: {},
    processing: false,
});

watch(() => props.editingSubdealership, () => {
    form.name = props.editingSubdealership?.name;
    form.ruc = props.editingSubdealership?.ruc;
    form.dealership_id = props.editingSubdealership?.dealership_id;
    form.phone = props.editingSubdealership?.phone;
    form.email = props.editingSubdealership?.email;
    form.fiscal_address = props.editingSubdealership?.fiscal_address;
    form.legal_address = props.editingSubdealership?.legal_address;
});

const closeModal = () => {
    emit('closeModal');
    form.name = '';
    form.ruc = '';
    form.dealership_id = '';
    form.phone = '';
    form.email = '';
    form.fiscal_address = '';
    form.legal_address = '';
    form.errors = {};
};

const submitCreate = () => {
     emit('submitCreate', form);
};
</script>
<template>
    <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="fixed inset-0 bg-black opacity-50" @click="closeModal"></div>
                
                <div class="relative w-full max-w-2xl bg-white rounded-lg shadow-xl">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Crear Subconcesionaria</h3>
                        <button @click="closeModal" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <div  class="p-6">
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <label for="create-name" class="block text-sm font-medium text-gray-700">
                                    Nombre *
                                </label>
                                <input
                                    type="text"
                                    id="create-name"
                                    v-model="form.name"
                                    required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-2 py-2"
                                    :class="{ 'border-red-500': form.errors.name }"
                                />
                                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <div>
                                <label for="create-ruc" class="block text-sm font-medium text-gray-700">
                                    RUC
                                </label>
                                <input
                                    type="text"
                                    id="create-ruc"
                                    v-model="form.ruc"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-2 py-2"
                                    :class="{ 'border-red-500': form.errors.ruc }"
                                />
                                <p v-if="form.errors.ruc" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.ruc }}
                                </p>
                            </div>

                            <div>
                                <label for="create-dealership_id" class="block text-sm font-medium text-gray-700">
                                    Concesionaria *
                                </label>
                                <select
                                    id="create-dealership_id"
                                    v-model="form.dealership_id"
                                    required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-2 py-2"
                                    :class="{ 'border-red-500': form.errors.dealership_id }"
                                >
                                    <option value="">Selecciona subconcesionaria</option>
                                    <option v-for="dealership in props.dealerships" :key="dealership.id" :value="dealership.id">
                                        {{ dealership.name }}
                                    </option>
                                </select>
                                <p v-if="form.errors.dealership_id" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.dealership_id }}
                                </p>
                            </div>

                            <div>
                                <label for="create-phone" class="block text-sm font-medium text-gray-700">
                                    Telefono
                                </label>
                                <input
                                    type="text"
                                    id="create-phone"
                                    v-model="form.phone"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-2 py-2"
                                    :class="{ 'border-red-500': form.errors.phone }"
                                />
                                <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.phone }}
                                </p>
                            </div>

                            <div>
                                <label for="create-email" class="block text-sm font-medium text-gray-700">
                                    Email
                                </label>
                                <input
                                    type="email"
                                    id="create-email"
                                    v-model="form.email"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-2 py-2"
                                    :class="{ 'border-red-500': form.errors.email }"
                                />
                                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.email }}
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 mt-6">
                            <div>
                                <label for="create-fiscal_address" class="block text-sm font-medium text-gray-700">
                                    Direccion Fiscal
                                </label>
                                <textarea
                                    id="create-fiscal_address"
                                    v-model="form.fiscal_address"
                                    rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-2 py-2"
                                    :class="{ 'border-red-500': form.errors.fiscal_address }"
                                ></textarea>
                                <p v-if="form.errors.fiscal_address" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.fiscal_address }}
                                </p>
                            </div>

                            <div>
                                <label for="create-legal_address" class="block text-sm font-medium text-gray-700">
                                    Direccion Legal
                                </label>
                                <textarea
                                    id="create-legal_address"
                                    v-model="form.legal_address"
                                    rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-2 py-2"
                                    :class="{ 'border-red-500': form.errors.legal_address }"
                                ></textarea>
                                <p v-if="form.errors.legal_address" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.legal_address }}
                                </p>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3 mt-6">
                            <button
                                type="button"
                                @click="closeModal"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                @click="submitCreate"
                                class="inline-flex justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-75"
                            >
                                Crear Subconcesionaria
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</template>