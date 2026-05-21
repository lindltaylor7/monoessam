<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Staff, User } from '@/types';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowRight, Download, FilterX, Info, Trash, UserPlus } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, ref } from 'vue';
import * as XLSX from 'xlsx';
import { filesRequired } from '../staff/composables/useStaffForm';

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
    return props.users.filter((u) => u.name.toLowerCase().includes(q.toLowerCase()));
};

const assignReplacement = async (user: User) => {
    if (!selectedRoleForReplacement.value) return;
    try {
        await axios.post('/guards/roles/replacement', {
            guard_role_id: selectedRoleForReplacement.value.id,
            user_id: user.id,
        });
        selectedRoleForReplacement.value.replacement = user;
        isReplacementModalOpen.value = false;
        emit('fetchCafeData', props.cafeId);
        Swal.fire({
            icon: 'success',
            title: 'Reemplazo asignado',
            timer: 1500,
            showConfirmButton: false,
        });
    } catch (e) {
        console.error(e);
        alert('Error asignando reemplazo');
    }
};

const unassignReplacement = async (role: any) => {
    if (!confirm('¿Está seguro de quitar el reemplazo?')) return;
    try {
        await axios.delete(`/guards/roles/replacement/${role.id}`);
        role.replacement = null;
        emit('fetchCafeData', props.cafeId);
    } catch (e) {
        console.error(e);
        alert('Error quitando reemplazo');
    }
};

const updateObservation = async (role: any) => {
    try {
        await axios.put('/guards/roles/observation', {
            guard_role_id: role.id,
            observation: role.observation,
        });
        Swal.fire({
            icon: 'success',
            title: 'Observación guardada',
            text: 'La observación se ha almacenado correctamente.',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
        });
    } catch (e) {
        console.error(e);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Hubo un problema al guardar la observación.',
        });
    }
};

const filteredUsers = (roleId: string) => {
    const q = searchQueries.value[roleId] || '';
    if (!q) return props.users;
    return props.users.filter((u) => u.name.toLowerCase().includes(q.toLowerCase()));
};

