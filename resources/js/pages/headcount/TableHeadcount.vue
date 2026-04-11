<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Staff, User } from '@/types';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { Trash, Info, UserPlus, ArrowRight } from 'lucide-vue-next';
import { ref } from 'vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { filesRequired } from '../staff/composables/useStaffForm'
import Swal from 'sweetalert2';

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

const isReplacementModalOpen = ref(false);
const selectedRoleForReplacement = ref<any>(null);
const replacementSearchQuery = ref('');

const openReplacementModal = (role: any) => {
    selectedRoleForReplacement.value = role;
    replacementSearchQuery.value = '';
    isReplacementModalOpen.value = true;
};

const filteredReplacementUsers = () => {
    const q = replacementSearchQuery.value || '';
    if (!q) return props.users;
    return props.users.filter(u => u.name.toLowerCase().includes(q.toLowerCase()));
};

const assignReplacement = async (user: User) => {
    if (!selectedRoleForReplacement.value) return;
    try {
        await axios.post('/guards/roles/replacement', {
            guard_role_id: selectedRoleForReplacement.value.id,
            user_id: user.id
        });
        selectedRoleForReplacement.value.replacement = user;
        isReplacementModalOpen.value = false;
        emit('fetchCafeData', props.cafeId);
        Swal.fire({
            icon: 'success',
            title: 'Reemplazo asignado',
            timer: 1500,
            showConfirmButton: false
        });
    } catch (e) {
        console.error(e);
        alert('Error asignando reemplazo');
    }
};

const unassignReplacement = async (role: any) => {
    if(!confirm('¿Está seguro de quitar el reemplazo?')) return;
    try {
        await axios.delete(`/guards/roles/replacement/${role.id}`);
        role.replacement = null;
        emit('fetchCafeData', props.cafeId);
    } catch(e) {
        console.error(e);
        alert('Error quitando reemplazo');
    }
};

