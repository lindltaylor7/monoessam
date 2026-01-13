<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Staff, User } from '@/types';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { Trash } from 'lucide-vue-next';
import { ref } from 'vue';

// Interfaces
interface DateColumn {
    id: string;
    start_date: string;
    end_date: string;
}

interface Props {
    users: User[];
    cafeId: string; // O number, según tu DB
    periods: DateColumn[];
    guards: any[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'fetchCafeData', cafeId: string | number): void;
}>();

// --- Estados y Forms ---
const form = useForm({
    cafe_id: 0,
    start_date: '',
    end_date: '',
    status: '0', // Cambiado a string para compatibilidad con Select value
    users: [],
});

const newStartDate = ref('');
const newEndDate = ref('');

// --- Helpers ---

// Configuración centralizada de estados (Colores y Labels)
const statusConfig: Record<string | number, { label: string; color: string }> = {
    1: { label: 'Trabajando', color: 'bg-green-400 hover:bg-green-500' },
    2: { label: 'Libre', color: 'bg-blue-400 hover:bg-blue-500' },
    3: { label: 'Falta', color: 'bg-red-400 hover:bg-red-500' },
    4: { label: 'Nuevo', color: 'bg-zinc-400 hover:bg-zinc-500' },
    default: { label: 'Pendiente', color: 'bg-zinc-200 hover:bg-zinc-300' },
};

// Obtener configuración basada en ID
const getStatusDetails = (statusId: number | string | undefined) => {
    return statusConfig[statusId] || statusConfig.default;
};

// Obtener el ID del status actual para un usuario en un periodo
const getCurrentStatusId = (staff: Staff, period: any) => {
    const staffFound = period.staffs.find((s: any) => s.id === staff?.id);
    // Convertimos a String porque el Select Value suele trabajar mejor con strings
    return staffFound?.pivot?.status ? String(staffFound.pivot.status) : undefined;
};

const formatDate = (dateString: string) => {
    if (!dateString) return '';
    const [year, month, day] = dateString.split('-');
    return `${day}/${month}/${year}`;
};

// --- Acciones ---

const addColumn = () => {
    if (!newStartDate.value || !newEndDate.value) {
        alert('Por favor selecciona ambas fechas');
        return;
    }
    // Asignamos valores al form
    form.cafe_id = props.cafeId;
    form.start_date = newStartDate.value;
    form.end_date = newEndDate.value;
    // Nota: Asegúrate de que form.users tenga el formato que espera el backend
    // Aquí solo estamos enviando el array completo de usuarios props
    form.users = props.users;

    axios
        .post('/periods', form) // Ajusta la ruta a tu API
        .then(() => {
            emit('fetchCafeData', props.cafeId);
            newStartDate.value = '';
            newEndDate.value = '';
            form.status = '0';
        })
        .catch((err) => console.error(err));
};

const deletePeriod = (periodId: string) => {
    if (confirm('¿Está seguro de eliminar este periodo?')) {
        axios
            .delete('/periods/' + periodId)
            .then(() => emit('fetchCafeData', props.cafeId))
            .catch((err) => console.error(err));
    }
};

// --- NUEVA FUNCIÓN: Actualizar celda individual ---
const updateUserStatus = (newStatus: string, userId: number, periodId: number) => {
    axios
        .put(`/periods/user/${periodId}`, {
            staff_id: userId,
            period_id: periodId,
            status: newStatus,
        })
        .then(() => {
            emit('fetchCafeData', props.cafeId);
        })
        .catch((err) => {
            console.error('Error actualizando estado:', err);
            alert('No se pudo actualizar el estado');
        });
};
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-end gap-4 rounded-lg border bg-gray-50 p-4">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-gray-700">Fecha Inicio</label>
                <input type="date" v-model="newStartDate" class="rounded border px-3 py-2 text-sm" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-gray-700">Fecha Fin</label>
                <input type="date" v-model="newEndDate" class="rounded border px-3 py-2 text-sm" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-gray-700">Estado Inicial</label>
                <Select v-model="form.status">
                    <SelectTrigger>
                        <SelectValue placeholder="Estado" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="1">Trabajando</SelectItem>
                        <SelectItem value="2">Libre</SelectItem>
                        <SelectItem value="3">Falta</SelectItem>
                        <SelectItem value="4">Nuevo</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <button @click="addColumn" class="h-10 rounded bg-black px-4 py-2 text-sm text-white transition hover:bg-gray-800">
                Agregar Periodo
            </button>
        </div>

        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead class="w-[150px] font-bold"> Guardia </TableHead>
                    <TableHead class="w-[150px] font-bold"> Personal </TableHead>
                    <TableHead v-for="col in props.periods" :key="col.id" class="min-w-[120px] text-center">
                        <div class="flex flex-col items-center justify-center gap-1">
                            <span>{{ formatDate(col.start_date) }}</span>
                            <span class="text-xs text-gray-400">al</span>
                            <span>{{ formatDate(col.end_date) }}</span>
                            <button
                                class="absolute top-2 right-2 opacity-0 transition-opacity group-hover:opacity-100"
                                @click.stop="deletePeriod(col.id)"
                            >
                                <Trash class="text-red-400 hover:text-red-600" :size="14" />
                            </button>
                        </div>
                        <div class="mt-1 flex justify-center">
                            <button @click="deletePeriod(col.id)" title="Eliminar periodo">
                                <Trash class="cursor-pointer text-red-300 hover:text-red-500" :size="16" />
                            </button>
                        </div>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <template v-for="guard in props.guards" :key="guard.id">
                    <TableRow v-for="(role, index) in guard.assigned_roles" :key="role.id">
                        <TableCell v-if="index === 0" class="font-medium" :rowspan="guard.assigned_roles.length">
                            {{ guard.name }}
                        </TableCell>

                        <TableCell class="font-medium"> {{ role.role.name }} - {{ role.staff?.name }} </TableCell>
                        <TableCell v-for="period in props.periods" :key="period.id" class="p-2 text-center">
                            <Select
                                :model-value="getCurrentStatusId(role.staff, period)"
                                @update:model-value="(val) => updateUserStatus(val, role.staff.id, period.id)"
                            >
                                <SelectTrigger class="flex h-auto w-full justify-center border-0 bg-transparent p-0 shadow-none focus:ring-0">
                                    <Badge
                                        class="cursor-pointer border-0 px-3 py-1 text-white shadow-sm transition-all hover:scale-105"
                                        :class="getStatusDetails(getCurrentStatusId(role.staff, period)).color"
                                    >
                                        {{ getStatusDetails(getCurrentStatusId(role.staff, period)).label }}
                                    </Badge>
                                    <span class="sr-only">Toggle menu</span>
                                </SelectTrigger>

                                <SelectContent>
                                    <SelectGroup>
                                        <SelectLabel>Cambiar Estado</SelectLabel>
                                        <SelectItem value="1">Trabajando</SelectItem>
                                        <SelectItem value="2">Libre</SelectItem>
                                        <SelectItem value="3">Falta</SelectItem>
                                        <SelectItem value="4">Nuevo</SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </TableCell>
                    </TableRow>
                </template>
            </TableBody>
        </Table>
    </div>
</template>
