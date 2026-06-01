<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Box, Briefcase, Check, Package, Plus, Trash2 } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, ref } from 'vue';

import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

const props = defineProps<{
    roles: Array<{ id: number; name: string }>;
    epps: Array<{ id: number; name: string; quantity: number; roles: Array<{ id: number; pivot: { cafe_id: number } }> }>;
    cafes: Array<{
        id: number;
        name: string;
        roles: Array<{ id: number }>;
        unit: { name: string; mine: { name: string } };
    }>;
    colors: Array<{ id: number; name: string }>;
}>();

const newEppName = ref('');
const isCreating = ref(false);
const searchQuery = ref('');
const selectedCafeId = ref(props.cafes.length > 0 ? String(props.cafes[0].id) : '');
const eppSearchQuery = ref('');

// Filtrar EPPs según búsqueda
const filteredEpps = computed(() => {
    if (!eppSearchQuery.value) return props.epps;
    return props.epps.filter((epp) => epp.name.toLowerCase().includes(eppSearchQuery.value.toLowerCase()));
});

// Filtrar roles según el café seleccionado y la búsqueda
const filteredRoles = computed(() => {
    const currentCafe = props.cafes.find((c) => String(c.id) === selectedCafeId.value);
    const cafeRoleIds = currentCafe?.roles?.map((r) => r.id) || [];

    const roles = props.roles.filter((role) => cafeRoleIds.includes(role.id));

    if (!searchQuery.value) return roles;
    return roles.filter((role) => role.name.toLowerCase().includes(searchQuery.value.toLowerCase()));
});

// Obtener EPPs asignados a un rol específico y café seleccionado
const getEppsForRole = (roleId: number) => {
    return props.epps.filter((epp) => epp.roles.some((role) => role.id === roleId && String(role.pivot.cafe_id) === selectedCafeId.value));
};

// Verificar si un EPP está asignado a un rol en el café seleccionado
const hasRole = (epp: any, roleId: number) => {
    return epp.roles.some((r: any) => r.id === roleId && String(r.pivot.cafe_id) === selectedCafeId.value);
};

const getPivotData = (epp: any, roleId: number) => {
    const role = epp.roles.find((r: any) => r.id === roleId && String(r.pivot.cafe_id) === selectedCafeId.value);
    return role ? role.pivot : null;
};

const createEpp = () => {
    if (!newEppName.value) return;
    isCreating.value = true;
    router.post(
        route('inventory.epps.store'),
        {
            name: newEppName.value,
            category_epp_id: 'none', // Seteamos por defecto
            size_ids: [],
        },
        {
            onSuccess: () => (newEppName.value = ''),
            onFinish: () => (isCreating.value = false),
        },
    );
};

const deleteEpp = (id: number) => {
    if (confirm('¿Seguro que deseas eliminar este EPP y todas sus asignaciones?')) {
        router.delete(route('clothes.epps.destroy', id), {
            preserveScroll: true,
        });
    }
};

const toggleRole = (eppId: number, roleId: number, currentStatus: boolean, quantity: number = 1, color_id: number | null = null) => {
    if (!selectedCafeId.value) {
        Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'Por favor selecciona un café primero',
        });
        return;
    }

    Swal.fire({
        title: 'Procesando...',
        text: currentStatus ? 'Desvinculando EPP...' : 'Asignando EPP...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        },
    });

    router.post(
        route('clothes.assign-epp-role'),
        {
            epp_id: eppId,
            role_id: roleId,
            cafe_id: selectedCafeId.value,
            action: currentStatus ? 'detach' : 'attach',
            quantity: quantity,
            color_id: color_id,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => Swal.close(),
        },
    );
};

