<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Pencil } from 'lucide-vue-next';
import { ref } from 'vue';

interface StatusHistory {
    id: number;
    status: number;
    observation: string;
    created_at: string;
    user_name?: string;
}

interface Staff {
    id: number;
    name: string;
    document_type?: string;
    dni?: string;
    email?: string;
    cell?: string;
    birth_date?: string;
    hire_date?: string;
    position?: string;
    department?: string;
    status: number;
    salary?: number;
    emergency_contact?: string;
    emergency_phone?: string;
    status_history?: StatusHistory[];
    observations?: StatusHistory[];
}

const props = defineProps({
    staff: {
        type: Object as () => Staff,
        required: true,
    },
});

const open = ref(false);

const statusesStaff: Record<number, string> = {
    0: 'Lista negra',
    1: 'En proceso',
    2: 'Completo - RRHH',
    3: 'Contratado',
    4: 'Cesado',
    5: 'Retirado',
    6: 'Abandono',
    7: 'Cumplió Contrato',
};

const formatDate = (date: string | undefined) => {
    if (!date) return 'No registrado';
    return new Date(date).toLocaleString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
};

const formatDateTime = (date: string) => {
    return new Date(date).toLocaleString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusColor = (status: number) => {
    const colors: Record<number, string> = {
        0: 'bg-red-500',
        1: 'bg-yellow-500',
        2: 'En proceso',
        3: 'bg-green-500',
        4: 'bg-gray-500',
        5: 'bg-blue-500',
        6: 'bg-orange-500',
        7: 'bg-purple-500',
    };
    return colors[status] || 'bg-gray-500';
};

const getStatusBadgeColor = (status: number) => {
    const colors: Record<number, string> = {
        0: 'bg-red-100 text-red-800',
        1: 'bg-yellow-100 text-yellow-800',
        2: 'bg-green-100 text-green-800',
        3: 'bg-gray-100 text-gray-800',
        4: 'bg-blue-100 text-blue-800',
        5: 'bg-orange-100 text-orange-800',
        6: 'bg-purple-100 text-purple-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
};

const formatCurrency = (amount: number | undefined) => {
    if (!amount) return 'No registrado';
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
    }).format(amount);
};

const tabActivo = ref('informacion');

