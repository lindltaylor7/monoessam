<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Subdealership } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import { ref, watch } from 'vue';
import SubdealershipModal from './SubdealershipModal.vue';

interface Props {
    subdealerships: Subdealership[];
    allSubdealerships: Subdealership[];
}

const props = defineProps<Props>();

const displayedSubdealerships = ref([...props.subdealerships]);

watch(
    () => props.subdealerships,
    (val) => {
        displayedSubdealerships.value = [...val];
    },
);

const showModal = ref(false);
const editingSubdealership = ref<Subdealership | null>(null);

const form = useForm({
    name: '',
    ruc: '',
    fiscal_address: '',
    legal_address: '',
    phone: '',
    email: '',
});

const openEditModal = (subdealership: Subdealership) => {
    editingSubdealership.value = subdealership;
    form.name = subdealership.name || '';
    form.ruc = subdealership.ruc || '';
    form.fiscal_address = subdealership.fiscal_address || '';
    form.legal_address = subdealership.legal_address || '';
    form.phone = subdealership.phone || '';
    form.email = subdealership.email || '';
    showModal.value = true;
};

const openCreateModal = () => {
    editingSubdealership.value = null;
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

const submitCreate = (form: any) => {
    form.post(route('subdealerships.store'), {
        onSuccess: () => {
            closeModal();
            router.reload({ only: ['subdealerships', 'allSubdealerships'] });
            Swal.fire({
                icon: 'success',
                title: '¡Registrado!',
                text: 'La subconcesionaria se ha creado correctamente.',
                timer: 2500,
                showConfirmButton: false,
            });
        },
    });
};

const attachExisting = (id: number) => {
    router.post(
        route('subdealerships.attach', { subdealership: id }),
        {},
        {
            onSuccess: () => {
                router.reload({ only: ['subdealerships', 'allSubdealerships'] });
                Swal.fire({
                    icon: 'success',
                    title: '¡Asociada!',
                    text: 'La subconcesionaria existente se ha asociado a tu mina correctamente.',
                    timer: 2500,
                    showConfirmButton: false,
                });
            },
        },
    );
};

const deleteSubdealership = async (id: number) => {
    if (confirm('¿Estás seguro de eliminar esta subconcesionaria?')) {
        await axios
            .delete(`/subdealerships/${id}`)
            .then(() => {
                displayedSubdealerships.value = displayedSubdealerships.value.filter((s) => s.id !== id);
            })
            .catch((error) => {
                console.error(error);
            });
    }
};
</script>

<template>
    <Head title="Subconcesionarias" />
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
                                <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">Nombre</th>
                                <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">RUC</th>
                                <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">Teléfono</th>
                                <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">Email</th>
                                <!--  <th class="px-6 py-3 text-right text-xs font-medium tracking-wider text-gray-500 uppercase">Acciones</th> -->
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="subdealership in displayedSubdealerships" :key="subdealership.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-900">
                                    {{ subdealership.name }}
                                </td>
                                <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-500">
                                    {{ subdealership.ruc || '—' }}
                                </td>
                                <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-500">
                                    {{ subdealership.phone || '—' }}
                                </td>
                                <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-500">
                                    {{ subdealership.email || '—' }}
                                </td>
                                <!--  <td class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap">
                                    <button @click="openEditModal(subdealership)" class="mr-3 text-indigo-600 hover:text-indigo-900">Editar</button>
                                    <button @click="deleteSubdealership(subdealership.id)" class="text-red-600 hover:text-red-900">Eliminar</button>
                                </td> -->
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="displayedSubdealerships.length === 0" class="py-12 text-center">
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

        <SubdealershipModal
            :showModal="showModal"
            :editingSubdealership="editingSubdealership"
            :existingSubdealerships="props.allSubdealerships"
            @closeModal="closeModal"
            @submitCreate="submitCreate"
            @attachExisting="attachExisting"
        />
    </AppLayout>
</template>