const updatePivot = (eppId: number, roleId: number, field: string, value: any) => {
    const epp = props.epps.find((e) => e.id === eppId);
    const pivot = getPivotData(epp, roleId);
    if (!pivot) return;

    const data = {
        quantity: pivot.quantity,
        color_id: pivot.color_id,
        [field]: value,
    };

    Swal.fire({
        title: 'Actualizando...',
        text: 'Guardando cambios en la matriz',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        },
    });

    router.post(
        route('clothes.assign-epp-role'),
        {
            epp_id: eppId,
            role_id: roleId,
            cafe_id: selectedCafeId.value,
            action: 'attach',
            ...data,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => Swal.close(),
        },
    );
};

// Calcular estadísticas
const stats = computed(() => {
    const totalAssignments = props.roles.reduce((acc, role) => {
        return acc + getEppsForRole(role.id).length;
    }, 0);

    const averagePerRole = props.roles.length > 0 ? (totalAssignments / props.roles.length).toFixed(1) : '0';

    return {
        totalAssignments,
        averagePerRole,
        rolesWithAssignments: props.roles.filter((role) => getEppsForRole(role.id).length > 0).length,
    };
});

const selectedCafeLabel = computed(() => {
    const cafe = props.cafes.find((c) => String(c.id) === selectedCafeId.value);
    if (!cafe) return 'Seleccionar';
    return `${cafe.name} - ${cafe.unit?.name} - ${cafe.unit?.mine?.name}`;
});

// --- NUEVA LÓGICA DE PAGINACIÓN DE COLUMNAS (ROLES) ---
const roleStartIndex = ref(0);
const rolesPerPage = ref(8); // Por defecto 8 para balancear espacio

const paginatedRoles = computed(() => {
    return filteredRoles.value.slice(roleStartIndex.value, roleStartIndex.value + rolesPerPage.value);
});

const canGoPrev = computed(() => roleStartIndex.value > 0);
const canGoNext = computed(() => roleStartIndex.value + rolesPerPage.value < filteredRoles.value.length);

const nextRoles = () => {
    if (canGoNext.value) {
        roleStartIndex.value = Math.min(roleStartIndex.value + rolesPerPage.value, filteredRoles.value.length - rolesPerPage.value);
    }
};

const prevRoles = () => {
    if (canGoPrev.value) {
        roleStartIndex.value = Math.max(0, roleStartIndex.value - rolesPerPage.value);
    }
};

// Resetear página al filtrar
import { watch } from 'vue';
watch([filteredRoles, selectedCafeId], () => {
    roleStartIndex.value = 0;
});

// Helper para saber si un rol tiene alguna asignación en la vista actual
const hasRoleInSelection = (roleId: number) => {
    return props.epps.some((epp) => hasRole(epp, roleId));
};
</script>

