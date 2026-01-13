<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { Business, Cafe, Role, Staff, Unit } from '@/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import ChangeStatusModal from './ChangeStatusModal.vue';

import FilesModal from './FilesModal.vue';
import StaffRegistrationDialog from './StaffRegistrationDialog.vue';

interface Props {
    cafes: Cafe[];
    staff: Staff[];
    roles: Role[];
    units: Unit[];
    businneses: Business[];
}

const props = defineProps<Props>();

// Usar ref para reactividad
const staffComplete = ref(props.staff);

const deleteStaff = async (staffId: number) => {
    if (!confirm('Estás seguro de eliminar a este personal?')) return;

    try {
        await axios.delete('/staff/' + staffId);
        // Actualizar la referencia reactiva
        staffComplete.value = staffComplete.value.filter((s) => s.id !== staffId);
    } catch (err) {
        console.error('Error al eliminar personal:', err);
    }
};

const STATUSES = ['Lista negra', 'En proceso', 'Contratado', 'Cesado', 'Retirado', 'Abandono', 'Cumplió Contrato'] as const;

const STATUS_COLORS = [
    'bg-zinc-500 text-white',
    'bg-yellow-500 text-white',
    'bg-green-500 text-white',
    'bg-gray-500 text-white',
    'bg-red-500 text-white',
    'bg-red-500 text-white',
    'bg-blue-500 text-white',
] as const;

const showStatus = (statusId: number) => STATUSES[statusId] ?? 'Desconocido';

const showColor = (statusId: number) => STATUS_COLORS[statusId] ?? '';

const changeStatus = () => {};

const onChangeSearchInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const query = target.value.toLowerCase().trim();

    if (!query) {
        staffComplete.value = props.staff;
        return;
    }

    staffComplete.value = props.staff.filter((staff) => staff.name.toLowerCase().includes(query) || staff.dni.toLowerCase().includes(query));
};

const selectedUnit = ref(0);

watch(selectedUnit, (newValue) => {
    staffComplete.value = props.staff;

    if (newValue != 0) {
        staffComplete.value = props.staff.filter((staff) => staff.staffable && staff.staffable.unit_id && staff.staffable.unit_id == newValue);
    }
});

watch(props, () => {
    staffComplete.value = props.staff;
});
</script>