const assignStaff = async (role: any, user: User) => {
    try {
        await axios.post('/guards/roles/user', {
            guard_role_id: role.id,
            user_id: user.id,
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
    if (!role.staff) return;
    if (!confirm('¿Está seguro de quitar a este personal?')) return;
    try {
        await axios.delete(`/guards/roles/user/${role.staff.id}`);
        role.staff = null;
        emit('fetchCafeData', props.cafeId);
    } catch (e) {
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
            text: 'Por favor selecciona las fechas correctamente.',
        });
        return;
    }

    Swal.fire({
        title: 'Cargando...',
        text: 'Agregando periodo(s)...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        },
    });

    if (workMode.value === 'manual') {
        try {
            await axios.post('/periods', {
                cafe_id: props.cafeId,
                start_date: newStartDate.value,
                end_date: newEndDate.value,
                status: form.status,
                users: props.users,
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
                showConfirmButton: false,
            });
        } catch (err) {
            console.error(err);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo agregar el periodo',
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
                    users: props.users,
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
                text: 'Hubo un error al agregar algunos periodos',
            });
        } else {
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: 'Periodos agregados correctamente',
                timer: 1500,
                showConfirmButton: false,
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

const getFilesIncomplete = (staff: Staff) => {
    console.log(staff);
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

// --- Excel Export ---
const exportToExcel = () => {
    const wsData: any[][] = [];
    const merges: any[] = [];

    // Header Row
    const headerRow = ['Guardia', 'Personal'];
    filteredPeriods.value.forEach((p: any) => {
        headerRow.push(`${formatDate(p.start_date)} al ${formatDate(p.end_date)}`);
    });
    wsData.push(headerRow);

    let currentRow = 1; // 0 is header

    if (filteredGuards.value.length === 0) {
        wsData.push(['No se encontraron resultados para los filtros aplicados.']);
    } else {
        filteredGuards.value.forEach((guard: any) => {
            const startRow = currentRow;

            guard.assigned_roles.forEach((role: any, index: number) => {
                const rowData: any[] = [];

                rowData.push(index === 0 ? guard.name : '');

                let staffInfo = role.role.name;
                if (role.staff) {
                    staffInfo += ` - ${role.staff.name} (Titular)`;
                } else {
                    staffInfo += ' - Sin asignar';
                }
                if (role.replacement) {
                    staffInfo += `\nReemplazo: ${role.replacement.name}`;
                }
                if (role.observation) {
                    staffInfo += `\nObs: ${role.observation}`;
                }
                rowData.push(staffInfo);

                filteredPeriods.value.forEach((period: any) => {
                    const statusId = getCurrentStatusId(role, period);
                    const statusLabel = getStatusDetails(statusId).label;
                    rowData.push(statusLabel);
                });

                wsData.push(rowData);
                currentRow++;
            });

            if (guard.assigned_roles.length > 1) {
                merges.push({ s: { r: startRow, c: 0 }, e: { r: currentRow - 1, c: 0 } });
            }
        });
    }

    const ws = XLSX.utils.aoa_to_sheet(wsData);
    if (merges.length > 0) {
        ws['!merges'] = merges;
    }
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Headcount');
    XLSX.writeFile(wb, 'Headcount.xlsx');
};

// --- Filtros ---
const filterDate = ref('');
const filterStatus = ref('all');

const clearFilters = () => {
    filterDate.value = '';
    filterStatus.value = 'all';
};

const filteredPeriods = computed(() => {
    if (!filterDate.value) return props.periods;
    const fDateStr = filterDate.value;
    return props.periods.filter((p) => p.start_date <= fDateStr && p.end_date >= fDateStr);
});

const filteredGuards = computed(() => {
    if (!filterDate.value && filterStatus.value === 'all') {
        return props.guards;
    }

    const targetPeriods = filteredPeriods.value;
    if (targetPeriods.length === 0) return [];

    return props.guards
        .map((guard: any) => {
            const matchingRoles = guard.assigned_roles.filter((role: any) => {
                const activeStaff = role.replacement || role.staff;

                if (filterStatus.value !== 'all') {
                    if (!activeStaff) return false;
                    return targetPeriods.some((p: any) => {
                        const st = getCurrentStatusId(role, p);
                        if (filterStatus.value === 'pending') {
                            return st === undefined;
                        }
                        return st === filterStatus.value;
                    });
                }
                return true;
            });

            if (matchingRoles.length > 0) {
                return {
                    ...guard,
                    assigned_roles: matchingRoles,
                };
            }
            return null;
        })
        .filter((g: any) => g !== null);
});
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

        <div class="relative mt-2 flex items-end gap-4 rounded-lg border border-l-4 border-l-blue-500 bg-white p-4 shadow-sm">
            <div class="absolute -top-3 left-2 rounded bg-blue-500 px-3 py-0.5 text-[10px] font-bold tracking-wider text-white uppercase shadow">
                Filtros de Búsqueda
            </div>
            <div class="flex w-full max-w-[200px] flex-col gap-1">
                <label class="text-xs font-bold text-gray-500 uppercase">Por Fecha</label>
                <input
                    type="date"
                    v-model="filterDate"
                    class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm transition-all outline-none focus:bg-white focus:ring-2 focus:ring-blue-500"
                />
            </div>
            <div class="flex w-full max-w-[200px] flex-col gap-1">
                <label class="text-xs font-bold text-gray-500 uppercase">Por Estado</label>
                <Select v-model="filterStatus">
                    <SelectTrigger class="w-full bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500">
                        <SelectValue placeholder="Todos" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos</SelectItem>
                        <SelectItem value="1">Trabajando</SelectItem>
                        <SelectItem value="2">Libre</SelectItem>
                        <SelectItem value="3">Falta</SelectItem>
                        <SelectItem value="4">Nuevo</SelectItem>
                        <SelectItem value="pending">Pendiente</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="flex flex-col gap-1">
                <button
                    v-if="filterDate || filterStatus !== 'all'"
                    @click="clearFilters"
                    class="flex h-10 items-center gap-2 rounded-md bg-red-50 px-4 py-2 text-sm font-semibold text-red-600 transition-colors hover:bg-red-100"
                >
                    <FilterX :size="16" />
                    Limpiar Filtros
                </button>
            </div>
            <div class="ml-auto flex flex-col gap-1">
                <button
                    @click="exportToExcel"
                    class="flex h-10 items-center gap-2 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm font-semibold text-green-700 transition-colors hover:bg-green-100"
                >
                    <Download :size="16" />
                    Exportar Excel
                </button>
            </div>
        </div>

        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead class="w-[150px] font-bold"> Guardia </TableHead>
                    <TableHead class="w-[150px] font-bold"> Personal </TableHead>
                    <TableHead v-for="col in filteredPeriods" :key="col.id" class="min-w-[120px] text-center">
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
                <template v-for="guard in filteredGuards" :key="guard.id">
                    <TableRow v-for="(role, index) in guard.assigned_roles" :key="role.id">
                        <TableCell v-if="index === 0" class="font-medium" :rowspan="guard.assigned_roles.length">
                            {{ guard.name }}
                        </TableCell>
                        <TableCell class="min-w-[280px] bg-white/50 align-top font-medium">
                            <div class="flex flex-col gap-3">
                                <!-- Role Header -->
                                <div class="flex items-center justify-between border-b pb-1">
                                    <span class="text-[10px] font-bold tracking-wider text-gray-500 uppercase">{{ role.role.name }}</span>
                                    <span v-if="role.staff" class="text-xs font-normal text-red-500">{{ getFilesIncomplete(role.staff) }}</span>
                                </div>

                                <!-- Staff Display Area -->
                                <div class="space-y-2">
                                    <!-- Search if no staff -->
                                    <div class="relative w-full" v-if="!role.staff">
                                        <input
                                            type="text"
                                            placeholder="Asignar personal..."
                                            class="w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm transition-all focus:border-black focus:ring-2 focus:ring-black/5"
                                            v-model="searchQueries[role.id]"
                                            @focus="showDropdowns[role.id] = true"
                                            @blur="hideDropdown(role.id)"
                                        />
                                        <div
                                            v-if="showDropdowns[role.id] && filteredUsers(role.id).length"
                                            class="absolute left-0 z-50 mt-1 max-h-48 w-full overflow-y-auto rounded-md border bg-white shadow-xl"
                                        >
                                            <ul class="py-1 text-sm text-gray-700">
                                                <li
                                                    v-for="user in filteredUsers(role.id)"
                                                    :key="user.id"
                                                    @mousedown.prevent="assignStaff(role, user)"
                                                    class="flex cursor-pointer flex-col px-4 py-2.5 hover:bg-gray-50"
                                                >
                                                    <span class="font-medium">{{ user.name }}</span>
                                                    <span class="text-[10px] text-gray-400 capitalize">{{ user.dni }}</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Assigned Staff -->
                                    <div v-else class="flex flex-col gap-1.5">
                                        <div
                                            class="group relative flex items-center justify-between gap-3 rounded-lg border border-gray-100 bg-white p-2.5 shadow-sm transition-all hover:border-gray-300"
                                        >
                                            <div class="flex flex-col overflow-hidden">
                                                <span class="truncate text-sm font-semibold text-gray-900">{{ role.staff.name }}</span>
                                                <span class="text-[10px] tracking-tight text-gray-400 uppercase">Titular</span>
                                            </div>
                                            <div class="flex shrink-0 gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                                <button
                                                    v-if="!role.replacement"
                                                    @click="openReplacementModal(role)"
                                                    title="Asignar Reemplazo"
                                                    class="rounded-md p-1.5 text-blue-500 hover:bg-blue-50"
                                                >
                                                    <UserPlus :size="14" />
                                                </button>
                                                <button
                                                    @click="unassignStaff(role)"
                                                    title="Quitar titular"
                                                    class="rounded-md p-1.5 text-red-400 hover:bg-red-50"
                                                >
                                                    <Trash :size="14" />
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Replacement Indicator & Name -->
                                        <div v-if="role.replacement" class="flex items-center gap-2 px-1 py-1">
                                            <ArrowRight class="ml-2 text-gray-300" :size="14" />
                                            <div
                                                class="group relative flex flex-1 items-center justify-between gap-2 rounded-lg border border-blue-100 bg-blue-50/50 p-2 transition-all hover:bg-blue-50"
                                            >
                                                <div class="flex flex-col overflow-hidden">
                                                    <span class="truncate text-sm font-bold text-blue-700">{{ role.replacement.name }}</span>
                                                    <span class="text-[10px] tracking-tight text-blue-500 uppercase">Reemplazo activo</span>
                                                </div>
                                                <button
                                                    @click="unassignReplacement(role)"
                                                    title="Quitar reemplazo"
                                                    class="rounded-md p-1.5 text-blue-400 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-blue-100"
                                                >
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
                                        <span class="text-[10px] font-bold tracking-wide text-gray-400 uppercase">Observaciones</span>
                                    </div>
                                    <textarea
                                        v-model="role.observation"
                                        class="w-full resize-none rounded-md border border-gray-100 bg-gray-50/50 px-2.5 py-2 text-xs shadow-inner transition-all focus:border-gray-300 focus:bg-white focus:outline-none"
                                        placeholder="Escribe una nota aquí..."
                                        rows="2"
                                        @blur="updateObservation(role)"
                                    ></textarea>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell v-for="period in filteredPeriods" :key="period.id" class="border-l border-gray-50 p-2 text-center">
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
                <TableRow v-if="filteredGuards.length === 0">
                    <TableCell :colspan="filteredPeriods.length + 2" class="h-24 text-center text-gray-500">
                        No se encontraron resultados para los filtros aplicados.
                    </TableCell>
                </TableRow>
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
                        <Input v-model="replacementSearchQuery" placeholder="Buscar personal..." class="w-full" />
                    </div>
                    <div class="max-h-[300px] overflow-y-auto rounded-md border">
                        <ul class="divide-y divide-gray-100">
                            <li
                                v-for="user in filteredReplacementUsers()"
                                :key="user.id"
                                @click="assignReplacement(user)"
                                class="flex cursor-pointer items-center justify-between px-4 py-3 transition-colors hover:bg-gray-50"
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
                    <Button variant="outline" @click="isReplacementModalOpen = false"> Cancelar </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