const cambiarTab = (tab: string) => {
    tabActivo.value = tab;
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="ghost" size="icon" class="cursor-pointer text-blue-600 hover:text-blue-800">
                <Pencil class="h-4 w-4" />
            </Button>
        </DialogTrigger>
        <DialogContent class="flex max-h-[90vh] flex-col sm:max-w-[900px]">
            <DialogHeader>
                <DialogTitle>Información Completa del Personal</DialogTitle>
            </DialogHeader>

            <!-- Tabs Navigation -->
            <div class="flex-shrink-0 border-b border-gray-200">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <button
                        @click="cambiarTab('informacion')"
                        :class="[
                            tabActivo === 'informacion'
                                ? 'border-blue-500 text-blue-600'
                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700',
                            'cursor-pointer border-b-2 px-1 py-4 text-sm font-medium whitespace-nowrap',
                        ]"
                    >
                        Información Personal
                    </button>
                    <button
                        @click="cambiarTab('historial')"
                        :class="[
                            tabActivo === 'historial'
                                ? 'border-blue-500 text-blue-600'
                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700',
                            'cursor-pointer border-b-2 px-1 py-4 text-sm font-medium whitespace-nowrap',
                        ]"
                    >
                        Archivos
                    </button>
                </nav>
            </div>

            <!-- Tab Content -->
            <div class="min-h-0 flex-1 overflow-y-auto">
                <!-- Tab: Información General -->
                <div v-if="tabActivo === 'informacion'" class="space-y-6 p-4">
                    <!-- Datos Personales -->
                    <div>
                        <h3 class="mb-3 border-l-4 border-blue-500 pl-2 text-sm font-semibold text-gray-900">Datos Personales</h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="flex justify-between border-b py-2">
                                <span class="text-sm text-gray-600">Nombre Completo</span>
                                <span class="text-sm font-medium">{{ staff.name }}</span>
                            </div>
                            <div class="flex justify-between border-b py-2">
                                <span class="text-sm text-gray-600">Tipo de Documento</span>
                                <span class="text-sm font-medium">{{ staff.document_type || 'No registrado' }}</span>
                            </div>
                            <div class="flex justify-between border-b py-2">
                                <span class="text-sm text-gray-600">Número de Documento</span>
                                <span class="text-sm font-medium">{{ staff.dni || 'No registrado' }}</span>
                            </div>
                            <div class="flex justify-between border-b py-2">
                                <span class="text-sm text-gray-600">Fecha de Nacimiento</span>
                                <span class="text-sm font-medium">{{ formatDate(staff.birth_date) }}</span>
                            </div>
                            <div class="flex justify-between border-b py-2">
                                <span class="text-sm text-gray-600">Teléfono</span>
                                <span class="text-sm font-medium">{{ staff.cell || 'No registrado' }}</span>
                            </div>
                            <div class="flex justify-between border-b py-2">
                                <span class="text-sm text-gray-600">Correo Electrónico</span>
                                <span class="text-sm font-medium">{{ staff.email || 'No registrado' }}</span>
                            </div>

                            <div class="flex justify-between border-b py-2 md:col-span-2">
                                <span class="text-sm text-gray-600">Dirección</span>
                                <span class="text-sm font-medium">{{ staff.address || 'No registrado' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Información Laboral -->
                    <div>
                        <h3 class="mb-3 border-l-4 border-green-500 pl-2 text-sm font-semibold text-gray-900">Información Laboral</h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="flex justify-between border-b py-2">
                                <span class="text-sm text-gray-600">Cargo</span>
                                <span class="text-sm font-medium">{{ staff.position || 'No registrado' }}</span>
                            </div>
                            <div class="flex justify-between border-b py-2">
                                <span class="text-sm text-gray-600">Departamento</span>
                                <span class="text-sm font-medium">{{ staff.department || 'No registrado' }}</span>
                            </div>
                            <div class="flex justify-between border-b py-2">
                                <span class="text-sm text-gray-600">Fecha de Contratación</span>
                                <span class="text-sm font-medium">{{ formatDate(staff.hire_date) }}</span>
                            </div>
                            <div class="flex justify-between border-b py-2">
                                <span class="text-sm text-gray-600">Salario</span>
                                <span class="text-sm font-medium">{{ formatCurrency(staff.salary) }}</span>
                            </div>
                            <div class="flex justify-between border-b py-2 md:col-span-2">
                                <span class="text-sm text-gray-600">Estado Actual</span>
                                <span :class="['rounded-full px-3 py-1 text-xs font-medium', getStatusBadgeColor(staff.status)]">
                                    {{ statusesStaff[staff.status] }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Contacto de Emergencia -->
                    <div>
                        <h3 class="mb-3 border-l-4 border-red-500 pl-2 text-sm font-semibold text-gray-900">Contacto de Emergencia</h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="flex justify-between border-b py-2">
                                <span class="text-sm text-gray-600">Nombre del Contacto</span>
                                <span class="text-sm font-medium">{{ staff.emergency_contact || 'No registrado' }}</span>
                            </div>
                            <div class="flex justify-between border-b py-2">
                                <span class="text-sm text-gray-600">Teléfono de Emergencia</span>
                                <span class="text-sm font-medium">{{ staff.emergency_phone || 'No registrado' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Historial de Observaciones -->
                <div v-if="tabActivo === 'historial'" class="p-4">
                    <div v-if="staff.observations && staff.observations.length > 0" class="relative">
                        <!-- Línea vertical del timeline -->
                        <div class="absolute top-0 bottom-0 left-[15px] w-0.5 bg-gray-200"></div>

                        <!-- Items del timeline -->
                        <div v-for="(history, index) in staff.observations" :key="history.id" class="relative pb-6 pl-10 last:pb-0">
                            <!-- Punto del timeline -->
                            <div
                                :class="[
                                    'absolute top-1 left-0 flex h-8 w-8 items-center justify-center rounded-full border-4 border-white',
                                    getStatusColor(history.status),
                                ]"
                            >
                                <span class="text-xs font-bold text-white">{{ index + 1 }}</span>
                            </div>

                            <!-- Contenido -->
                            <div class="rounded-lg bg-gray-50 p-4 shadow-sm">
                                <div class="mb-2 flex items-start justify-between">
                                    <div class="flex-1">
                                        <span class="text-sm font-medium text-gray-900">
                                            {{ history.user?.name || 'Usuario desconocido' }}
                                        </span>
                                        <span :class="['ml-2 rounded-full px-2 py-1 text-xs font-medium', getStatusBadgeColor(history.status)]">
                                            {{ statusesStaff[history.status] }}
                                        </span>
                                    </div>
                                    <span class="text-xs text-gray-500">
                                        {{ formatDateTime(history.created_at) }}
                                    </span>
                                </div>
                                <p v-if="history.observation" class="mt-2 text-sm text-gray-700">
                                    {{ history.observation }}
                                </p>
                                <p v-else class="mt-2 text-sm text-gray-400 italic">Sin observaciones</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="py-12 text-center">
                        <div class="mx-auto mb-4 flex h-24 w-24 items-center justify-center rounded-full bg-gray-100">
                            <svg class="h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                />
                            </svg>
                        </div>
                        <p class="text-sm text-gray-500">No hay historial de cambios registrado</p>
                    </div>
                </div>
            </div>

            <DialogFooter class="flex-shrink-0">
                <Button variant="outline" @click="open = false">Cerrar</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