<template>
    <Head title="Personal" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold tracking-tight">Personal</h1>
                <StaffRegistrationDialog :cafes="props.cafes" :roles="props.roles" :units="props.units" :businneses="props.businneses" />
            </div>

            <div class="flex items-center">
                <Input type="text" placeholder="Buscar personal por dni o nombre" @change="onChangeSearchInput"></Input>
                <Select v-model="selectedUnit">
                    <SelectTrigger class="h-10 border-zinc-200 bg-white hover:bg-zinc-50">
                        <SelectValue placeholder="Seleccionar unidad" />
                    </SelectTrigger>
                    <SelectContent class="border-zinc-200 bg-white shadow-lg">
                        <SelectItem value="0" class="hover:bg-zinc-50"> Ninguna </SelectItem>
                        <SelectItem :value="unit.id" class="hover:bg-zinc-50" v-for="unit in units" :key="unit.id">
                            {{ unit.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Button variant="default" class="ms-2 cursor-pointer bg-blue-600 text-white hover:bg-blue-700">Buscar</Button>
            </div>

            <div class="bg-card rounded-xl border shadow-sm">
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full table-auto border-collapse">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="p-4 text-left text-sm font-semibold">Nombre</th>
                                <th class="p-4 text-left text-sm font-semibold">DNI</th>
                                <th class="p-4 text-left text-sm font-semibold">Celular</th>
                                <th class="p-4 text-left text-sm font-semibold">Comedor/Area</th>
                                <th class="p-4 text-left text-sm font-semibold">Documentación</th>
                                <th class="p-4 text-left text-sm font-semibold">Estado</th>
                                <th class="p-4 text-center text-sm font-semibold">Opciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="staff in staffComplete" :key="staff.id" class="hover:bg-muted/30 border-t transition">
                                <td class="p-4">{{ staff.name }}</td>
                                <td class="p-4">{{ staff.dni }}</td>
                                <td class="p-4">{{ staff.cell }}</td>
                                <td class="p-4">
                                    <p>
                                        {{ staff.staffable?.name || 'Sin asignar' }}
                                        <span v-if="staff.staffable?.unit" class="text-gray-500"> ({{ staff.staffable.unit.name }}) </span>
                                    </p>
                                </td>
                                <td class="p-4">
                                    <TooltipProvider v-for="file in staff.staff_files" :key="file.id">
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <a class="text-md rounded-sm text-white" :href="'/storage/' + file.file_path" target="_blank"> 📄 </a>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>{{ file.file_type }}</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </td>

                                <td class="p-4">
                                    <Badge :class="showColor(staff.status)" class="cursor-pointer rounded-sm" @click="changeStatus()">
                                        {{ showStatus(staff.status) }}
                                    </Badge>
                                </td>

                                <td class="p-4 text-center">
                                    <div class="flex items-center justify-center gap-3">
                                        <ChangeStatusModal :staff="staff" />

                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="cursor-pointer text-red-600 hover:text-red-800"
                                            @click="deleteStaff(staff.id)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>

                                        <StaffRegistrationDialog
                                            :cafes="props.cafes"
                                            :roles="props.roles"
                                            :units="props.units"
                                            :businneses="props.businneses"
                                            :staff="staff"
                                        />

                                        <FilesModal :staff="staff" />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Vista Mobile: Cards -->
                <div class="space-y-4 md:hidden">
                    <div v-for="staff in staffComplete" :key="staff.id" class="rounded-lg border bg-white p-4 shadow-sm transition hover:shadow-md">
                        <!-- Header con nombre y estado -->
                        <div class="mb-3 flex items-start justify-between border-b pb-3">
                            <div class="flex-1">
                                <h3 class="mb-1 text-base font-semibold">{{ staff.name }}</h3>
                                <Badge :class="showColor(staff.status)" class="cursor-pointer rounded-sm text-xs" @click="changeStatus()">
                                    {{ showStatus(staff.status) }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Información en grid -->
                        <div class="mb-4 space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="text-muted-foreground min-w-[80px] text-xs font-medium">DNI:</span>
                                <span class="text-sm">{{ staff.dni }}</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <span class="text-muted-foreground min-w-[80px] text-xs font-medium">Celular:</span>
                                <span class="text-sm">{{ staff.cell }}</span>
                            </div>

                            <div class="flex items-start gap-2">
                                <span class="text-muted-foreground min-w-[80px] text-xs font-medium">Comedor:</span>
                                <span class="flex-1 text-sm"> {{ staff.cafe?.name ?? 'Sin asignar' }} - {{ staff.cafe?.unit?.name }} </span>
                            </div>

                            <div class="flex items-start gap-2">
                                <span class="text-muted-foreground min-w-[80px] text-xs font-medium">Documentos:</span>
                                <div class="flex flex-wrap gap-2">
                                    <TooltipProvider v-for="file in staff.staff_files" :key="file.id">
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <a
                                                    class="inline-block rounded-sm text-lg transition hover:scale-110"
                                                    :href="'/storage/' + file.file_path"
                                                    target="_blank"
                                                >
                                                    📄
                                                </a>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>{{ file.file_type }}</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                    <span v-if="!staff.staff_files || staff.staff_files.length === 0" class="text-muted-foreground text-sm">
                                        Sin documentos
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="flex items-center justify-end gap-2 border-t pt-3">
                            <ChangeStatusModal :staff="staff" />

                            <Button variant="ghost" size="icon" class="cursor-pointer text-red-600 hover:text-red-800" @click="deleteStaff(staff.id)">
                                <Trash2 class="h-4 w-4" />
                            </Button>

                            <StaffRegistrationDialog :cafes="props.cafes" :roles="props.roles" :units="props.units" :businneses="props.businneses" />
                        </div>
                    </div>

                    <!-- Mensaje cuando no hay datos -->
                    <div v-if="!staffComplete || staffComplete.length === 0" class="py-12 text-center">
                        <p class="text-muted-foreground">No hay personal registrado</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