<template>
    <Head title="Asignación de EPPs" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Personal', href: route('staff.index') },
            { title: 'EPPs', href: route('inventory.invoices.index') },
            { title: 'Matriz de Asignación de EPPs', href: route('clothes.manage') },
        ]"
    >
        <!-- Full constraints to stay within SidebarInset boundaries - Using flex-1 to fill space -->
        <!-- 
            CLAVE: w-px min-w-full asegura que el flex-item NO expanda al padre (SidebarInset).
            overflow-x-hidden en este nivel para matar cualquier scroll lateral de página.
        -->
        <div class="relative flex min-h-0 w-px min-w-full flex-1 flex-col gap-4 overflow-x-hidden overflow-y-hidden bg-slate-50/30 p-4 lg:p-6">
            <!-- Compact Header Section -->
            <div class="flex min-w-0 flex-none flex-col items-center justify-between gap-4 md:flex-row">
                <div class="min-w-0">
                    <h1 class="flex items-center gap-2 text-xl font-black tracking-tight text-slate-900 uppercase italic">
                        <div class="rounded-lg bg-indigo-600 p-1.5 shadow-lg shadow-indigo-200">
                            <Briefcase class="h-5 w-5 text-white" />
                        </div>
                        <span>Matriz EPP</span>
                    </h1>
                </div>

                <!-- Stats Badges (Condensed) -->
                <div class="hidden items-center gap-3 rounded-xl border bg-white p-1.5 shadow-sm lg:flex">
                    <div class="flex items-center gap-2 border-r px-3">
                        <span class="text-[9px] font-black tracking-tighter text-slate-400 uppercase">Asignaciones</span>
                        <span class="text-xs font-black text-indigo-600">{{ stats.totalAssignments }}</span>
                    </div>
                    <div class="flex items-center gap-2 border-r px-3">
                        <span class="text-[9px] font-black tracking-tighter text-slate-400 uppercase">Promedio</span>
                        <span class="text-xs font-black text-indigo-600">{{ stats.averagePerRole }}</span>
                    </div>
                    <div class="flex items-center gap-2 px-3">
                        <span class="text-[9px] font-black tracking-tighter text-slate-400 uppercase">Activos</span>
                        <span class="text-xs font-black text-indigo-600">{{ stats.rolesWithAssignments }}</span>
                    </div>
                </div>

                <div class="flex flex-none items-center gap-2">
                    <Link :href="route('inventory.index')">
                        <Button variant="outline" size="sm" class="h-8 gap-2 border-slate-300 text-[10px] font-black uppercase">
                            <Package class="h-3.5 w-3.5 text-indigo-600" /> Inventario
                        </Button>
                    </Link>
                    <Link :href="route('inventory.invoices.index')" class="flex-shrink-0">
                        <Button variant="ghost" size="sm" class="h-8 gap-2 text-[10px] font-black text-slate-500 uppercase">
                            <ArrowLeft class="h-3.5 w-3.5" /> Volver
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Condensed Action Bar -->
            <div
                class="flex flex-none flex-col items-center gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-4 shadow-2xl shadow-indigo-100 xl:flex-row"
            >
                <div class="grid w-full min-w-0 flex-1 grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                    <div class="min-w-0 space-y-1">
                        <Label class="ml-1 text-[9px] font-black text-slate-400 uppercase">Unidad / Comedor</Label>
                        <Select v-model="selectedCafeId">
                            <SelectTrigger
                                class="h-9 w-full overflow-hidden border-none bg-slate-800 text-white shadow-none focus:ring-1 focus:ring-indigo-500"
                            >
                                <div class="truncate text-[10px] font-black tracking-tight uppercase">
                                    {{ selectedCafeLabel }}
                                </div>
                            </SelectTrigger>
                            <SelectContent class="max-w-[400px]">
                                <SelectItem v-for="cafe in cafes" :key="cafe.id" :value="String(cafe.id)">
                                    <div class="flex flex-col py-0.5">
                                        <span class="text-[11px] font-bold text-slate-900">{{ cafe.name }}</span>
                                        <span class="text-[8px] font-medium tracking-tighter text-slate-400 uppercase">
                                            {{ cafe.unit?.mine?.name }} — {{ cafe.unit?.name }}
                                        </span>
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="min-w-0 space-y-1">
                        <Label class="ml-1 text-[9px] font-black text-slate-400 uppercase">Buscar Cargo</Label>
                        <div class="relative min-w-0">
                            <Briefcase class="absolute top-2.5 left-2.5 h-3.5 w-3.5 text-slate-500" />
                            <Input
                                v-model="searchQuery"
                                placeholder="..."
                                class="h-9 border-none bg-slate-800 pl-8 text-[10px] text-white shadow-none placeholder:text-slate-600 focus:ring-1 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <div class="min-w-0 space-y-1">
                        <Label class="ml-1 text-[9px] font-black text-slate-400 uppercase">Buscar EPP</Label>
                        <div class="relative min-w-0">
                            <Box class="absolute top-2.5 left-2.5 h-3.5 w-3.5 text-slate-500" />
                            <Input
                                v-model="eppSearchQuery"
                                placeholder="..."
                                class="h-9 border-none bg-slate-800 pl-8 text-[10px] text-white shadow-none placeholder:text-slate-600 focus:ring-1 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <div class="min-w-0 space-y-1">
                        <Label class="ml-1 text-[9px] font-black text-slate-400 uppercase">Nuevo EPP</Label>
                        <div class="flex h-9 min-w-0 gap-1.5">
                            <Input
                                v-model="newEppName"
                                placeholder="Nombre..."
                                class="h-full min-w-0 flex-1 border-none bg-slate-800 px-3 text-[10px] text-white shadow-none placeholder:text-slate-600 focus:ring-1 focus:ring-indigo-500"
                                @keyup.enter="createEpp"
                            />
                            <Button
                                @click="createEpp"
                                :disabled="isCreating || !newEppName"
                                size="sm"
                                class="h-full shrink-0 rounded-lg bg-indigo-600 px-3 text-[10px] font-black text-white uppercase hover:bg-indigo-700"
                            >
                                <Plus class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Column Nav Controls (Mobile & Desktop) -->
                <div class="group flex flex-none shrink-0 items-center gap-2 rounded-xl border border-white/5 bg-indigo-500/10 p-1.5">
                    <Button
                        @click="prevRoles"
                        :disabled="!canGoPrev"
                        variant="ghost"
                        size="icon"
                        class="h-10 w-10 rounded-lg text-white transition-all hover:bg-white/10 disabled:opacity-20"
                    >
                        <ArrowLeft class="h-5 w-5" />
                    </Button>
                    <div class="flex flex-col items-center">
                        <span class="text-[9px] leading-none font-black text-indigo-300 uppercase">Cargos</span>
                        <span class="text-[11px] leading-tight font-black text-white">
                            {{ filteredRoles.length > 0 ? roleStartIndex + 1 : 0 }}-{{
                                Math.min(roleStartIndex + rolesPerPage, filteredRoles.length)
                            }}
                        </span>
                        <div class="relative mt-1 h-1 w-8 overflow-hidden rounded-full bg-white/20">
                            <div
                                class="absolute left-0 h-full bg-indigo-400 transition-all duration-300"
                                :style="{ width: `${(Math.min(roleStartIndex + rolesPerPage, filteredRoles.length) / filteredRoles.length) * 100}%` }"
                            ></div>
                        </div>
                    </div>
                    <Button
                        @click="nextRoles"
                        :disabled="!canGoNext"
                        variant="ghost"
                        size="icon"
                        class="h-10 w-10 rounded-lg text-white transition-all hover:bg-white/10 disabled:opacity-20"
                    >
                        <Check class="hidden h-5 w-5 rotate-180" />
                        <!-- Placeholder to keep same arrow icon types if needed -->
                        <ArrowLeft class="h-5 w-5 rotate-180" />
                    </Button>
                </div>
            </div>

            <Card class="flex min-h-0 w-px min-w-full flex-1 flex-col overflow-hidden rounded-2xl border bg-white shadow-xl">
                <CardContent class="flex min-h-0 flex-1 flex-col overflow-hidden p-0">
                    <div class="custom-scrollbar min-h-0 flex-1 overflow-auto">
                        <Table class="relative w-full border-collapse">
                            <TableHeader class="sticky top-0 z-[5] border-b bg-slate-50/95 shadow-sm backdrop-blur-sm">
                                <TableRow class="h-48 border-none hover:bg-transparent">
                                    <TableHead
                                        class="sticky left-0 z-[6] w-[300px] border-r border-b bg-slate-50 p-6 pb-4 align-bottom shadow-[1px_0_0_0_#e2e8f0]"
                                    >
                                        <div class="flex flex-col gap-2">
                                            <Badge
                                                variant="outline"
                                                class="w-fit border-slate-300 px-1.5 py-0 text-[9px] font-black text-slate-500 uppercase"
                                                >Matriz de Rol</Badge
                                            >
                                            <span class="text-xs leading-none font-black tracking-tighter text-slate-900 uppercase italic"
                                                >Roles / Cargos →</span
                                            >
                                            <span class="mt-1 flex items-center gap-1 text-[9px] font-medium text-slate-400">
                                                EPP / Elemento <ArrowLeft class="h-2 w-2 rotate-270" />
                                            </span>
                                        </div>
                                    </TableHead>

                                    <!-- Paginated Headers for Roles -->
                                    <TableHead
                                        v-for="role in paginatedRoles"
                                        :key="role.id"
                                        class="group relative h-48 min-w-[70px] border-r border-b p-0 font-medium transition-all"
                                        :class="hasRoleInSelection(role.id) ? 'bg-indigo-50/30' : 'bg-slate-50/50'"
                                    >
                                        <div class="absolute inset-0 flex items-center justify-center p-2">
                                            <div
                                                class="w-[180px] -rotate-90 transform truncate px-2 text-left text-[10px] font-black tracking-tight whitespace-nowrap text-slate-500 uppercase transition-all group-hover:scale-105 group-hover:text-indigo-600"
                                            >
                                                {{ role.name }}
                                            </div>
                                        </div>
                                        <div v-if="hasRoleInSelection(role.id)" class="absolute right-0 bottom-0 left-0 h-1 bg-indigo-500"></div>
                                    </TableHead>

                                    <!-- Placeholder to fill empty spaces if less than rolesPerPage -->
                                    <TableHead
                                        v-for="i in Math.max(0, rolesPerPage - paginatedRoles.length)"
                                        :key="'empty-' + i"
                                        class="border-r border-b p-0 opacity-10"
                                    ></TableHead>

                                    <TableHead class="w-16 border-b bg-slate-50 p-4 text-center"></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="epp in filteredEpps" :key="epp.id" class="group/row border-b transition-colors hover:bg-slate-50/80">
                                    <TableCell
                                        class="sticky left-0 z-[4] border-r bg-white p-4 font-bold text-slate-700 shadow-[1px_0_0px_0_rgba(0,0,0,0.1)] group-hover/row:bg-slate-50"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="flex h-9 w-9 transform items-center justify-center rounded-xl bg-slate-100 text-slate-400 shadow-sm transition-all group-hover/row:scale-110 group-hover/row:bg-indigo-600 group-hover/row:text-white"
                                            >
                                                <Box class="h-4.5 w-4.5" />
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-xs leading-none font-black tracking-tighter uppercase">{{ epp.name }}</span>
                                                <span class="mt-0.5 text-[9px] text-slate-400 group-hover/row:text-slate-500">ID: #{{ epp.id }}</span>
                                            </div>
                                        </div>
                                    </TableCell>

                                    <!-- Paginated Matrix Intersection Points -->
                                    <TableCell
                                        v-for="role in paginatedRoles"
                                        :key="role.id"
                                        class="group/cell relative min-w-[130px] cursor-pointer border-r p-0 text-center"
                                    >
                                        <div
                                            class="absolute inset-0 transition-all duration-300"
                                            :class="hasRole(epp, role.id) ? 'bg-indigo-500/5' : 'group-hover/cell:bg-indigo-600/5'"
                                            @click="toggleRole(epp.id, role.id, hasRole(epp, role.id))"
                                        ></div>

                                        <!-- Vertical Highlight Helper -->
                                        <div
                                            class="absolute inset-x-0 top-0 h-full w-0.5 bg-indigo-500/0 transition-all group-hover/cell:w-1 group-hover/cell:bg-indigo-500/20"
                                        ></div>

                                        <div class="relative flex flex-col items-center gap-2 px-2 py-4">
                                            <div
                                                v-if="hasRole(epp, role.id)"
                                                class="animate-in zoom-in flex w-full flex-col items-center gap-2 duration-300"
                                            >
                                                <div
                                                    class="mx-auto flex h-7 w-7 scale-100 transform cursor-pointer items-center justify-center rounded-xl bg-indigo-600 shadow-[0_4px_12px_rgba(79,70,229,0.3)] transition-all group-hover/cell:scale-110"
                                                    @click="toggleRole(epp.id, role.id, true)"
                                                >
                                                    <Check class="h-4 w-4 text-white" />
                                                </div>

                                                <div
                                                    class="flex w-full flex-col gap-1.5 rounded-xl border border-slate-100 bg-white/60 p-1.5 shadow-sm backdrop-blur-sm"
                                                    @click.stop
                                                >
                                                    <div class="group/input flex items-center gap-1.5 rounded-lg bg-white p-1 shadow-inner">
                                                        <Input
                                                            type="number"
                                                            :model-value="getPivotData(epp, role.id)?.quantity || 1"
                                                            @change="(e: any) => updatePivot(epp.id, role.id, 'quantity', parseInt(e.target.value))"
                                                            class="h-7 w-full border-none bg-transparent p-0 text-center text-[11px] font-black text-slate-800"
                                                            placeholder="Cant"
                                                        />
                                                    </div>
                                                    <Select
                                                        :model-value="String(getPivotData(epp, role.id)?.color_id || '')"
                                                        @update:model-value="
                                                            (val: any) => updatePivot(epp.id, role.id, 'color_id', val ? parseInt(val) : null)
                                                        "
                                                    >
                                                        <SelectTrigger
                                                            class="h-7 w-full overflow-hidden rounded-lg border-none bg-white px-2 text-[10px] font-bold uppercase shadow-inner"
                                                        >
                                                            <SelectValue placeholder="Color" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem v-for="color in colors" :key="color.id" :value="String(color.id)">
                                                                <span class="text-[10px] font-black uppercase">{{ color.name }}</span>
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                            <div
                                                v-else
                                                class="mx-auto flex h-8 w-8 items-center justify-center rounded-xl border-2 border-dashed border-slate-200 opacity-20 transition-all group-hover/cell:border-indigo-300 group-hover/cell:bg-indigo-100/50 group-hover/cell:opacity-100"
                                                @click="toggleRole(epp.id, role.id, false)"
                                            >
                                                <Plus class="h-4 w-4 text-indigo-400" />
                                            </div>
                                        </div>
                                    </TableCell>

                                    <!-- Fill empty row spaces -->
                                    <TableCell
                                        v-for="i in Math.max(0, rolesPerPage - paginatedRoles.length)"
                                        :key="'cell-empty-' + i"
                                        class="border-r bg-slate-50/20 p-0"
                                    ></TableCell>

                                    <TableCell class="p-4 text-center">
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        @click="deleteEpp(epp.id)"
                                                        class="h-9 w-9 rounded-xl p-0 text-slate-300 transition-all hover:bg-rose-50 hover:text-rose-600"
                                                    >
                                                        <Trash2 class="h-4.5 w-4.5" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent><p class="text-[10px] font-black uppercase">Eliminar EPP</p></TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </TableCell>
                                </TableRow>

                                <TableRow v-if="filteredEpps.length === 0">
                                    <TableCell
                                        :colspan="paginatedRoles.length + 2 + Math.max(0, rolesPerPage - paginatedRoles.length)"
                                        class="h-64 text-center"
                                    >
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-50">
                                                <Box class="h-6 w-6 text-slate-200" />
                                            </div>
                                            <p class="font-medium text-slate-400 italic">No se encontraron EPPs con los filtros actuales.</p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <!-- Footer Info -->
            <div class="text-muted-foreground flex flex-none flex-col items-center justify-between gap-2 border-t bg-gray-50 p-4 text-sm sm:flex-row">
                <div class="flex items-center gap-4">
                    <span class="flex items-center gap-1">
                        <Briefcase class="h-3 w-3" />
                        {{ roles.length }} Roles
                    </span>
                    <span class="flex items-center gap-1">
                        <Box class="h-3 w-3" />
                        {{ epps.length }} EPPs
                    </span>
                </div>
                <div class="text-xs text-gray-500">Haz clic en una intersección para asignar o quitar un EPP de un rol</div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Scrollbar moderno */
.custom-scrollbar::-webkit-scrollbar {
    height: 10px;
    width: 10px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f8fafc;
    border-radius: 5px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 5px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Transiciones suaves */
.group:hover .group-hover\:scale-110 {
    transform: scale(1.1);
}
</style>
