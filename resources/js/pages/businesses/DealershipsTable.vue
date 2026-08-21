<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Pencil, Trash } from 'lucide-vue-next';
import Swal from 'sweetalert2';

interface DealershipRecord {
    id: number;
    name: string;
    ruc: string | null;
    fiscal_address: string | null;
    legal_address: string | null;
    phone: string | null;
    email: string | null;
    contracts_count?: number;
    mines_count?: number;
}

const props = defineProps<{
    dealerships: DealershipRecord[];
}>();

const emit = defineEmits<{
    (e: 'edit', dealership: DealershipRecord): void;
}>();

const deleteDealership = (dealership: DealershipRecord) => {
    const dependents = (dealership.contracts_count || 0) + (dealership.mines_count || 0);
    const text =
        dependents > 0
            ? `"${dealership.name}" tiene ${dependents} registro(s) asociado(s) (contratos o unidades mineras) que quedarán sin concesionaria asignada. ¿Desea continuar?`
            : `Se eliminará la concesionaria "${dealership.name}". Esta acción no se puede deshacer.`;

    Swal.fire({
        title: '¿Eliminar concesionaria?',
        text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc2626',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('dealerships.destroy', dealership.id), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        title: '¡Eliminada!',
                        text: 'La concesionaria ha sido eliminada.',
                        icon: 'success',
                        timer: 1800,
                        showConfirmButton: false,
                    });
                },
            });
        }
    });
};
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-md transition-all duration-200 hover:shadow-lg dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-200 bg-gradient-to-r from-blue-50 to-purple-50 p-4 dark:border-gray-700 dark:from-gray-700 dark:to-gray-700">
            <h2 class="flex items-center text-xl font-semibold text-gray-800 dark:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="mr-2 h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5" />
                </svg>
                Concesionarias
            </h2>
        </div>

        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            <div
                v-for="dealership in props.dealerships"
                :key="dealership.id"
                class="group flex items-center justify-between p-4 transition-colors duration-150 hover:bg-gray-50 dark:hover:bg-gray-700/50"
            >
                <div>
                    <h3 class="font-medium text-gray-900 dark:text-white">
                        {{ dealership.name }}
                        <span class="ml-2 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                            Concesionaria
                        </span>
                    </h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">RUC: {{ dealership.ruc || '—' }}</p>
                </div>
                <div class="flex items-center gap-1">
                    <button
                        @click="emit('edit', dealership)"
                        class="rounded-full p-2 text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/30"
                        title="Editar"
                    >
                        <Pencil class="h-4.5 w-4.5" />
                    </button>
                    <button
                        @click="deleteDealership(dealership)"
                        class="rounded-full p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30"
                        title="Eliminar"
                    >
                        <Trash class="h-4.5 w-4.5" />
                    </button>
                </div>
            </div>

            <div v-if="props.dealerships.length === 0" class="p-6 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No hay concesionarias registradas</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Agrega la primera concesionaria para comenzar</p>
            </div>
        </div>
    </div>
</template>
