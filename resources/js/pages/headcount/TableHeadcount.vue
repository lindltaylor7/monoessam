<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Staff, User } from '@/types';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { Trash } from 'lucide-vue-next';
import { ref } from 'vue';
import { filesRequired } from '../staff/composables/useStaffForm'

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
const workMode = ref('manual');

const searchQueries = ref<Record<string, string>>({});
const showDropdowns = ref<Record<string, boolean>>({});

const hideDropdown = (roleId: string) => {
    setTimeout(() => {
        showDropdowns.value[roleId] = false;
    }, 150);
};

const filteredUsers = (roleId: string) => {
    const q = searchQueries.value[roleId] || '';
    if (!q) return props.users;
    return props.users.filter(u => u.name.toLowerCase().includes(q.toLowerCase()));
};

const assignStaff = async (role: any, user: User) => {
    try {
        await axios.post('/guards/roles/user', {
            guard_role_id: role.id,
            user_id: user.id
        });
        role.staff = user;
        searchQueries.value[role.id] = '';
        showDropdowns.value[role.id] = false;
        emit('fetchCafeData', props.cafeId);
    } catch (e) {
        console.error(e);
        alert('Error asignando staff');
    }
};

const unassignStaff = async (role: any) => {
    if(!role.staff) return;
    if(!confirm('¿Está seguro de quitar a este personal?')) return;
    try {
        await axios.delete(`/guards/roles/user/${role.staff.id}`);
        role.staff = null;
        emit('fetchCafeData', props.cafeId);
    } catch(e) {
        console.error(e);
        alert('Error quitando staff');
    }
};

const filesRequired = ref([
        { label: 'CV Documentado', file: {} },
        { label: 'Certificado Único Laboral (CUL)', file: {}, expirationDate: null },
        { label: 'Certificado de Estudios', file: {} },
        { label: 'Certificados de Trabajo', file: {} },
        { label: 'DNI escaneado', file: {}, expirationDate: null },
        { label: 'Antecedentes Penales y Policiales', file: {}, expirationDate: null },
        { label: 'Carné de sanidad', file: {}, expirationDate: null },
        { label: 'Carné de vacunación contra el COVID', file: {} },
        { label: 'Examen Medico Ocupacional (EMO)', file: {}, expirationDate: null },
        { label: 'SCTR', file: {}, expirationDate: null },
        { label: 'Contrato', file: {}, expirationDate: null },
    ]);

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

const addColumn = async () => {
    if (!newStartDate.value || (workMode.value === 'manual' && !newEndDate.value)) {
        alert('Por favor selecciona las fechas correctamente.');
        return;
    }

    if (workMode.value === 'manual') {
        try {
            await axios.post('/periods', {
                cafe_id: props.cafeId,
                start_date: newStartDate.value,
                end_date: newEndDate.value,
                status: form.status,
                users: props.users
            });
            emit('fetchCafeData', props.cafeId);
            newStartDate.value = '';
            newEndDate.value = '';
            form.status = '0';
        } catch (err) {
            console.error(err);
        }
    } else {
        const cycles = 4;
        let pStartDate = new Date(newStartDate.value + 'T12:00:00');
        
        for (let i = 0; i < cycles; i++) {
            let startStr = pStartDate.toISOString().split('T')[0];
            let endStr = '';
            let nextStart = new Date(pStartDate);
            
            if (workMode.value === '14x7') {
                let end = new Date(pStartDate);
                end.setDate(end.getDate() + 6);
                endStr = end.toISOString().split('T')[0];
                nextStart.setDate(nextStart.getDate() + 7);
            } else if (workMode.value === '20x10') {
                let end = new Date(pStartDate);
                end.setDate(end.getDate() + 9);
                endStr = end.toISOString().split('T')[0];
                nextStart.setDate(nextStart.getDate() + 10);
            }
            
            try {
                await axios.post('/periods', {
                    cafe_id: props.cafeId,
                    start_date: startStr,
                    end_date: endStr,
                    status: form.status,
                    users: props.users
                });
            } catch (err) {
                console.error(err);
            }
            
            pStartDate = nextStart;
        }
        
        emit('fetchCafeData', props.cafeId);
        newStartDate.value = '';
        newEndDate.value = '';
        form.status = '0';
        workMode.value = 'manual';
    }
};

const deletePeriod = (periodId: string) => {
    if (confirm('¿Está seguro de eliminar este periodo?')) {
        axios
            .delete('/periods/' + periodId)
            .then(() => emit('fetchCafeData', props.cafeId))
            .catch((err) => console.error(err));
    }
};

const getFilesIncomplete = (staff:Staff) => {
    console.log(staff)
}

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
                <label class="text-sm font-medium text-gray-700">Modo</label>
                <Select v-model="workMode">
                    <SelectTrigger class="w-[120px]">
                        <SelectValue placeholder="Modo" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="manual">Manual</SelectItem>
                        <SelectItem value="14x7">14x7</SelectItem>
                        <SelectItem value="20x10">20x10</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-gray-700">Fecha Inicio</label>
                <input type="date" v-model="newStartDate" class="rounded border px-3 py-2 text-sm" />
            </div>
            <div class="flex flex-col gap-1" v-if="workMode === 'manual'">
                <label class="text-sm font-medium text-gray-700">Fecha Fin</label>
                <input type="date" v-model="newEndDate" class="rounded border px-3 py-2 text-sm" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-gray-700">Estado</label>
                <Select v-model="form.status">
                    <SelectTrigger class="w-[140px]">
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
                Agregar Periodo{{ workMode !== 'manual' ? 's' : '' }}
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
                        <TableCell class="font-medium min-w-[250px] align-top">
                            <div class="flex flex-col gap-1">
                                <span class="text-xs font-semibold text-gray-500 uppercase">{{ role.role.name }}</span>
                                
                                <div class="relative w-full" v-if="!role.staff">
                                    <input 
                                        type="text" 
                                        placeholder="Buscar staff..." 
                                        class="w-full rounded border px-2 py-1 text-sm text-gray-700 focus:outline-none focus:border-black"
                                        v-model="searchQueries[role.id]"
                                        @focus="showDropdowns[role.id] = true"
                                        @blur="hideDropdown(role.id)"
                                    />
                                    <div v-if="showDropdowns[role.id] && filteredUsers(role.id).length" class="absolute z-50 left-0 mt-1 max-h-40 w-[250px] overflow-y-auto rounded-md border bg-white shadow-lg">
                                        <ul class="py-1 text-sm text-gray-700">
                                            <li 
                                                v-for="user in filteredUsers(role.id)" 
                                                :key="user.id" 
                                                @mousedown.prevent="assignStaff(role, user)"
                                                class="cursor-pointer px-3 py-2 hover:bg-gray-100"
                                            >
                                                {{ user.name }}
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div v-else class="flex items-center justify-between gap-2 rounded border bg-gray-50 px-2 py-1">
                                    <span class="text-sm font-medium">{{ role.staff.name }}</span>
                                    <button @click="unassignStaff(role)" title="Quitar asignación" class="text-xs text-red-400 hover:text-red-600">
                                        <Trash :size="14" />
                                    </button>
                                </div>
                                <span v-if="role.staff" class="text-xs text-red-500">{{ getFilesIncomplete(role.staff) }}</span>
                            </div>
                        </TableCell>
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