const updateObservation = async (role: any) => {
    try {
        await axios.put('/guards/roles/observation', {
            guard_role_id: role.id,
            observation: role.observation
        });
        // Opcional: mostrar un toast o algo sutil
    } catch (e) {
        console.error(e);
    }
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
const getCurrentStatusId = (role: any, period: any) => {
    const activeStaff = role.replacement || role.staff;
    if (!activeStaff) return undefined;
    
    const staffFound = period.staffs.find((s: any) => s.id === activeStaff.id);
    return staffFound?.pivot?.status ? String(staffFound.pivot.status) : undefined;
};

const updateUserStatusWithRole = (newStatus: string, role: any, periodId: number) => {
    const activeStaff = role.replacement || role.staff;
    if (!activeStaff) return;
    
    updateUserStatus(newStatus, activeStaff.id, periodId);
};

const formatDate = (dateString: string) => {
    if (!dateString) return '';
    const [year, month, day] = dateString.split('-');
    return `${day}/${month}/${year}`;
};

// --- Acciones ---

const addColumn = async () => {
    if (!newStartDate.value || (workMode.value === 'manual' && !newEndDate.value)) {
        Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'Por favor selecciona las fechas correctamente.'
        });
        return;
    }

    Swal.fire({
        title: 'Cargando...',
        text: 'Agregando periodo(s)...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

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
            
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: 'Periodo agregado correctamente',
                timer: 1500,
                showConfirmButton: false
            });
        } catch (err) {
            console.error(err);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo agregar el periodo'
            });
        }
    } else {
        const cycles = 4;
        let pStartDate = new Date(newStartDate.value + 'T12:00:00');
        let hasError = false;
        
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
                hasError = true;
            }
            
            pStartDate = nextStart;
        }
        
        emit('fetchCafeData', props.cafeId);
        newStartDate.value = '';
        newEndDate.value = '';
        form.status = '0';
        workMode.value = 'manual';

        if (hasError) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Hubo un error al agregar algunos periodos'
            });
        } else {
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: 'Periodos agregados correctamente',
                timer: 1500,
                showConfirmButton: false
            });
        }
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
                        <TableCell class="font-medium min-w-[280px] align-top bg-white/50">
                            <div class="flex flex-col gap-3">
                                <!-- Role Header -->
                                <div class="flex items-center justify-between border-b pb-1">
                                    <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">{{ role.role.name }}</span>
                                    <span v-if="role.staff" class="text-xs text-red-500 font-normal">{{ getFilesIncomplete(role.staff) }}</span>
                                </div>
                                
                                <!-- Staff Display Area -->
                                <div class="space-y-2">
                                    <!-- Search if no staff -->
                                    <div class="relative w-full" v-if="!role.staff">
                                        <input 
                                            type="text" 
                                            placeholder="Asignar personal..." 
                                            class="w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm transition-all focus:ring-2 focus:ring-black/5 focus:border-black"
                                            v-model="searchQueries[role.id]"
                                            @focus="showDropdowns[role.id] = true"
                                            @blur="hideDropdown(role.id)"
                                        />
                                        <div v-if="showDropdowns[role.id] && filteredUsers(role.id).length" class="absolute z-50 left-0 mt-1 max-h-48 w-full overflow-y-auto rounded-md border bg-white shadow-xl">
                                            <ul class="py-1 text-sm text-gray-700">
                                                <li 
                                                    v-for="user in filteredUsers(role.id)" 
                                                    :key="user.id" 
                                                    @mousedown.prevent="assignStaff(role, user)"
                                                    class="cursor-pointer px-4 py-2.5 hover:bg-gray-50 flex flex-col"
                                                >
                                                    <span class="font-medium">{{ user.name }}</span>
                                                    <span class="text-[10px] text-gray-400 capitalize">{{ user.dni }}</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <!-- Assigned Staff -->
                                    <div v-else class="flex flex-col gap-1.5">
                                        <div class="group relative flex items-center justify-between gap-3 rounded-lg border border-gray-100 bg-white p-2.5 shadow-sm transition-all hover:border-gray-300">
                                            <div class="flex flex-col overflow-hidden">
                                                <span class="truncate text-sm font-semibold text-gray-900">{{ role.staff.name }}</span>
                                                <span class="text-[10px] text-gray-400 uppercase tracking-tight">Titular</span>
                                            </div>
                                            <div class="flex shrink-0 gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <button v-if="!role.replacement" @click="openReplacementModal(role)" title="Asignar Reemplazo" class="p-1.5 rounded-md text-blue-500 hover:bg-blue-50">
                                                    <UserPlus :size="14" />
                                                </button>
                                                <button @click="unassignStaff(role)" title="Quitar titular" class="p-1.5 rounded-md text-red-400 hover:bg-red-50">
                                                    <Trash :size="14" />
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Replacement Indicator & Name -->
                                        <div v-if="role.replacement" class="flex items-center gap-2 px-1 py-1">
                                            <ArrowRight class="text-gray-300 ml-2" :size="14" />
                                            <div class="group relative flex flex-1 items-center justify-between gap-2 rounded-lg border border-blue-100 bg-blue-50/50 p-2 transition-all hover:bg-blue-50">
                                                <div class="flex flex-col overflow-hidden">
                                                    <span class="truncate text-sm font-bold text-blue-700">{{ role.replacement.name }}</span>
                                                    <span class="text-[10px] text-blue-500 uppercase tracking-tight">Reemplazo activo</span>
                                                </div>
                                                <button @click="unassignReplacement(role)" title="Quitar reemplazo" class="p-1.5 rounded-md text-blue-400 hover:bg-blue-100 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <Trash :size="12" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Observation Field -->
                                <div class="mt-1 space-y-1">
                                    <div class="flex items-center gap-1.5 px-0.5">
                                        <Info :size="10" class="text-gray-400" />
                                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Observaciones</span>
                                    </div>
                                    <textarea 
                                        v-model="role.observation" 
                                        class="w-full rounded-md border border-gray-100 bg-gray-50/50 px-2.5 py-2 text-xs transition-all focus:outline-none focus:border-gray-300 focus:bg-white resize-none shadow-inner"
                                        placeholder="Escribe una nota aquí..."
                                        rows="2"
                                        @blur="updateObservation(role)"
                                    ></textarea>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell v-for="period in props.periods" :key="period.id" class="p-2 text-center border-l border-gray-50">
                            <Select
                                :model-value="getCurrentStatusId(role, period)"
                                @update:model-value="(val) => updateUserStatusWithRole(val, role, period.id)"
                            >
                                <SelectTrigger class="flex h-10 w-full justify-center border-0 bg-transparent p-0 shadow-none focus:ring-0">
                                    <Badge
                                        class="cursor-pointer border-0 px-3 py-1 text-white shadow-sm transition-all hover:scale-105"
                                        :class="getStatusDetails(getCurrentStatusId(role, period)).color"
                                    >
                                        {{ getStatusDetails(getCurrentStatusId(role, period)).label }}
                                    </Badge>
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

        <Dialog v-model:open="isReplacementModalOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Asignar Reemplazo</DialogTitle>
                    <DialogDescription>
                        Seleccione un personal para reemplazar a la persona actual en el rol: 
                        <span class="font-bold text-black" v-if="selectedRoleForReplacement">
                            {{ selectedRoleForReplacement.role.name }}
                        </span>
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4 py-4">
                    <div class="flex flex-col gap-2">
                        <Input 
                            v-model="replacementSearchQuery" 
                            placeholder="Buscar personal..." 
                            class="w-full"
                        />
                    </div>
                    <div class="max-h-[300px] overflow-y-auto border rounded-md">
                        <ul class="divide-y divide-gray-100">
                            <li 
                                v-for="user in filteredReplacementUsers()" 
                                :key="user.id" 
                                @click="assignReplacement(user)"
                                class="cursor-pointer px-4 py-3 hover:bg-gray-50 transition-colors flex items-center justify-between"
                            >
                                <span class="text-sm font-medium">{{ user.name }}</span>
                                <UserPlus class="text-gray-400" :size="16" />
                            </li>
                            <li v-if="filteredReplacementUsers().length === 0" class="p-4 text-center text-sm text-gray-500">
                                No se encontraron resultados
                            </li>
                        </ul>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="isReplacementModalOpen = false">
                        Cancelar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
