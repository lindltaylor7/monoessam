<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Trash2, Plus, ArrowLeft, Shirt, Briefcase, Check, X, Coffee, Package, Box } from 'lucide-vue-next';
import Swal from 'sweetalert2';

import { 
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow 
} from '@/components/ui/table';
import { Label } from '@/components/ui/label';
import { 
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

const props = defineProps<{
    roles: Array<{ id: number, name: string }>;
    epps: Array<{ id: number, name: string, quantity: number, roles: Array<{ id: number, pivot: { cafe_id: number } }> }>;
    cafes: Array<{ 
        id: number, 
        name: string, 
        roles: Array<{ id: number }>,
        unit: { name: string, mine: { name: string } } 
    }>;
    colors: Array<{ id: number, name: string }>;
}>();

const newEppName = ref('');
const isCreating = ref(false);
const searchQuery = ref('');
const selectedCafeId = ref(props.cafes.length > 0 ? String(props.cafes[0].id) : '');
const eppSearchQuery = ref('');

// Filtrar EPPs según búsqueda
const filteredEpps = computed(() => {
    if (!eppSearchQuery.value) return props.epps;
    return props.epps.filter(epp => 
        epp.name.toLowerCase().includes(eppSearchQuery.value.toLowerCase())
    );
});

// Filtrar roles según el café seleccionado y la búsqueda
const filteredRoles = computed(() => {
    const currentCafe = props.cafes.find(c => String(c.id) === selectedCafeId.value);
    const cafeRoleIds = currentCafe?.roles?.map(r => r.id) || [];
    
    const roles = props.roles.filter(role => cafeRoleIds.includes(role.id));

    if (!searchQuery.value) return roles;
    return roles.filter(role => 
        role.name.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

// Obtener EPPs asignados a un rol específico y café seleccionado
const getEppsForRole = (roleId: number) => {
    return props.epps.filter(epp => 
        epp.roles.some(role => role.id === roleId && String(role.pivot.cafe_id) === selectedCafeId.value)
    );
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
    if(!newEppName.value) return;
    isCreating.value = true;
    router.post(route('inventory.epps.store'), {
        name: newEppName.value,
        category_epp_id: 'none', // Seteamos por defecto
        size_ids: []
    }, {
        onSuccess: () => newEppName.value = '',
        onFinish: () => isCreating.value = false
    });
};

const deleteEpp = (id: number) => {
    if(confirm('¿Seguro que deseas eliminar este EPP y todas sus asignaciones?')) {
        router.delete(route('clothes.epps.destroy', id), {
             preserveScroll: true
        });
    }
};

const toggleRole = (eppId: number, roleId: number, currentStatus: boolean, quantity: number = 1, color_id: number | null = null) => {
    if (!selectedCafeId.value) {
        Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'Por favor selecciona un café primero'
        });
        return;
    }

    Swal.fire({
        title: 'Procesando...',
        text: currentStatus ? 'Desvinculando EPP...' : 'Asignando EPP...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    router.post(route('clothes.assign-epp-role'), {
        epp_id: eppId,
        role_id: roleId,
        cafe_id: selectedCafeId.value,
        action: currentStatus ? 'detach' : 'attach',
        quantity: quantity,
        color_id: color_id
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => Swal.close()
    });
};

const updatePivot = (eppId: number, roleId: number, field: string, value: any) => {
    const epp = props.epps.find(e => e.id === eppId);
    const pivot = getPivotData(epp, roleId);
    if (!pivot) return;

    const data = {
        quantity: pivot.quantity,
        color_id: pivot.color_id,
        [field]: value
    };

    Swal.fire({
        title: 'Actualizando...',
        text: 'Guardando cambios en la matriz',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    router.post(route('clothes.assign-epp-role'), {
        epp_id: eppId,
        role_id: roleId,
        cafe_id: selectedCafeId.value,
        action: 'attach',
        ...data
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => Swal.close()
    });
};

// Calcular estadísticas
const stats = computed(() => {
    const totalAssignments = props.roles.reduce((acc, role) => {
        return acc + getEppsForRole(role.id).length;
    }, 0);
    
    const averagePerRole = props.roles.length > 0 
        ? (totalAssignments / props.roles.length).toFixed(1) 
        : '0';
    
    return {
        totalAssignments,
        averagePerRole,
        rolesWithAssignments: props.roles.filter(role => getEppsForRole(role.id).length > 0).length
    };
});

const selectedCafeLabel = computed(() => {
    const cafe = props.cafes.find(c => String(c.id) === selectedCafeId.value);
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
        roleStartIndex.value = Math.min(
            roleStartIndex.value + rolesPerPage.value, 
            filteredRoles.value.length - rolesPerPage.value
        );
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
    return props.epps.some(epp => hasRole(epp, roleId));
};
</script>

<template>
    <Head title="Asignación de EPPs" />

    <AppLayout :breadcrumbs="[
        { title: 'Personal', href: route('staff.index') },
        { title: 'EPPs', href: route('inventory.invoices.index') },
        { title: 'Matriz de Asignación de EPPs', href: route('clothes.manage') }
    ]">
        <!-- Full constraints to stay within SidebarInset boundaries - Using flex-1 to fill space -->
        <!-- 
            CLAVE: w-px min-w-full asegura que el flex-item NO expanda al padre (SidebarInset).
            overflow-x-hidden en este nivel para matar cualquier scroll lateral de página.
        -->
        <div class="flex-1 flex flex-col min-h-0 w-px min-w-full overflow-x-hidden overflow-y-hidden p-4 lg:p-6 gap-4 relative bg-slate-50/30">
            
            <!-- Compact Header Section -->
            <div class="flex flex-col md:flex-row justify-between items-center gap-4 flex-none min-w-0">
                 <div class="min-w-0">
                    <h1 class="text-xl font-black tracking-tight flex items-center gap-2 text-slate-900 uppercase italic">
                        <div class="p-1.5 bg-indigo-600 rounded-lg shadow-lg shadow-indigo-200">
                            <Briefcase class="h-5 w-5 text-white" />
                        </div>
                        <span>Matriz EPP</span>
                    </h1>
                 </div>
                 
                 <!-- Stats Badges (Condensed) -->
                 <div class="hidden lg:flex items-center gap-3 bg-white p-1.5 rounded-xl border shadow-sm">
                     <div class="flex items-center gap-2 px-3 border-r">
                         <span class="text-[9px] font-black text-slate-400 uppercase tracking-tighter">Asignaciones</span>
                         <span class="text-xs font-black text-indigo-600">{{ stats.totalAssignments }}</span>
                     </div>
                     <div class="flex items-center gap-2 px-3 border-r">
                         <span class="text-[9px] font-black text-slate-400 uppercase tracking-tighter">Promedio</span>
                         <span class="text-xs font-black text-indigo-600">{{ stats.averagePerRole }}</span>
                     </div>
                     <div class="flex items-center gap-2 px-3">
                         <span class="text-[9px] font-black text-slate-400 uppercase tracking-tighter">Activos</span>
                         <span class="text-xs font-black text-indigo-600">{{ stats.rolesWithAssignments }}</span>
                     </div>
                 </div>

                  <div class="flex items-center gap-2 flex-none">
                    <Link :href="route('inventory.index')">
                        <Button variant="outline" size="sm" class="h-8 gap-2 font-black uppercase text-[10px] border-slate-300">
                            <Package class="h-3.5 w-3.5 text-indigo-600" /> Inventario
                        </Button>
                    </Link>
                    <Link :href="route('inventory.invoices.index')" class="flex-shrink-0">
                        <Button variant="ghost" size="sm" class="h-8 gap-2 font-black uppercase text-[10px] text-slate-500">
                            <ArrowLeft class="h-3.5 w-3.5" /> Volver
                        </Button>
                    </Link>
                  </div>
            </div>

            <!-- Condensed Action Bar -->
            <div class="bg-slate-900 rounded-2xl p-4 flex flex-col xl:flex-row gap-3 items-center flex-none shadow-2xl shadow-indigo-100 border border-slate-800">
                <div class="flex-1 w-full grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 min-w-0">
                    <div class="space-y-1 min-w-0">
                        <Label class="text-[9px] font-black uppercase text-slate-400 ml-1">Unidad / Café</Label>
                        <Select v-model="selectedCafeId">
                            <SelectTrigger class="h-9 bg-slate-800 border-none text-white shadow-none focus:ring-1 focus:ring-indigo-500 w-full overflow-hidden">
                                <div class="truncate text-[10px] font-black uppercase tracking-tight">
                                    {{ selectedCafeLabel }}
                                </div>
                            </SelectTrigger>
                            <SelectContent class="max-w-[400px]">
                                <SelectItem v-for="cafe in cafes" :key="cafe.id" :value="String(cafe.id)">
                                    <div class="flex flex-col py-0.5">
                                        <span class="font-bold text-slate-900 text-[11px]">{{ cafe.name }}</span>
                                        <span class="text-[8px] text-slate-400 uppercase tracking-tighter font-medium">
                                            {{ cafe.unit?.mine?.name }} — {{ cafe.unit?.name }}
                                        </span>
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-1 min-w-0">
                        <Label class="text-[9px] font-black uppercase text-slate-400 ml-1">Buscar Cargo</Label>
                        <div class="relative min-w-0">
                            <Briefcase class="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-500" />
                            <Input 
                                v-model="searchQuery"
                                placeholder="..." 
                                class="pl-8 h-9 bg-slate-800 border-none text-white shadow-none focus:ring-1 focus:ring-indigo-500 text-[10px] placeholder:text-slate-600"
                            />
                        </div>
                    </div>

                    <div class="space-y-1 min-w-0">
                        <Label class="text-[9px] font-black uppercase text-slate-400 ml-1">Buscar EPP</Label>
                        <div class="relative min-w-0">
                            <Box class="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-500" />
                            <Input 
                                v-model="eppSearchQuery"
                                placeholder="..." 
                                class="pl-8 h-9 bg-slate-800 border-none text-white shadow-none focus:ring-1 focus:ring-indigo-500 text-[10px] placeholder:text-slate-600"
                            />
                        </div>
                    </div>

                    <div class="space-y-1 min-w-0">
                        <Label class="text-[9px] font-black uppercase text-slate-400 ml-1">Nuevo EPP</Label>
                        <div class="flex gap-1.5 min-w-0 h-9">
                            <Input 
                                v-model="newEppName" 
                                placeholder="Nombre..." 
                                class="h-full bg-slate-800 border-none text-white shadow-none focus:ring-1 focus:ring-indigo-500 text-[10px] placeholder:text-slate-600 flex-1 min-w-0 px-3"
                                @keyup.enter="createEpp" 
                            />
                            <Button @click="createEpp" :disabled="isCreating || !newEppName" size="sm" class="h-full bg-indigo-600 text-white hover:bg-indigo-700 font-black uppercase text-[10px] px-3 shrink-0 rounded-lg">
                                <Plus class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Column Nav Controls (Mobile & Desktop) -->
                <div class="flex items-center gap-2 bg-indigo-500/10 p-1.5 rounded-xl border border-white/5 flex-none shrink-0 group">
                    <Button 
                        @click="prevRoles" 
                        :disabled="!canGoPrev" 
                        variant="ghost" 
                        size="icon" 
                        class="h-10 w-10 text-white hover:bg-white/10 disabled:opacity-20 transition-all rounded-lg"
                    >
                        <ArrowLeft class="h-5 w-5" />
                    </Button>
                    <div class="flex flex-col items-center">
                        <span class="text-[9px] font-black text-indigo-300 uppercase leading-none">Cargos</span>
                        <span class="text-[11px] font-black text-white leading-tight">
                            {{ filteredRoles.length > 0 ? roleStartIndex + 1 : 0 }}-{{ Math.min(roleStartIndex + rolesPerPage, filteredRoles.length) }}
                        </span>
                        <div class="w-8 h-1 bg-white/20 rounded-full mt-1 overflow-hidden relative">
                            <div 
                                class="h-full bg-indigo-400 absolute transition-all duration-300 left-0"
                                :style="{ width: `${(Math.min(roleStartIndex + rolesPerPage, filteredRoles.length) / filteredRoles.length) * 100}%` }"
                            ></div>
                        </div>
                    </div>
                    <Button 
                        @click="nextRoles" 
                        :disabled="!canGoNext" 
                        variant="ghost" 
                        size="icon" 
                        class="h-10 w-10 text-white hover:bg-white/10 disabled:opacity-20 transition-all rounded-lg"
                    >
                        <Check class="h-5 w-5 rotate-180 hidden" /> <!-- Placeholder to keep same arrow icon types if needed -->
                        <ArrowLeft class="h-5 w-5 rotate-180" />
                    </Button>
                </div>
            </div>

            <Card class="flex-1 min-h-0 w-px min-w-full overflow-hidden flex flex-col rounded-2xl border shadow-xl bg-white">
                <CardContent class="p-0 flex-1 overflow-hidden flex flex-col min-h-0">
                    <div class="flex-1 overflow-auto custom-scrollbar min-h-0">
                        <Table class="relative w-full border-collapse">
                            <TableHeader class="sticky top-0 z-[5] bg-slate-50/95 backdrop-blur-sm border-b shadow-sm">
                                <TableRow class="hover:bg-transparent h-48 border-none">
                                    <TableHead class="w-[300px] bg-slate-50 p-6 border-r border-b sticky left-0 z-[6] align-bottom pb-4 shadow-[1px_0_0_0_#e2e8f0]">
                                        <div class="flex flex-col gap-2">
                                            <Badge variant="outline" class="w-fit text-[9px] font-black uppercase border-slate-300 text-slate-500 py-0 px-1.5">Matriz de Rol</Badge>
                                            <span class="text-xs font-black text-slate-900 uppercase italic tracking-tighter leading-none">Roles / Cargos →</span>
                                            <span class="text-[9px] font-medium text-slate-400 flex items-center gap-1 mt-1">
                                               EPP / Elemento <ArrowLeft class="h-2 w-2 rotate-270" />
                                            </span>
                                        </div>
                                    </TableHead>
                                    
                                    <!-- Paginated Headers for Roles -->
                                    <TableHead 
                                        v-for="role in paginatedRoles" 
                                        :key="role.id" 
                                        class="p-0 border-r border-b min-w-[70px] h-48 relative font-medium group transition-all"
                                        :class="hasRoleInSelection(role.id) ? 'bg-indigo-50/30' : 'bg-slate-50/50'"
                                    >
                                        <div class="absolute inset-0 flex items-center justify-center p-2">
                                            <div class="transform -rotate-90 whitespace-nowrap text-[10px] font-black uppercase tracking-tight text-slate-500 group-hover:text-indigo-600 group-hover:scale-105 transition-all w-[180px] text-left px-2 truncate">
                                                {{ role.name }}
                                            </div>
                                        </div>
                                        <div v-if="hasRoleInSelection(role.id)" class="absolute bottom-0 left-0 right-0 h-1 bg-indigo-500"></div>
                                    </TableHead>

                                    <!-- Placeholder to fill empty spaces if less than rolesPerPage -->
                                    <TableHead 
                                        v-for="i in Math.max(0, rolesPerPage - paginatedRoles.length)" 
                                        :key="'empty-' + i"
                                        class="p-0 border-r border-b opacity-10"
                                    ></TableHead>

                                    <TableHead class="w-16 p-4 text-center border-b bg-slate-50"></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="epp in filteredEpps" :key="epp.id" class="group/row hover:bg-slate-50/80 transition-colors border-b">
                                    <TableCell class="p-4 font-bold text-slate-700 sticky left-0 z-[4] bg-white group-hover/row:bg-slate-50 border-r shadow-[1px_0_0px_0_rgba(0,0,0,0.1)]">
                                        <div class="flex items-center gap-3">
                                            <div class="h-9 w-9 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400 group-hover/row:bg-indigo-600 group-hover/row:text-white transition-all transform group-hover/row:scale-110 shadow-sm">
                                                <Box class="h-4.5 w-4.5" />
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-xs uppercase font-black leading-none tracking-tighter">{{ epp.name }}</span>
                                                <span class="text-[9px] text-slate-400 mt-0.5 group-hover/row:text-slate-500">ID: #{{ epp.id }}</span>
                                            </div>
                                        </div>
                                    </TableCell>
                                    
                                    <!-- Paginated Matrix Intersection Points -->
                                    <TableCell 
                                        v-for="role in paginatedRoles" 
                                        :key="role.id" 
                                        class="p-0 border-r text-center group/cell cursor-pointer relative min-w-[130px]"
                                    >
                                        <div 
                                            class="absolute inset-0 transition-all duration-300"
                                            :class="hasRole(epp, role.id) ? 'bg-indigo-500/5' : 'group-hover/cell:bg-indigo-600/5'"
                                            @click="toggleRole(epp.id, role.id, hasRole(epp, role.id))"
                                        ></div>
                                        
                                        <!-- Vertical Highlight Helper -->
                                        <div class="absolute inset-x-0 top-0 h-full w-0.5 bg-indigo-500/0 group-hover/cell:w-1 group-hover/cell:bg-indigo-500/20 transition-all"></div>

                                        <div class="relative py-4 px-2 flex flex-col items-center gap-2">
                                            <div v-if="hasRole(epp, role.id)" class="flex flex-col items-center gap-2 w-full animate-in zoom-in duration-300">
                                                <div 
                                                    class="mx-auto h-7 w-7 rounded-xl bg-indigo-600 flex items-center justify-center transform scale-100 shadow-[0_4px_12px_rgba(79,70,229,0.3)] transition-all group-hover/cell:scale-110 cursor-pointer"
                                                    @click="toggleRole(epp.id, role.id, true)"
                                                >
                                                    <Check class="h-4 w-4 text-white" />
                                                </div>
                                                
                                                <div class="flex flex-col gap-1.5 w-full bg-white/60 backdrop-blur-sm rounded-xl p-1.5 border border-slate-100 shadow-sm" @click.stop>
                                                    <div class="flex items-center gap-1.5 bg-white rounded-lg p-1 shadow-inner group/input">
                                                        <Input 
                                                            type="number" 
                                                            :model-value="getPivotData(epp, role.id)?.quantity || 1"
                                                            @change="(e: any) => updatePivot(epp.id, role.id, 'quantity', parseInt(e.target.value))"
                                                            class="h-7 w-full text-[11px] text-center p-0 border-none bg-transparent font-black text-slate-800"
                                                            placeholder="Cant"
                                                        />
                                                    </div>
                                                    <Select 
                                                        :model-value="String(getPivotData(epp, role.id)?.color_id || '')" 
                                                        @update:model-value="(val: any) => updatePivot(epp.id, role.id, 'color_id', val ? parseInt(val) : null)"
                                                    >
                                                        <SelectTrigger class="h-7 w-full text-[10px] px-2 border-none bg-white shadow-inner font-bold uppercase overflow-hidden rounded-lg">
                                                            <SelectValue placeholder="Color" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem v-for="color in colors" :key="color.id" :value="String(color.id)">
                                                                <span class="text-[10px] uppercase font-black">{{ color.name }}</span>
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                            <div 
                                                v-else
                                                class="mx-auto h-8 w-8 rounded-xl border-2 border-dashed border-slate-200 opacity-20 group-hover/cell:opacity-100 group-hover/cell:bg-indigo-100/50 group-hover/cell:border-indigo-300 transition-all flex items-center justify-center"
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
                                        class="p-0 border-r bg-slate-50/20"
                                    ></TableCell>

                                    <TableCell class="p-4 text-center">
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button variant="ghost" size="sm" @click="deleteEpp(epp.id)" class="text-slate-300 hover:text-rose-600 hover:bg-rose-50 transition-all h-9 w-9 p-0 rounded-xl">
                                                        <Trash2 class="h-4.5 w-4.5" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent><p class="text-[10px] font-black uppercase">Eliminar EPP</p></TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </TableCell>
                                </TableRow>

                                <TableRow v-if="filteredEpps.length === 0">
                                    <TableCell :colspan="paginatedRoles.length + 2 + Math.max(0, rolesPerPage - paginatedRoles.length)" class="h-64 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="h-12 w-12 bg-slate-50 rounded-full flex items-center justify-center">
                                                <Box class="h-6 w-6 text-slate-200" />
                                            </div>
                                            <p class="text-slate-400 font-medium italic">No se encontraron EPPs con los filtros actuales.</p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <!-- Footer Info -->
            <div class="p-4 border-t bg-gray-50 text-sm text-muted-foreground flex flex-col sm:flex-row justify-between items-center gap-2 flex-none">
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
                <div class="text-xs text-gray-500">
                    Haz clic en una intersección para asignar o quitar un EPP de un rol
                </div>
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