<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { Subdealership, Dealership } from '@/types';
import { ref } from 'vue';
import SubdealershipModal from './SubdealershipModal.vue';
import axios from 'axios';

interface Props {
    subdealerships: Subdealership[];
    dealerships: Dealership[];
}

const props = defineProps<Props>();

const allSubdealerships = ref([]);

allSubdealerships.value = props.subdealerships

const showEditModal = ref(false);
const showModal = ref(false);
const editingSubdealership = ref<Subdealership | null>(null);

const form = useForm({
    name: '',
    ruc: '',
    fiscal_address: '',
    legal_address: '',
    phone: '',
    email: '',
    dealership_id: '',
});

const openEditModal = (subdealership: Subdealership) => {
    editingSubdealership.value = subdealership;
    form.name = subdealership.name || '';
    form.ruc = subdealership.ruc || '';
    form.fiscal_address = subdealership.fiscal_address || '';
    form.legal_address = subdealership.legal_address || '';
    form.phone = subdealership.phone || '';
    form.email = subdealership.email || '';
    form.dealership_id = subdealership.dealership_id?.toString() || '';
    showModal.value = true;
};

const openCreateModal = () => {
    console.log('test')
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingSubdealership.value = null;
    form.reset();
    form.clearErrors();
};

const closeCreateModal = () => {
    showCreateModal.value = false;
    editingSubdealership.value = null;
    form.reset();
    form.clearErrors();
};

const submitEdit = () => {
    if (!editingSubdealership.value) return;
    
    form.put(route('subdealerships.update', editingSubdealership.value.id), {
        onSuccess: () => {
            closeModal();
        },
    });
};

const submitCreate = (form: any) => {
    form.post(route('subdealerships.store'));
    form.reset();
    allSubdealerships.value = props.subdealerships;
    closeModal();
};

const deleteSubdealership = async (id: number) => {
    if (confirm('Estás seguro de eliminar esta subconcesionaria?')) {
        await axios.delete(`/subdealerships/${id}`).then(() => {
              allSubdealerships.value = props.subdealerships.filter((subdealership: Subdealership) => subdealership.id != id);
        }).catch((error) => {
            console.log(error);
        });
    }
};

</script>

<template>
    <Head title="Subdealerships" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex h-[40px] w-full items-center justify-between">
                <h1 class="text-2xl font-semibold">Subconcesionarias</h1>
                <button
                    @click="openCreateModal"
                    class="inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                >
                    Crear Subconcesionaria
                </button>
            </div>

            <div class="rounded-md bg-white shadow">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Nombre
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    RUC
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Concesionaria
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Teléfono
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Email
                                </th>
                                <th scope="col" class="px-6 py-3 text-rigth text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="subdealership in allSubdealerships" :key="subdealership.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ subdealership.name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ subdealership.ruc || '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ subdealership.dealership?.name || '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ subdealership.phone || '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ subdealership.email || '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    
                                    <button
                                        @click="openEditModal(subdealership)"
                                        class="text-indigo-600 hover:text-indigo-900 mr-3"
                                    >
                                        Editar
                                    </button>
                                    <button
                                        @click="deleteSubdealership(subdealership.id)"
                                        class="text-red-600 hover:text-red-900"
                                    >
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div v-if="props.subdealerships.length === 0" class="text-center py-12">
                    <p class="text-gray-500">No se han encontrado subconcesionarias.</p>
                    <button
                        @click="openCreateModal"
                        class="mt-4 inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500"
                    >
                        Crear subconcesionaria
                    </button>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <SubdealershipModal
            :showModal="showModal"
            :dealerships="props.dealerships"
            :editingSubdealership="editingSubdealership"
            @closeModal="closeModal"
            @submitCreate="submitCreate"
        />
    </AppLayout>
</template>